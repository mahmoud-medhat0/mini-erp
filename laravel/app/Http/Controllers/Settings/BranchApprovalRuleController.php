<?php

namespace App\Http\Controllers\Settings;

use App\Application\Approvals\BranchApprovalRuleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchApprovalRuleController extends Controller
{
    public function __construct(private readonly BranchApprovalRuleService $service) {}

    public function index(): Response
    {
        return Inertia::render('Settings/BranchApprovalRules', $this->service->indexData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($this->validated($request), $request->user()?->id);

        return back()->with('success', __('Branch approval rule created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->service->update($id, $this->validated($request), $request->user()?->id);

        return back()->with('success', __('Branch approval rule updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->service->delete($id, $request->user()?->id);

        return back()->with('success', __('Branch approval rule deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'document_type' => ['required', 'string', Rule::in(BranchApprovalRuleService::DOCUMENT_TYPES)],
            'branch_match' => ['required', 'string', Rule::in(BranchApprovalRuleService::BRANCH_MATCHES)],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'required_permission' => ['required', 'string', 'exists:permissions,name'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
