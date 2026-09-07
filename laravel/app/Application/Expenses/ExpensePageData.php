<?php

namespace App\Application\Expenses;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ExpensePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     expenses: array,
     *     summary: array{total_minor: int, currency: ?string, has_mixed_currencies: bool, posted_count: int, pipeline_count: int},
     *     categories: EloquentCollection<int, ExpenseCategory>,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     suppliers: EloquentCollection<int, Supplier>,
     *     cashAccounts: EloquentCollection<int, CashAccount>,
     *     bankAccounts: EloquentCollection<int, BankAccount>,
     *     branches: EloquentCollection<int, Branch>,
     *     currencies: EloquentCollection<int, Currency>,
     *     taxCodes: EloquentCollection<int, TaxCode>,
     *     projects: EloquentCollection<int, Project>,
     *     costCenters: EloquentCollection<int, CostCenter>,
     *     statuses: array<int, string>,
     *     settlementMethods: array<int, string>,
     *     filters: array{search: string, status: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'expenses' => [],
            'summary' => $this->summary($normalizedFilters),
            'categories' => ExpenseCategory::query()->where('is_active', true)->with(['defaultExpenseAccount', 'defaultTaxCode'])->orderBy('code')->get(),
            'expenseAccounts' => $this->expenseAccountOptions(),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->with('branch')->orderBy('code')->get(['id', 'code', 'name', 'branch_id', 'currency', 'gl_account_id']),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->with('branch')->orderBy('code')->get(['id', 'code', 'name', 'branch_id', 'currency', 'gl_account_id']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'calculation_mode', 'recoverability_mode']),
            'projects' => Project::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'statuses' => ExpenseService::ALLOWED_STATUSES,
            'settlementMethods' => ExpenseService::SETTLEMENT_METHODS,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = $this->filteredQuery($this->normalizeFilters($filters), false)
            ->with(['branch', 'supplier', 'cashAccount', 'bankAccount', 'lines.category', 'lines.expenseAccount', 'lines.project', 'lines.costCenter']);

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $this->applySearch($query, $keyword);
            })
            ->addColumn('branch_name', fn (Expense $row) => $row->branch?->code ?? '')
            ->addColumn('payee', fn (Expense $row) => $row->supplier?->code ?? $row->payee_name ?? '')
            ->addColumn('actions', fn () => '')
            ->toJson();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{search: string, status: string, branch_id: string}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
            'branch_id' => (string) ($filters['branch_id'] ?? ''),
        ];
    }

    /**
     * @param  array{search: string, status: string, branch_id: string}  $filters
     */
    private function filteredQuery(array $filters, bool $includeSearch = true): Builder
    {
        return Expense::query()
            ->when(
                $filters['status'] !== '' && in_array($filters['status'], ExpenseService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when($filters['branch_id'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->when($includeSearch && $filters['search'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['search']));
    }

    private function applySearch(Builder $query, string $keyword): void
    {
        $needle = '%'.mb_strtolower($keyword).'%';

        $query->where(function (Builder $inner) use ($needle): void {
            $inner->whereRaw('LOWER(CAST(expense.number AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(expense.reference AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(expense.description AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(expense.payee_name AS TEXT)) LIKE ?', [$needle])
                ->orWhereHas('supplier', function (Builder $supplierQuery) use ($needle): void {
                    $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(supplier.code AS TEXT)) LIKE ?', [$needle]);
                })
                ->orWhereHas('branch', function (Builder $branchQuery) use ($needle): void {
                    $branchQuery->whereRaw('LOWER(CAST(branch.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(branch.code AS TEXT)) LIKE ?', [$needle]);
                });
        });
    }

    /**
     * @param  array{search: string, status: string, branch_id: string}  $filters
     * @return array{total_minor: int, currency: ?string, has_mixed_currencies: bool, posted_count: int, pipeline_count: int}
     */
    private function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $currencies = (clone $query)->whereNotNull('currency')->distinct()->limit(2)->pluck('currency');

        return [
            'total_minor' => (int) (clone $query)->sum('total_minor'),
            'currency' => $currencies->count() === 1 ? (string) $currencies->first() : null,
            'has_mixed_currencies' => $currencies->count() > 1,
            'posted_count' => (clone $query)->where('status', 'posted')->count(),
            'pipeline_count' => (clone $query)->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
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
