<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Yajra\DataTables\Facades\DataTables;

class JournalPageData
{
    /**
     * The grid streams its rows from {@see self::datatable()}; the index page
     * itself no longer needs a paginated `journals` collection.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     periods: EloquentCollection<int, FinancialPeriod>,
     *     filters: array<string, mixed>
     * }
     */
    public function indexData(array $filters): array
    {
        return [
            'periods' => FinancialPeriod::query()
                ->with('fiscalYear:id,year')
                ->orderBy('start_date', 'desc')
                ->get(['id', 'month', 'status', 'start_date', 'end_date', 'fiscal_year_id']),
            'filters' => Arr::only($filters, ['status', 'period_id', 'start_date', 'end_date', 'branch_id']),
        ];
    }

    /**
     * Server-side DataTables feed for the general journal grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $periodId = (string) ($filters['period_id'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');
        $startDate = (string) ($filters['start_date'] ?? '');
        $endDate = (string) ($filters['end_date'] ?? '');

        $query = JournalEntry::query()
            ->with(['createdBy:id,name'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($periodId !== '', fn ($q) => $q->where('financial_period_id', $periodId))
            ->when($branchId !== '', function ($q) use ($branchId): void {
                $q->where(function ($branchQuery) use ($branchId): void {
                    $branchQuery->where('branch_id', $branchId)
                        ->orWhereHas('lines', fn ($lineQuery) => $lineQuery->where('branch_id', $branchId));
                });
            })
            ->when($startDate !== '', fn ($q) => $q->where('entry_date', '>=', $startDate))
            ->when($endDate !== '', fn ($q) => $q->where('entry_date', '<=', $endDate))
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at');

        return DataTables::eloquent($query)
            ->filterColumn('number', function ($q, $keyword): void {
                $needle = '%'.$keyword.'%';
                $q->where(function ($inner) use ($needle): void {
                    $inner->where('number', 'like', $needle)
                        ->orWhere('description', 'like', $needle)
                        ->orWhere('reference', 'like', $needle);
                });
            })
            ->addColumn('creator_name', fn ($row) => $row->createdBy?->name)
            ->toJson();
    }

    /**
     * @return array{
     *     periods: EloquentCollection<int, FinancialPeriod>,
     *     accounts: EloquentCollection<int, Account>,
     *     currencies: EloquentCollection<int, Currency>,
     *     branches: EloquentCollection<int, Branch>,
     *     projects: EloquentCollection<int, Project>,
     *     costCenters: EloquentCollection<int, CostCenter>
     * }
     */
    public function createData(): array
    {
        return [
            'periods' => $this->openPeriods(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'projects' => Project::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
        ];
    }

    /**
     * @return array{
     *     journal: JournalEntry,
     *     openPeriods: EloquentCollection<int, FinancialPeriod>
     * }
     */
    public function showData(JournalEntry $journalEntry): array
    {
        $journalEntry->load([
            'branch',
            'lines.account',
            'lines.branch',
            'lines.project',
            'lines.costCenter',
            'period.fiscalYear',
            'currencyRef',
            'createdBy',
            'postedBy',
            'reversesEntry',
            'reversalEntry',
        ]);

        return [
            'journal' => $journalEntry,
            'openPeriods' => $this->openPeriods(),
        ];
    }

    /**
     * @return EloquentCollection<int, FinancialPeriod>
     */
    private function openPeriods(): EloquentCollection
    {
        return FinancialPeriod::query()
            ->with('fiscalYear')
            ->whereIn('status', ['open', 'reopened'])
            ->orderBy('start_date', 'desc')
            ->get();
    }
}
