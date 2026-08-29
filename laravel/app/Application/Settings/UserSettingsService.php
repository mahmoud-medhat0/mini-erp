<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SuperAdminProtection $superAdminProtection,
    ) {}

    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function indexData(): array
    {
        return [
            'users' => User::query()
                ->with('roles')
                ->orderBy('email')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'theme' => $user->theme,
                    'isActive' => $user->is_active,
                    'roles' => $user->roles
                        ->sortBy('name')
                        ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
                        ->values(),
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
