<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserSettingsService
{
    private const USER_SORT_COLUMNS = [
        'name' => 'users.name',
        'email' => 'users.email',
        'locale' => 'users.locale',
        'is_active' => 'users.is_active',
        'created_at' => 'users.created_at',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SuperAdminProtection $superAdminProtection,
    ) {}

    /**
     * @return array{
     *     userCount: int,
     *     userOptions: Collection<int, array{id: int|string, name: string, email: string}>,
     *     roles: Collection<int, mixed>,
     *     allPermissions: Collection<int, string>
     * }
     */
    public function indexData(): array
    {
        return [
            'userCount' => User::query()->count(),
            'userOptions' => User::query()
                ->orderBy('email')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values(),
            'roles' => Role::query()
                ->with(['permissions' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'isTemplate' => (bool) $role->is_template,
                    'permissions' => $role->permissions
                        ->pluck('name')
                        ->values(),
                ])
                ->values(),
            'allPermissions' => Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = User::query()
            ->select('users.*')
            ->with('roles');

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $pattern = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($pattern): void {
                    $nested->whereRaw('LOWER(users.name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(users.email) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(users.locale, \'\')) LIKE ?', [$pattern])
                        ->orWhereHas('roles', fn (Builder $roles) => $roles->whereRaw('LOWER(roles.name) LIKE ?', [$pattern]));
                });
            })
            ->order(function (Builder $builder): void {
                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset(self::USER_SORT_COLUMNS[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    $builder->orderBy(self::USER_SORT_COLUMNS[$data], $direction);
                }

                $builder->orderBy('users.email')->orderBy('users.id');
            })
            ->editColumn('roles', fn (User $user): array => $user->roles
                ->sortBy('name')
                ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
                ->values()
                ->all())
            ->editColumn('is_active', fn (User $user): bool => (bool) $user->is_active)
            ->addColumn('isActive', fn (User $user): bool => (bool) $user->is_active)
            ->toJson();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, bool $isActive, int $actorId): User
    {
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale' => $validated['locale'] ?? 'en',
            'theme' => 'dark',
            'is_active' => $isActive,
        ]);

        $this->syncRole($user, $validated['role_id'] ?? null, array_key_exists('role_id', $validated));
        $this->auditLogger->record($actorId, 'user.create', 'user', (string) $user->id, after: $user->toArray());

        return $user;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(string $userId, array $validated, bool $hasIsActive, bool $isActive, bool $hasRoleId, bool $filledPassword, int $actorId): User
    {
        $user = User::findOrFail($userId);
        $roleId = $validated['role_id'] ?? null;

        if ($this->superAdminProtection->wouldDeactivateLastActiveSuperAdmin($user, $hasIsActive, $isActive)) {
            throw ValidationException::withMessages(['is_active' => __('Cannot deactivate the last active super admin user.')]);
        }

        if ($this->superAdminProtection->wouldWeakenLastActiveSuperAdmin($user, $hasRoleId, $roleId)) {
            throw ValidationException::withMessages(['role_id' => __('Cannot remove super admin role from the last active super admin user.')]);
        }

        $before = $user->toArray();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (isset($validated['locale'])) {
            $user->locale = $validated['locale'];
        }
        if ($hasIsActive) {
            $user->is_active = $isActive;
        }

        if ($filledPassword) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $this->syncRole($user, $roleId, $hasRoleId, replace: true);
        $this->auditLogger->record($actorId, 'user.update', 'user', (string) $user->id, before: $before, after: $user->toArray());

        return $user;
    }

    public function delete(string $userId, int $actorId): void
    {
        if ((string) $userId === (string) $actorId) {
            throw ValidationException::withMessages(['user' => __('You cannot delete your own user account.')]);
        }

        $user = User::findOrFail($userId);

        if ($this->superAdminProtection->isSuperAdmin($user) && $this->superAdminProtection->activeSuperAdminCount() <= 1) {
            throw ValidationException::withMessages(['user' => __('Cannot delete the last active super admin user.')]);
        }

        $before = $user->toArray();
        $user->delete();

        $this->auditLogger->record($actorId, 'user.delete', 'user', (string) $userId, before: $before);
    }

    private function syncRole(User $user, mixed $roleId, bool $roleWasSubmitted, bool $replace = false): void
    {
        if (! $roleWasSubmitted) {
            return;
        }

        if (! $roleId) {
            if ($replace) {
                $user->syncRoles([]);
            }

            return;
        }

        $role = Role::find($roleId);

        if (! $role) {
            return;
        }

        $replace ? $user->syncRoles([$role]) : $user->assignRole($role);
    }
}
