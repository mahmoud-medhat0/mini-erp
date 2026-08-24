<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'is_template' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $this->auditLogger->record($request->user()->id, 'role.create', 'role', (string) $role->id, after: $validated);

        return back()->with('success', __('Role created successfully.'));
    }

    public function update(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $role = Role::query()->findOrFail($roleId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $role->toArray();
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        $this->auditLogger->record($request->user()->id, 'role.update', 'role', (string) $role->id, before: $before, after: $role->toArray());

        return back()->with('success', __('Role updated successfully.'));
    }

    public function destroy(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $role = Role::query()->findOrFail($roleId);

        abort_if($role->is_template, 403, 'Template roles cannot be deleted.');

        $before = $role->toArray();
        $role->delete();

        $this->auditLogger->record($request->user()->id, 'role.delete', 'role', (string) $roleId, before: $before);

        return back()->with('success', __('Role deleted successfully.'));
    }
}
