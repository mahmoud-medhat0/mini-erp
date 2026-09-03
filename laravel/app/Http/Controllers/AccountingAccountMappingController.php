<?php

namespace App\Http\Controllers;

use App\Application\Accounting\AccountingAccountMappingPageData;
use App\Application\Accounting\AccountingAccountMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAccountMappingController extends Controller
{
    public function __construct(
        private readonly AccountingAccountMappingPageData $pageData,
        private readonly AccountingAccountMappingService $mappingService,
    ) {}

    public function index(): Response
    {
        Gate::authorize('accounting.mappings');

        return Inertia::render('Accounting/AccountMappings', $this->pageData->indexData());
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('accounting.mappings');

        return $this->pageData->datatable($request->only(['scope', 'key']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'key' => ['required', 'string', Rule::in(AccountingAccountMappingService::ALLOWED_KEYS)],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'account_id' => ['required', 'uuid', 'exists:account,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->mappingService->setMapping(
            key: $validated['key'],
            accountId: $validated['account_id'],
            description: $validated['description'] ?? null,
            actorId: $request->user()?->id,
            branchId: $validated['branch_id'] ?? null,
        );

        return redirect()->back()->with('success', __('Accounting account mapping saved successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $this->mappingService->deleteBranchMapping($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Branch accounting account mapping removed successfully.'));
    }
}
