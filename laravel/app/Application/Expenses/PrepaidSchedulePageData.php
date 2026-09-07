<?php

namespace App\Application\Expenses;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\PrepaidRecognition;
use App\Models\PrepaidSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PrepaidSchedulePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     schedules: array,
     *     summary: array{total_schedules: int, pending_recognitions: int},
     *     categories: EloquentCollection<int, ExpenseCategory>,
     *     prepaidAssetAccounts: EloquentCollection<int, Account>,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     branches: EloquentCollection<int, Branch>,
     *     currencies: EloquentCollection<int, Currency>,
     *     statuses: array<int, string>,
     *     filters: array{search: string, status: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'schedules' => [],
            'summary' => $this->summary($normalizedFilters),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'prepaidAssetAccounts' => $this->prepaidAssetAccounts(),
            'expenseAccounts' => $this->expenseAccounts(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => PrepaidScheduleService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = $this->filteredQuery($this->normalizeFilters($filters), false)
            ->with(['branch', 'category', 'prepaidAssetAccount', 'expenseAccount', 'recognitions.period', 'recognitions.journalEntry']);

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $this->applySearch($query, $keyword);
            })
            ->addColumn('branch_name', fn (PrepaidSchedule $row) => $row->branch?->code ?? '')
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
        return PrepaidSchedule::query()
            ->when(
                $filters['status'] !== '' && in_array($filters['status'], PrepaidScheduleService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when($filters['branch_id'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->when($includeSearch && $filters['search'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['search']));
    }

    private function applySearch(Builder $query, string $keyword): void
    {
        $needle = '%'.mb_strtolower($keyword).'%';

        $query->where(function (Builder $inner) use ($needle): void {
            $inner->whereRaw('LOWER(CAST(prepaid_schedule.number AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(prepaid_schedule.reference AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(prepaid_schedule.description AS TEXT)) LIKE ?', [$needle])
                ->orWhereHas('category', function (Builder $categoryQuery) use ($needle): void {
                    $categoryQuery->whereRaw('LOWER(CAST(expense_category.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(expense_category.code AS TEXT)) LIKE ?', [$needle]);
                })
                ->orWhereHas('branch', function (Builder $branchQuery) use ($needle): void {
                    $branchQuery->whereRaw('LOWER(CAST(branch.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(branch.code AS TEXT)) LIKE ?', [$needle]);
                });
        });
    }

    /**
     * @param  array{search: string, status: string, branch_id: string}  $filters
     * @return array{total_schedules: int, pending_recognitions: int}
     */
    private function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);

        return [
            'total_schedules' => (clone $query)->count(),
            'pending_recognitions' => PrepaidRecognition::query()
                ->where('status', 'pending')
                ->whereIn('prepaid_schedule_id', (clone $query)->select('id'))
                ->count(),
        ];
    }

    /**
     * @return EloquentCollection<int, Account>
     */
    private function prepaidAssetAccounts(): EloquentCollection
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where('nature', 'debit')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency']);
    }

    /**
     * @return EloquentCollection<int, Account>
     */
    private function expenseAccounts(): EloquentCollection
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
