<?php

namespace App\Http\Controllers;

use App\Application\Expenses\ExpensePageData;
use App\Application\Expenses\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ExpensePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Expenses/Index', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->expenseService->create($this->validatedExpense($request), $request->user()?->id);

        return back()->with('success', __('Expense saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->expenseService->update($id, $this->validatedExpense($request, true), $request->user()?->id);

        return back()->with('success', __('Expense updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->expenseService->submit($id, $request->user()?->id);

        return back()->with('success', __('Expense submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->expenseService->approve($id, $request->user()?->id);

        return back()->with('success', __('Expense approved.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->expenseService->post($id, $request->user()?->id);

        return back()->with('success', __('Expense posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->expenseService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Expense cancelled.'));
    }

    private function validatedExpense(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'expense_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:supplier,id'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'settlement_method' => [$isUpdate ? 'sometimes' : 'required', Rule::in(ExpenseService::SETTLEMENT_METHODS)],
            'cash_account_id' => ['nullable', 'uuid', 'exists:cash_account,id'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:bank_account,id'],
            'currency' => [$isUpdate ? 'sometimes' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
            'lines' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'lines.*.expense_category_id' => ['required', 'uuid', 'exists:expense_category,id'],
            'lines.*.expense_account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity_e6' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_amount_minor' => ['required', 'integer', 'min:1'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
        ]);
    }
}
