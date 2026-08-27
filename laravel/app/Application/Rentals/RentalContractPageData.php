<?php

namespace App\Application\Rentals;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\RentableItem;
use App\Models\RentalContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class RentalContractPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     contracts: LengthAwarePaginator,
     *     customers: EloquentCollection<int, Customer>,
     *     branches: EloquentCollection<int, Branch>,
     *     rentableItems: EloquentCollection<int, RentableItem>,
     *     currencies: EloquentCollection<int, Currency>,
     *     statuses: array<int, string>,
     *     billingCycles: array<int, string>,
     *     rateTypes: array<int, string>,
     *     filters: array{search: string, status: string, customer_id: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $customerId = (string) ($filters['customer_id'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');

        $contracts = RentalContract::query()
            ->with(['customer', 'branch', 'lines.rentableItem'])
            ->when($status !== '' && in_array($status, RentalContractService::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($customerId !== '', fn ($query) => $query->where('customer_id', $customerId))
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customer) use ($search): void {
                            $customer->where('code', 'like', "%{$search}%")
                                ->orWhere('name->en', 'like', "%{$search}%")
                                ->orWhere('name->ar', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'contracts' => $contracts,
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'rentableItems' => RentableItem::query()
                ->with(['branch', 'warehouse'])
                ->where('is_active', true)
                ->whereIn('status', ['available', 'returned'])
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'status', 'currency', 'branch_id', 'warehouse_id', 'daily_rate_minor', 'monthly_rate_minor', 'deposit_minor']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => RentalContractService::STATUSES,
            'billingCycles' => RentalContractService::BILLING_CYCLES,
            'rateTypes' => RentalContractService::RATE_TYPES,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'customer_id' => $customerId,
                'branch_id' => $branchId,
            ],
        ];
    }
}
