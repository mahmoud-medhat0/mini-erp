<?php

namespace App\Application\Rentals;

use App\Models\RentalContract;
use App\Models\RentalHandover;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class RentalHandoverPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     handovers: array,
     *     contracts: EloquentCollection<int, RentalContract>,
     *     statuses: array<int, string>,
     *     conditions: array<int, string>,
     *     filters: array{search: string, status: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        return [
            'handovers' => [],
            'contracts' => RentalContract::query()
                ->with(['customer', 'branch', 'lines.rentableItem'])
                ->whereIn('status', ['approved', 'active'])
                ->latest('created_at')
                ->get(),
            'statuses' => RentalFulfillmentService::HANDOVER_STATUSES,
            'conditions' => RentalFulfillmentService::CONDITIONS_OUT,
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

        $query = RentalHandover::query()
            ->with(['contract.customer', 'customer', 'branch', 'lines.rentableItem'])
            ->leftJoin('rental_contract as handover_contract', 'handover_contract.id', '=', 'rental_handover.rental_contract_id')
            ->leftJoin('customer as handover_customer', 'handover_customer.id', '=', 'rental_handover.customer_id')
            ->select('rental_handover.*')
            ->when($status !== '' && in_array($status, RentalFulfillmentService::HANDOVER_STATUSES, true), fn (Builder $query) => $query->where('rental_handover.status', $status));

        return DataTables::eloquent($query)
            ->filterColumn('rental_handover.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rental_handover.number', 'like', "%{$keyword}%")
                        ->orWhere('handover_contract.number', 'like', "%{$keyword}%")
                        ->orWhere('handover_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(handover_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('contract_number', fn (Builder $query, string $keyword) => $query->where('handover_contract.number', 'like', "%{$keyword}%"))
            ->orderColumn('contract_number', 'handover_contract.number $1')
            ->addColumn('contract_number', fn () => '')
            ->addColumn('items', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
