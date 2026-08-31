<?php

namespace App\Application\Settings;

use App\Application\Notifications\NotificationService;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserRoleAssignmentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notificationService,
        private readonly SuperAdminProtection $superAdminProtection,
    ) {}

    /**
     * @param  array{user_id: int|string, role_id: int|string}  $validated
     */
    public function assign(array $validated, int $actorId): void
    {
        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        $user->assignRole($role);

        $this->notificationService->create($user->id, 'role.assigned', "role:{$role->name}");
        $this->auditLogger->record($actorId, 'user.role.assigned', 'user', (string) $user->id, after: ['role' => $role->name]);
    }

    /**
     * @param  array{user_id: int|string, role_id: int|string}  $validated
     */
    public function revoke(array $validated, int $actorId): void
    {
        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        if ($this->superAdminProtection->wouldRemoveLastActiveSuperAdmin($user, $role)) {
            throw ValidationException::withMessages(['role_id' => __('Cannot remove super admin role from the last active super admin user.')]);
        }

        $user->removeRole($role);

        $this->notificationService->create($user->id, 'role.revoked', "role:{$role->name}");
        $this->auditLogger->record($actorId, 'user.role.revoked', 'user', (string) $user->id, after: ['role' => $role->name]);
    }
}
