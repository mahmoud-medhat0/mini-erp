<?php

namespace App\Application\Inventory;

use App\Models\Currency;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockAdjustmentPageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     adjustments: LengthAwarePaginator,
     *     warehouses: Collection<int, Warehouse>,
     *     products: Collection<int, Product>,
     *     currencies: Collection<int, Currency>,
     *     statuses: array<int, string>,
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
            'adjustments' => $this->adjustments($normalizedFilters),
            'warehouses' => $this->inventoryPageOptions->activeWarehouses(),
            'products' => $this->inventoryPageOptions->stockProducts(),
            'currencies' => $this->inventoryPageOptions->currencies(),
            'statuses' => StockAdjustmentService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    private function adjustments(array $filters): LengthAwarePaginator
    {
        return StockAdjustment::query()
            ->with(['warehouse.branch', 'lines.product', 'lines.unitOfMeasure', 'lines.movement'])
            ->when($filters['search'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('number', 'like', "%{$filters['search']}%")
                        ->orWhere('reference', 'like', "%{$filters['search']}%")
                        ->orWhere('reason', 'like', "%{$filters['search']}%");
                });
            })
            ->when(
                $filters['status'] && in_array($filters['status'], StockAdjustmentService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when($filters['warehouse_id'], fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->orderBy('adjustment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }
}
