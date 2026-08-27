<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\UserSettingsService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(
        private readonly UserSettingsService $userSettingsService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Users', $this->userSettingsService->indexData());
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

        $this->userSettingsService->create($validated, $request->boolean('is_active', true), (int) $request->user()->id);

        return back()->with('success', __('User created successfully.'));
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $this->userSettingsService->update(
            $userId,
            $validated,
            $request->has('is_active'),
            $request->boolean('is_active', true),
            $request->has('role_id'),
            $request->filled('password'),
            (int) $request->user()->id
        );

        return back()->with('success', __('User updated successfully.'));
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $this->userSettingsService->delete($userId, (int) $request->user()->id);

        return back()->with('success', __('User deleted successfully.'));
    }
}
