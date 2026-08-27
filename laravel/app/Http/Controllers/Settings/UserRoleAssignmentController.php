<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\UserRoleAssignmentService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleAssignmentController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly UserRoleAssignmentService $service) {}

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $this->service->assign($validated, $request->user()->id);

        return back()->with('success', __('Role assigned.'));
    }

    public function revoke(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $this->service->revoke($validated, $request->user()->id);

        return back()->with('success', __('Role revoked.'));
    }
}
