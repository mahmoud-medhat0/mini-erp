<?php

namespace App\Application\Expenses;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ExpensePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     expenses: LengthAwarePaginator,
     *     categories: EloquentCollection<int, ExpenseCategory>,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     suppliers: EloquentCollection<int, Supplier>,
     *     cashAccounts: EloquentCollection<int, CashAccount>,
     *     bankAccounts: EloquentCollection<int, BankAccount>,
     *     branches: EloquentCollection<int, Branch>,
     *     currencies: EloquentCollection<int, Currency>,
     *     taxCodes: EloquentCollection<int, TaxCode>,
     *     statuses: array<int, string>,
     *     settlementMethods: array<int, string>,
     *     filters: array{search: string, status: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));
        $branchId = (string) ($filters['branch_id'] ?? '');

        $expenses = Expense::query()
            ->with(['branch', 'supplier', 'cashAccount', 'bankAccount', 'lines.category', 'lines.expenseAccount'])
            ->when($status !== '' && in_array($status, ExpenseService::ALLOWED_STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('payee_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->where('is_active', true)->with(['defaultExpenseAccount', 'defaultTaxCode'])->orderBy('code')->get(),
            'expenseAccounts' => $this->expenseAccountOptions(),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->with('branch')->orderBy('code')->get(['id', 'code', 'name', 'branch_id', 'currency', 'gl_account_id']),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->with('branch')->orderBy('code')->get(['id', 'code', 'name', 'branch_id', 'currency', 'gl_account_id']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'calculation_mode', 'recoverability_mode']),
            'statuses' => ExpenseService::ALLOWED_STATUSES,
            'settlementMethods' => ExpenseService::SETTLEMENT_METHODS,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
        ];
    }

    /**
     * @return EloquentCollection<int, Account>
     */
    private function expenseAccountOptions(): EloquentCollection
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'expense')
            ->where('nature', 'debit')
            ->where('is_control', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency']);
    }
}
