<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * Seed the module.action catalog and global role templates.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('erp_rbac.guard', 'web');
        $permissions = $this->allPermissions();

        foreach ($permissions as $permissionName) {
            [$module, $action] = str_contains($permissionName, '.')
                ? explode('.', $permissionName, 2)
                : ['_capability', $permissionName];

            Permission::query()->updateOrCreate(
                ['name' => $permissionName, 'guard_name' => $guard],
                ['module' => $module, 'action' => $action],
            );
        }

        foreach (config('erp_rbac.role_templates') as $roleName => $definition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
                ['is_template' => true],
            );

            $permissionIds = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $this->permissionsForRole($definition))
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function allPermissions(): array
    {
        $permissions = [];

        foreach (config('erp_rbac.modules') as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return array_values(array_unique([
            ...$permissions,
            ...config('erp_rbac.sensitive_capabilities'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    private function permissionsForRole(array $definition): array
    {
        if ($definition['all'] ?? false) {
            return array_values(array_diff($this->allPermissions(), $definition['except'] ?? []));
        }

        $permissions = $definition['permissions'] ?? [];

        if ($definition['view_all'] ?? false) {
            $permissions = [...$permissions, ...$this->viewAllPermissions()];
        }

        foreach ($definition['modules_all'] ?? [] as $module) {
            $permissions = [...$permissions, ...$this->modulePermissions($module)];
        }

        foreach ($definition['modules_except'] ?? [] as $module => $excludedActions) {
            foreach (config("erp_rbac.modules.{$module}", []) as $action) {
                if (! in_array($action, $excludedActions, true)) {
                    $permissions[] = "{$module}.{$action}";
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return list<string>
     */
    private function viewAllPermissions(): array
    {
        return array_map(
            static fn (string $module): string => "{$module}.view",
            array_keys(config('erp_rbac.modules')),
        );
    }

    /**
     * @return list<string>
     */
    private function modulePermissions(string $module): array
    {
        return array_map(
            static fn (string $action): string => "{$module}.{$action}",
            config("erp_rbac.modules.{$module}", []),
        );
    }
}
