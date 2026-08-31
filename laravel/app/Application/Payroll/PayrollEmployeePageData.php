<?php

namespace App\Application\Payroll;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\PayrollComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PayrollEmployeePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     employees: LengthAwarePaginator,
     *     branches: EloquentCollection<int, Branch>,
     *     currencies: EloquentCollection<int, Currency>,
     *     components: EloquentCollection<int, PayrollComponent>,
     *     statuses: array<int, string>,
     *     paymentMethods: array<int, string>,
     *     filters: array{search: string, status: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));
        $branchId = (string) ($filters['branch_id'] ?? '');

        $employees = Employee::query()
            ->with(['branch', 'componentAssignments.component'])
            ->when($status !== '' && in_array($status, EmployeeService::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
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
            'employees' => $employees,
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'components' => PayrollComponent::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name', 'type', 'calculation_type']),
            'statuses' => EmployeeService::STATUSES,
            'paymentMethods' => EmployeeService::PAYMENT_METHODS,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
        ];
    }
}
