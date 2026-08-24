<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Users', [
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale' => $validated['locale'] ?? 'en',
            'theme' => 'dark',
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncRequestedRole($request, $user);
        $this->auditLogger->record($request->user()->id, 'user.create', 'user', (string) $user->id, after: $user->toArray());

        return back()->with('success', __('User created successfully.'));
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        if ($this->wouldDeactivateLastActiveSuperAdmin($request, $user)) {
            return back()->withErrors(['is_active' => __('Cannot deactivate the last active super admin user.')]);
        }

        if ($this->wouldWeakenLastActiveSuperAdmin($request, $user)) {
            return back()->withErrors(['role_id' => __('Cannot remove super admin role from the last active super admin user.')]);
        }

        $before = $user->toArray();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if ($request->filled('locale')) {
            $user->locale = $validated['locale'];
        }
        $user->is_active = $request->boolean('is_active', true);

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $this->syncRequestedRole($request, $user, replace: true);
        $this->auditLogger->record($request->user()->id, 'user.update', 'user', (string) $user->id, before: $before, after: $user->toArray());

        return back()->with('success', __('User updated successfully.'));
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        if ((string) $userId === (string) $request->user()->id) {
            return back()->withErrors(['user' => __('You cannot delete your own user account.')]);
        }

        $user = User::findOrFail($userId);

        if ($this->isSuperAdmin($user) && $this->activeSuperAdminCount() <= 1) {
            return back()->withErrors(['user' => __('Cannot delete the last active super admin user.')]);
        }

        $before = $user->toArray();
        $user->delete();

        $this->auditLogger->record($request->user()->id, 'user.delete', 'user', (string) $userId, before: $before);

        return back()->with('success', __('User deleted successfully.'));
    }

    private function syncRequestedRole(Request $request, User $user, bool $replace = false): void
    {
        if (! $request->has('role_id')) {
            return;
        }

        if (! $request->filled('role_id')) {
            if ($replace) {
                $user->syncRoles([]);
            }

            return;
        }

        $role = Role::find($request->role_id);

        if (! $role) {
            return;
        }

        $replace ? $user->syncRoles([$role]) : $user->assignRole($role);
    }

    private function wouldDeactivateLastActiveSuperAdmin(Request $request, User $user): bool
    {
        return $this->isSuperAdmin($user)
            && $this->activeSuperAdminCount() <= 1
            && $request->has('is_active')
            && ! $request->boolean('is_active');
    }

    private function wouldWeakenLastActiveSuperAdmin(Request $request, User $user): bool
    {
        if (! $this->isSuperAdmin($user) || $this->activeSuperAdminCount() > 1 || ! $request->has('role_id')) {
            return false;
        }

        $newRoleId = $request->input('role_id');
        $newRole = $newRoleId ? Role::find($newRoleId) : null;

        return ! $newRole || ! str_contains(strtolower($newRole->name), 'super');
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->roles()->whereRaw('LOWER(name) LIKE ?', ['%super%'])->exists();
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%super%']))
            ->count();
    }
}
