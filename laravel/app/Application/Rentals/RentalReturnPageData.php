<?php

namespace App\Application\Rentals;

use App\Models\RentalContract;
use App\Models\RentalReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class RentalReturnPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     returns: array,
     *     contracts: EloquentCollection<int, RentalContract>,
     *     statuses: array<int, string>,
     *     conditions: array<int, string>,
     *     outcomes: array<int, string>,
     *     filters: array{search: string, status: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        return [
            'returns' => [],
            'contracts' => RentalContract::query()
                ->with(['customer', 'branch', 'lines.rentableItem'])
                ->where('status', 'active')
                ->latest('created_at')
                ->get(),
            'statuses' => RentalFulfillmentService::RETURN_STATUSES,
            'conditions' => RentalFulfillmentService::CONDITIONS_IN,
            'outcomes' => RentalFulfillmentService::RETURN_OUTCOMES,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = RentalReturn::query()
            ->with(['contract.customer', 'customer', 'branch', 'lines.rentableItem'])
            ->leftJoin('rental_contract as return_contract', 'return_contract.id', '=', 'rental_return.rental_contract_id')
            ->leftJoin('customer as return_customer', 'return_customer.id', '=', 'rental_return.customer_id')
            ->select('rental_return.*')
            ->withSum('lines as damage_total_minor', 'estimated_damage_charge_minor')
            ->when($status !== '' && in_array($status, RentalFulfillmentService::RETURN_STATUSES, true), fn (Builder $query) => $query->where('rental_return.status', $status));

        return DataTables::eloquent($query)
            ->filterColumn('rental_return.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rental_return.number', 'like', "%{$keyword}%")
                        ->orWhere('return_contract.number', 'like', "%{$keyword}%")
                        ->orWhere('return_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(return_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('contract_number', fn (Builder $query, string $keyword) => $query->where('return_contract.number', 'like', "%{$keyword}%"))
            ->orderColumn('contract_number', 'return_contract.number $1')
            ->addColumn('contract_number', fn () => '')
            ->addColumn('items', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
