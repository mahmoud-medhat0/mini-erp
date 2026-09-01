<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\UserSettingsService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validated();

        $this->userSettingsService->create($validated, $request->boolean('is_active', true), (int) $request->user()->id);

        return back()->with('success', __('User created successfully.'));
    }

    public function update(UpdateUserRequest $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validated();

        $this->userSettingsService->update(
            $userId,
            $validated,
            $request->has('is_active'),
            $request->boolean('is_active', true),
            $request->boolean('replace_role') && $request->has('role_id'),
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
