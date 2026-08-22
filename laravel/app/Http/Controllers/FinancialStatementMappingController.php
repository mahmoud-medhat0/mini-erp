<?php

namespace App\Http\Controllers;

use App\Application\Accounting\FinancialStatementMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialStatementMappingController extends Controller
{
    public function __construct(
        private FinancialStatementMappingService $mappingService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('accounting.mappings');

        $data = $this->mappingService->getMappingData();

        return Inertia::render('Accounting/FinancialStatementMappings', [
            'lines' => $data['lines'],
            'unmappedAccounts' => $data['unmapped_accounts'],
            'statementTypes' => [
                ['value' => 'balance_sheet'],
                ['value' => 'income_statement'],
            ],
            'sectionOptions' => [
                ['value' => 'current_assets'],
                ['value' => 'non_current_assets'],
                ['value' => 'current_liabilities'],
                ['value' => 'non_current_liabilities'],
                ['value' => 'equity'],
                ['value' => 'revenue'],
                ['value' => 'contra_revenue'],
                ['value' => 'cogs'],
                ['value' => 'operating_expenses'],
                ['value' => 'other_income'],
                ['value' => 'other_expenses'],
            ],
            'normalBalances' => [
                ['value' => 'debit'],
                ['value' => 'credit'],
            ],
        ]);
    }

    public function storeLine(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'statement_type' => ['required', 'string', 'in:balance_sheet,income_statement'],
            'section_code' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['required', 'string', 'in:debit,credit'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = [
            'en' => $validated['name_en'],
            'ar' => $validated['name_ar'] ?: $validated['name_en'],
        ];

        $this->mappingService->createStatementLine([
            'code' => $validated['code'],
            'statement_type' => $validated['statement_type'],
            'section_code' => $validated['section_code'],
            'name' => $name,
            'normal_balance' => $validated['normal_balance'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ], (int) $request->user()?->id);

        return redirect()->back()->with('success', __('Statement line created successfully.'));
    }

    public function updateLine(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('accounting.mappings');

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'statement_type' => ['nullable', 'string', 'in:balance_sheet,income_statement'],
            'section_code' => ['nullable', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['nullable', 'string', 'in:debit,credit'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = [];
        if (! empty($validated['code'])) {
            $payload['code'] = $validated['code'];
        }
        if (! empty($validated['statement_type'])) {
            $payload['statement_type'] = $validated['statement_type'];
        }
        if (! empty($validated['section_code'])) {
            $payload['section_code'] = $validated['section_code'];
        }
        if (! empty($validated['normal_balance'])) {
            $payload['normal_balance'] = $validated['normal_balance'];
        }
        if (isset($validated['sort_order'])) {
            $payload['sort_order'] = (int) $validated['sort_order'];
        }
        if (isset($validated['is_active'])) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }
        if (! empty($validated['name_en'])) {
            $payload['name'] = [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'] ?: $validated['name_en'],
            ];
        }

        $this->mappingService->updateStatementLine($id, $payload, (int) $request->user()?->id);

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
}
