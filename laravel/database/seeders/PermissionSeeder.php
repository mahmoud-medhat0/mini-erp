<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Seed all system permissions and assign to global role templates.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('erp_rbac.guard', 'web');

        // 1. Gather all module permissions and sensitive capabilities
        $permissions = [];
        foreach (config('erp_rbac.modules') as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        $allPermissions = array_values(array_unique([
            ...$permissions,
            ...config('erp_rbac.sensitive_capabilities', []),
        ]));

        // 2. Create or update permissions catalog
        foreach ($allPermissions as $permissionName) {
            [$module, $action] = str_contains($permissionName, '.')
                ? explode('.', $permissionName, 2)
                : ['_capability', $permissionName];

            Permission::query()->updateOrCreate(
                ['name' => $permissionName, 'guard_name' => $guard],
                ['module' => $module, 'action' => $action]
            );
        }

        // 3. Sync permissions for role templates
        foreach (config('erp_rbac.role_templates', []) as $roleName => $definition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
                ['is_template' => true]
            );

            $assignedPermissions = [];

            if ($definition['all'] ?? false) {
                $excluded = $definition['except'] ?? [];
                $assignedPermissions = array_values(array_diff($allPermissions, $excluded));
            } else {
                $assignedPermissions = $definition['permissions'] ?? [];

                if ($definition['modules_all'] ?? false) {
                    foreach ($definition['modules_all'] as $mod) {
                        foreach (config("erp_rbac.modules.{$mod}", []) as $act) {
                            $assignedPermissions[] = "{$mod}.{$act}";
                        }
                    }
                }
            }

            $permissionIds = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', array_values(array_unique($assignedPermissions)))
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
