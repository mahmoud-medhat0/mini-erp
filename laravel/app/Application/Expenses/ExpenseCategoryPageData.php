<?php

namespace App\Application\Expenses;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ExpenseCategoryPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     categories: array<int, never>,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     taxCodes: EloquentCollection<int, TaxCode>,
     *     filters: array{search: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return [
            'categories' => [],
            'expenseAccounts' => $this->expenseAccountOptions(),
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'calculation_mode', 'recoverability_mode']),
            'filters' => ['search' => $search],
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = ExpenseCategory::query()
            ->with(['defaultExpenseAccount', 'defaultTaxCode'])
            ->select('expense_category.*')
            ->withCount('expenseLines');

        return DataTables::eloquent($query)
            ->addColumn('name_text', fn (ExpenseCategory $category): string => (string) $category->name)
            ->addColumn('default_expense_account_text', fn (): string => '')
            ->addColumn('default_tax_code_text', fn (): string => '')
            ->addColumn('actions', fn (): string => '')
            ->filterColumn('name_text', function ($builder, string $keyword): void {
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('expense_category.name->en', 'like', "%{$keyword}%")
                        ->orWhere('expense_category.name->ar', 'like', "%{$keyword}%");
                });
            })
            ->toJson();
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
