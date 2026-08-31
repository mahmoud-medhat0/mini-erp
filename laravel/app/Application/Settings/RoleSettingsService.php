<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleSettingsService
{
    /** @var list<string> */
    private const SUPER_ADMIN_REQUIRED_PERMISSIONS = [
        'settings.configure',
        'users.configure',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SuperAdminProtection $superAdminProtection,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, int $actorId): Role
    {
        if ($this->superAdminProtection->isSystemRoleName($validated['name'])) {
            throw ValidationException::withMessages([
                'name' => __('System role names are reserved and cannot be created manually.'),
            ]);
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'is_template' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $this->auditLogger->record($actorId, 'role.create', 'role', (string) $role->id, after: $validated);

        return $role;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(int $roleId, array $validated, int $actorId): Role
    {
        return DB::transaction(function () use ($roleId, $validated, $actorId): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($roleId);
            $before = $role->load('permissions')->toArray();
            $permissions = array_values(array_unique($validated['permissions'] ?? []));

            if ($this->superAdminProtection->isProtectedSystemRole($role) && $validated['name'] !== $role->name) {
                throw ValidationException::withMessages([
                    'name' => __('System role names cannot be changed.'),
                ]);
            }

            if ($this->superAdminProtection->isProtectedSystemRole($role) && $permissions === []) {
                throw ValidationException::withMessages([
                    'permissions' => __('System roles must keep at least one permission.'),
                ]);
            }

            if ($this->superAdminProtection->isSuperAdminRole($role->name)) {
                $missingPermissions = array_diff(self::SUPER_ADMIN_REQUIRED_PERMISSIONS, $permissions);

                if ($missingPermissions !== []) {
                    throw ValidationException::withMessages([
                        'permissions' => __('The super admin role must keep settings and user administration permissions.'),
                    ]);
                }
            }

            $role->update(['name' => $validated['name']]);
            $role->syncPermissions($permissions);

            $role->load('permissions');
            $this->auditLogger->record($actorId, 'role.update', 'role', (string) $role->id, before: $before, after: $role->toArray());

            return $role;
        });
    }

    public function delete(int $roleId, int $actorId): void
    {
        $role = Role::findOrFail($roleId);

        abort_if($this->superAdminProtection->isProtectedSystemRole($role), 403, __('System roles cannot be deleted.'));

        $before = $role->toArray();
        $role->delete();

        $this->auditLogger->record($actorId, 'role.delete', 'role', (string) $roleId, before: $before);
    }
}
