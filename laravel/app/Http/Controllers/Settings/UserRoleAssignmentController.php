<?php

namespace App\Http\Controllers\Settings;

use App\Application\Notifications\NotificationService;
use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserRoleAssignmentController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notificationService,
    ) {}

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $role = Role::query()->findOrFail($validated['role_id']);
        $user->assignRole($role);

        $this->notificationService->create($user->id, 'role.assigned', "role:{$role->name}");
        $this->auditLogger->record($request->user()->id, 'user.role.assigned', 'user', (string) $user->id, after: ['role' => $role->name]);

        return back()->with('success', __('Role assigned.'));
    }

    public function revoke(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $role = Role::query()->findOrFail($validated['role_id']);

        if ($this->isSuperAdminRole($role->name) && $this->wouldRemoveLastActiveSuperAdmin($user, $role)) {
            return back()->withErrors(['role_id' => __('Cannot remove super admin role from the last active super admin user.')]);
        }

        $user->removeRole($role);

        $this->notificationService->create($user->id, 'role.revoked', "role:{$role->name}");
        $this->auditLogger->record($request->user()->id, 'user.role.revoked', 'user', (string) $user->id, after: ['role' => $role->name]);

        return back()->with('success', __('Role revoked.'));
    }

    private function wouldRemoveLastActiveSuperAdmin(User $user, Role $role): bool
    {
        $activeSuperAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%super%']))
            ->count();

        return $activeSuperAdmins <= 1 && $user->is_active && $user->hasRole($role->name);
    }

    private function isSuperAdminRole(string $roleName): bool
    {
        return str_contains(strtolower($roleName), 'super');
    }
}
