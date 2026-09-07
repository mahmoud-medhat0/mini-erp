<?php

namespace App\Application\Payroll;

use App\Models\Account;
use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PayrollComponentPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     components: array<int, never>,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     liabilityAccounts: EloquentCollection<int, Account>,
     *     types: array<int, string>,
     *     calculationTypes: array<int, string>,
     *     filters: array{search: string, type: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $type = (string) ($filters['type'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));

        return [
            'components' => [],
            'expenseAccounts' => $this->expenseAccounts(),
            'liabilityAccounts' => $this->liabilityAccounts(),
            'types' => PayrollComponentService::TYPES,
            'calculationTypes' => PayrollComponentService::CALCULATION_TYPES,
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters): JsonResponse
    {
        $type = (string) ($filters['type'] ?? '');

        $query = PayrollComponent::query()
            ->with(['expenseAccount', 'liabilityAccount'])
            ->select('payroll_component.*')
            ->withCount('employeeAssignments')
            ->when(in_array($type, PayrollComponentService::TYPES, true), fn ($builder) => $builder->where('payroll_component.type', $type));

        return DataTables::eloquent($query)
            ->addColumn('name_text', fn (PayrollComponent $component): string => (string) $component->name)
            ->addColumn('actions', fn (): string => '')
            ->filterColumn('name_text', function ($builder, string $keyword): void {
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('payroll_component.name->en', 'like', "%{$keyword}%")
                        ->orWhere('payroll_component.name->ar', 'like', "%{$keyword}%");
                });
            })
            ->toJson();
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
            ->get(['id', 'code', 'name', 'currency as currency_code']);
    }

    /**
     * @return EloquentCollection<int, Account>
     */
    private function liabilityAccounts(): EloquentCollection
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'liability')
            ->where('nature', 'credit')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency as currency_code']);
    }
}
