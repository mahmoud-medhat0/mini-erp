<?php

namespace App\Application\Payroll;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\PayrollEmployeeLoan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PayrollEmployeeLoanPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'loans' => [],
            'employees' => Employee::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'currency']),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'currency']),
            'loanTypes' => PayrollEmployeeLoanService::LOAN_TYPES,
            'disbursementMethods' => PayrollEmployeeLoanService::DISBURSEMENT_METHODS,
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
                'employee_id' => (string) ($filters['employee_id'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $employeeId = (string) ($filters['employee_id'] ?? '');

        $query = PayrollEmployeeLoan::query()
            ->with(['employee'])
            ->select('payroll_employee_loan.*')
            ->when($status !== '', fn (Builder $q) => $q->where('payroll_employee_loan.status', $status))
            ->when($employeeId !== '', fn (Builder $q) => $q->where('payroll_employee_loan.employee_id', $employeeId));

        return DataTables::eloquent($query)
            ->addColumn('employee', fn () => '')
            ->addColumn('paid_minor', fn (PayrollEmployeeLoan $loan) => (int) $loan->principal_minor - (int) $loan->remaining_balance_minor)
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
