<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use Spatie\Permission\Models\Role;

class RoleSettingsService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, int $actorId): Role
    {
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
        $role = Role::findOrFail($roleId);
        $before = $role->toArray();

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        $this->auditLogger->record($actorId, 'role.update', 'role', (string) $role->id, before: $before, after: $role->toArray());

        return $role;
    }

    public function delete(int $roleId, int $actorId): void
    {
        $role = Role::findOrFail($roleId);

        abort_if($role->is_template, 403, __('Template roles cannot be deleted.'));

        $before = $role->toArray();
        $role->delete();

        $this->auditLogger->record($actorId, 'role.delete', 'role', (string) $roleId, before: $before);
    }
}
