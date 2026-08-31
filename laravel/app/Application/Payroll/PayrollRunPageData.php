<?php

namespace App\Application\Payroll;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PayrollRunPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     runs: LengthAwarePaginator,
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
        $status = (string) ($filters['status'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));

        $runs = PayrollRun::query()
            ->with(['period', 'branch', 'journalEntry', 'lines.employee', 'lines.components'])
            ->when($status !== '' && in_array($status, PayrollRunService::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('payroll_date')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return [
            'runs' => $runs,
            'periods' => PayrollPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(['id', 'year', 'month', 'start_date', 'end_date', 'payment_date', 'status']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => PayrollRunService::STATUSES,
            'runTypes' => PayrollRunService::RUN_TYPES,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
        ];
    }
}
