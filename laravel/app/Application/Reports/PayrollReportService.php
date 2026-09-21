<?php

namespace App\Application\Reports;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

/**
 * Phase 28 - a dedicated analytical Payroll report under /reports, separate
 * from the operational /payroll/runs screen (PHASE_25_GAP_CLOSURE_DECISION_PACK.md
 * §7). Read-only; reuses the already-posted payroll_run_line rows.
 */
class PayrollReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'employees' => Employee::query()->orderBy('code')->get(['id', 'code', 'name']),
            'runs' => PayrollRun::query()->where('status', 'posted')->orderByDesc('payroll_date')->get(['id', 'number', 'payroll_date']),
            'filters' => [
                'employee_id' => (string) ($filters['employee_id'] ?? ''),
                'payroll_run_id' => (string) ($filters['payroll_run_id'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $employeeId = (string) ($filters['employee_id'] ?? '');
        $payrollRunId = (string) ($filters['payroll_run_id'] ?? '');

        $query = PayrollRunLine::query()
            ->with(['employee', 'run'])
            ->whereHas('run', fn (Builder $q) => $q->where('status', 'posted'))
            ->select('payroll_run_line.*')
            ->when($employeeId !== '', fn (Builder $q) => $q->where('payroll_run_line.employee_id', $employeeId))
            ->when($payrollRunId !== '', fn (Builder $q) => $q->where('payroll_run_line.payroll_run_id', $payrollRunId));

        return DataTables::eloquent($query)
            ->addColumn('employee', fn () => '')
            ->addColumn('run', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
