<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\BranchSettingsService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly BranchSettingsService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Branches', $this->service->indexData($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:branch,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->service->create($validated, $request->boolean('is_active', true), $request->user()->id);

        return back()->with('success', __('Branch saved.'));
    }

    public function update(Request $request, string $branchId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branch', 'code')->ignore($branchId)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->service->update(
            $branchId,
            $validated,
            $request->has('is_active') ? $request->boolean('is_active') : null,
            $request->user()->id
        );

        return back()->with('success', __('Branch saved.'));
    }

    public function destroy(Request $request, string $branchId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $this->service->delete($branchId, $request->user()->id);

        return back()->with('success', __('Branch deleted successfully.'));
    }
}
