<?php

namespace App\Application\Rentals;

use App\Models\RentalContract;
use App\Models\RentalHandover;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class RentalHandoverPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     handovers: LengthAwarePaginator,
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

        $handovers = RentalHandover::query()
            ->with(['contract.customer', 'customer', 'branch', 'lines.rentableItem'])
            ->when($status !== '' && in_array($status, RentalFulfillmentService::HANDOVER_STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('contract', fn ($contract) => $contract->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($customer) => $customer->where('code', 'like', "%{$search}%")
                            ->orWhere('name->en', 'like', "%{$search}%")
                            ->orWhere('name->ar', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'handovers' => $handovers,
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
}
