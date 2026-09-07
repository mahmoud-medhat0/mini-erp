<?php

namespace App\Application\Rentals;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\RentableItem;
use App\Models\RentalContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class RentalContractPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     contracts: array,
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

        return [
            'contracts' => [],
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

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $customerId = (string) ($filters['customer_id'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');

        $query = RentalContract::query()
            ->with(['customer', 'branch', 'lines.rentableItem'])
            ->leftJoin('customer as rental_customer', 'rental_customer.id', '=', 'rental_contract.customer_id')
            ->leftJoin('branch as rental_branch', 'rental_branch.id', '=', 'rental_contract.branch_id')
            ->select('rental_contract.*')
            ->when($status !== '' && in_array($status, RentalContractService::STATUSES, true), fn (Builder $query) => $query->where('rental_contract.status', $status))
            ->when($customerId !== '', fn (Builder $query) => $query->where('rental_contract.customer_id', $customerId))
            ->when($branchId !== '', fn (Builder $query) => $query->where('rental_contract.branch_id', $branchId));

        return DataTables::eloquent($query)
            ->filterColumn('rental_contract.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rental_contract.number', 'like', "%{$keyword}%")
                        ->orWhere('rental_contract.reference', 'like', "%{$keyword}%")
                        ->orWhere('rental_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(rental_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rental_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(rental_customer.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhere('rental_branch.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(rental_branch.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'rental_customer.code $1')
            ->addColumn('customer_name', fn () => '')
            ->addColumn('period', fn () => '')
            ->addColumn('items', fn () => '')
            ->addColumn('totals', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
