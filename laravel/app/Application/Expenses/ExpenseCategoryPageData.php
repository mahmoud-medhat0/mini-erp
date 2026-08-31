<?php

namespace App\Application\Expenses;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ExpenseCategoryPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     categories: LengthAwarePaginator,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     taxCodes: EloquentCollection<int, TaxCode>,
     *     filters: array{search: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $categories = ExpenseCategory::query()
            ->with(['defaultExpenseAccount', 'defaultTaxCode'])
            ->withCount('expenseLines')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return [
            'categories' => $categories,
            'expenseAccounts' => $this->expenseAccountOptions(),
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'calculation_mode', 'recoverability_mode']),
            'filters' => ['search' => $search],
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
