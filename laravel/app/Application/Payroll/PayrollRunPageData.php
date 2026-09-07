<?php

namespace App\Application\Payroll;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PayrollRunPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     runs: array,
     *     summary: array{total_runs: int, employee_count: int, gross_minor: int, net_minor: int, currency: ?string, has_mixed_currencies: bool},
     *     periods: EloquentCollection<int, PayrollPeriod>,
     *     branches: EloquentCollection<int, Branch>,
     *     currencies: EloquentCollection<int, Currency>,
     *     statuses: array<int, string>,
     *     runTypes: array<int, string>,
     *     filters: array{search: string, status: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'runs' => [],
            'summary' => $this->summary($normalizedFilters),
            'periods' => PayrollPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(['id', 'year', 'month', 'start_date', 'end_date', 'payment_date', 'status']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => PayrollRunService::STATUSES,
            'runTypes' => PayrollRunService::RUN_TYPES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = $this->filteredQuery($this->normalizeFilters($filters), false)
            ->with(['period', 'branch', 'journalEntry', 'lines.employee', 'lines.components']);

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $this->applySearch($query, $keyword);
            })
            ->addColumn('period_label', fn (PayrollRun $row) => $row->period ? sprintf('%d-%02d', $row->period->year, $row->period->month) : '')
            ->addColumn('branch_name', fn (PayrollRun $row) => $row->branch?->code ?? '')
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
        return PayrollRun::query()
            ->when(
                $filters['status'] !== '' && in_array($filters['status'], PayrollRunService::STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when($filters['branch_id'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->when($includeSearch && $filters['search'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['search']));
    }

    private function applySearch(Builder $query, string $keyword): void
    {
        $needle = '%'.mb_strtolower($keyword).'%';

        $query->where(function (Builder $inner) use ($needle): void {
            $inner->whereRaw('LOWER(CAST(payroll_run.number AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(payroll_run.reference AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(payroll_run.description AS TEXT)) LIKE ?', [$needle])
                ->orWhereHas('branch', function (Builder $branchQuery) use ($needle): void {
                    $branchQuery->whereRaw('LOWER(CAST(branch.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(branch.code AS TEXT)) LIKE ?', [$needle]);
                });
        });
    }

    /**
     * @param  array{search: string, status: string, branch_id: string}  $filters
     * @return array{total_runs: int, employee_count: int, gross_minor: int, net_minor: int, currency: ?string, has_mixed_currencies: bool}
     */
    private function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $totals = (clone $query)
            ->selectRaw('COUNT(*) AS total_runs')
            ->selectRaw('COALESCE(SUM(employee_count), 0) AS employee_count')
            ->selectRaw('COALESCE(SUM(gross_minor), 0) AS gross_minor')
            ->selectRaw('COALESCE(SUM(net_minor), 0) AS net_minor')
            ->first();
        $currencies = (clone $query)->whereNotNull('currency')->distinct()->limit(2)->pluck('currency');

        return [
            'total_runs' => (int) ($totals?->total_runs ?? 0),
            'employee_count' => (int) ($totals?->employee_count ?? 0),
            'gross_minor' => (int) ($totals?->gross_minor ?? 0),
            'net_minor' => (int) ($totals?->net_minor ?? 0),
            'currency' => $currencies->count() === 1 ? (string) $currencies->first() : null,
            'has_mixed_currencies' => $currencies->count() > 1,
        ];
    }
}
