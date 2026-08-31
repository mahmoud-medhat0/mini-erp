<?php

namespace App\Http\Controllers;

use App\Application\Accounting\FinancialStatementMappingPageData;
use App\Application\Accounting\FinancialStatementMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialStatementMappingController extends Controller
{
    public function __construct(
        private FinancialStatementMappingService $mappingService,
        private FinancialStatementMappingPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('accounting.mappings');

        return Inertia::render('Accounting/FinancialStatementMappings', $this->pageData->indexData());
    }

    public function storeLine(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'statement_type' => ['required', 'string', 'in:balance_sheet,income_statement'],
            'cash_flow_activity' => ['nullable', 'string', 'in:operating,investing,financing'],
            'section_code' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['required', 'string', 'in:debit,credit'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->mappingService->createStatementLine(
            $this->pageData->createLinePayload($validated),
            (int) $request->user()?->id
        );

        return redirect()->back()->with('success', __('Statement line created successfully.'));
    }

    public function updateLine(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'statement_type' => ['nullable', 'string', 'in:balance_sheet,income_statement'],
            'cash_flow_activity' => ['nullable', 'string', 'in:operating,investing,financing'],
            'section_code' => ['nullable', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['nullable', 'string', 'in:debit,credit'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->mappingService->updateStatementLine(
            $id,
            $this->pageData->updateLinePayload($validated),
            (int) $request->user()?->id
        );

        return redirect()->back()->with('success', __('Statement line updated successfully.'));
    }

    public function destroyLine(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $this->mappingService->deleteStatementLine($id, (int) $request->user()?->id);

        return redirect()->back()->with('success', __('Statement line deleted successfully.'));
    }

    public function assign(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'account_id' => ['required', 'uuid', 'exists:account,id'],
            'financial_statement_line_id' => ['nullable', 'uuid', 'exists:financial_statement_line,id'],
        ]);

        $this->mappingService->assignAccount(
            $validated['account_id'],
            $validated['financial_statement_line_id'] ?? null,
            (int) $request->user()?->id
        );

        return redirect()->back()->with('success', __('Account mapped successfully.'));
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.account_id' => ['required', 'uuid', 'exists:account,id'],
            'assignments.*.financial_statement_line_id' => ['nullable', 'uuid', 'exists:financial_statement_line,id'],
        ]);

        $this->mappingService->bulkAssignAccounts(
            $validated['assignments'],
            (int) $request->user()?->id
        );

        return redirect()->back()->with('success', __('Bulk account mappings updated successfully.'));
    }

    public function updateAccountCashFlow(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'account_id' => ['required', 'uuid', 'exists:account,id'],
            'cash_flow_activity' => ['nullable', 'string', 'in:operating,investing,financing'],
        ]);

        $this->mappingService->updateAccountCashFlowActivity(
            $validated['account_id'],
            $validated['cash_flow_activity'] ?? null,
            (int) $request->user()?->id
        );

        return redirect()->back()->with('success', __('Account cash flow activity updated successfully.'));
    }
}
