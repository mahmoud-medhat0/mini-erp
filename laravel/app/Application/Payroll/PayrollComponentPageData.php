<?php

namespace App\Application\Payroll;

use App\Models\Account;
use App\Models\PayrollComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PayrollComponentPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     components: LengthAwarePaginator,
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

        $components = PayrollComponent::query()
            ->with(['expenseAccount', 'liabilityAccount'])
            ->withCount('employeeAssignments')
            ->when($type !== '' && in_array($type, PayrollComponentService::TYPES, true), fn ($query) => $query->where('type', $type))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return [
            'components' => $components,
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
