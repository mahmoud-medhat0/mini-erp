<?php

namespace App\Http\Controllers\Payroll;

use App\Application\Payroll\PayrollEmployeeLoanPageData;
use App\Application\Payroll\PayrollEmployeeLoanService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollEmployeeLoanController extends Controller
{
    public function __construct(
        private readonly PayrollEmployeeLoanPageData $pageData,
        private readonly PayrollEmployeeLoanService $loanService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Payroll/Loans', $this->pageData->indexData($request->only(['status', 'employee_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('payroll.view');
        Gate::authorize('view_payroll');

        return $this->pageData->datatable($request->only(['status', 'employee_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'loan_type' => ['required', Rule::in(PayrollEmployeeLoanService::LOAN_TYPES)],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'principal_minor' => ['required', 'integer', 'min:1'],
            'installment_amount_minor' => ['required', 'integer', 'min:1'],
            'disbursement_date' => ['required', 'date'],
            'disbursement_method' => ['required', Rule::in(PayrollEmployeeLoanService::DISBURSEMENT_METHODS)],
            'cash_account_id' => ['nullable', 'uuid'],
            'bank_account_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->loanService->disburse($validated, $request->user()?->id);

        return back()->with('success', __('Employee loan disbursed.'));
    }

    public function settle(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string']]);

        $this->loanService->settleRemaining($id, $validated['reason'], $request->user()?->id);

        return back()->with('success', __('Loan marked as settled.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->loanService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Loan cancelled.'));
    }
}
