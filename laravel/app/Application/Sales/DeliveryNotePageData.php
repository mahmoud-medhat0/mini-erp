<?php

namespace App\Application\Sales;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DeliveryNotePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, warehouse_id?: mixed}  $filters
     * @return array{
     *     deliveryNotes: LengthAwarePaginator,
     *     confirmedSalesOrders: Collection<int, SalesOrder>,
     *     warehouses: Collection<int, Warehouse>,
     *     filters: array{search: mixed, status: mixed, warehouse_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];

        return [
            'deliveryNotes' => $this->deliveryNotes($normalizedFilters),
            'confirmedSalesOrders' => $this->confirmedSalesOrders(),
            'warehouses' => $this->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    private function deliveryNotes(array $filters): LengthAwarePaginator
    {
        $query = DeliveryNote::query()->with([
            'salesOrder.customer',
            'warehouse',
            'lines.product',
            'lines.unitOfMeasure',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('salesOrder', function (Builder $salesOrderQuery) use ($filters): void {
                        $salesOrderQuery->where('number', 'like', "%{$filters['search']}%")
                            ->orWhereHas('customer', function (Builder $customerQuery) use ($filters): void {
                                $customerQuery->where('name', 'like', "%{$filters['search']}%");
                            });
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], DeliveryNoteService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['warehouse_id']) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return Collection<int, SalesOrder>
     */
    private function confirmedSalesOrders(): Collection
    {
        return SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function activeWarehouses(): Collection
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code', 'asc')
            ->get(['id', 'code', 'name', 'is_default']);
    }
}
