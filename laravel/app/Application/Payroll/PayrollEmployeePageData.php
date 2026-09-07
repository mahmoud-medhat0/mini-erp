<?php

namespace App\Application\Payroll;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PayrollEmployeePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     employees: array,
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
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'employees' => [],
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'components' => PayrollComponent::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name', 'type', 'calculation_type']),
            'statuses' => EmployeeService::STATUSES,
            'paymentMethods' => EmployeeService::PAYMENT_METHODS,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $query = Employee::query()
            ->with(['branch', 'componentAssignments.component'])
            ->when(
                $normalizedFilters['status'] !== '' && in_array($normalizedFilters['status'], EmployeeService::STATUSES, true),
                fn (Builder $query) => $query->where('status', $normalizedFilters['status'])
            )
            ->when($normalizedFilters['branch_id'] !== '', fn (Builder $query) => $query->where('branch_id', $normalizedFilters['branch_id']));

        return DataTables::eloquent($query)
            ->filterColumn('code', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($needle): void {
                    $inner->whereRaw('LOWER(CAST(employee.code AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(employee.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($needle): void {
                            $branchQuery->whereRaw('LOWER(CAST(branch.name AS TEXT)) LIKE ?', [$needle])
                                ->orWhereRaw('LOWER(CAST(branch.code AS TEXT)) LIKE ?', [$needle]);
                        });
                });
            })
            ->addColumn('employee_name', fn (Employee $row) => $row->getTranslation('name', app()->getLocale(), false))
            ->addColumn('branch_name', fn (Employee $row) => $row->branch?->code ?? '')
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
}
