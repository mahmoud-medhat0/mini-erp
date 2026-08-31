<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\RoleSettingsService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly RoleSettingsService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->service->create($validated, $request->user()->id);

        return back()->with('success', __('Role created successfully.'));
    }

    public function update(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->service->update($roleId, $validated, $request->user()->id);

        return back()->with('success', __('Role updated successfully.'));
    }

    public function destroy(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $this->service->delete($roleId, $request->user()->id);

        return back()->with('success', __('Role deleted successfully.'));
    }
}
