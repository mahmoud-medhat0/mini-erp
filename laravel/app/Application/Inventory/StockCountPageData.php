<?php

namespace App\Application\Inventory;

use App\Models\Currency;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockCountPageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     stockCounts: LengthAwarePaginator,
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
            'stockCounts' => $this->stockCounts($normalizedFilters),
            'warehouses' => $this->inventoryPageOptions->activeWarehouses(),
            'products' => $this->inventoryPageOptions->stockProducts(),
            'currencies' => $this->inventoryPageOptions->currencies(),
            'statuses' => StockCountService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    private function stockCounts(array $filters): LengthAwarePaginator
    {
        return StockCount::query()
            ->with(['warehouse.branch', 'adjustment', 'lines.product', 'lines.unitOfMeasure'])
            ->when($filters['search'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('number', 'like', "%{$filters['search']}%")
                        ->orWhere('reference', 'like', "%{$filters['search']}%")
                        ->orWhere('notes', 'like', "%{$filters['search']}%");
                });
            })
            ->when(
                $filters['status'] && in_array($filters['status'], StockCountService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when($filters['warehouse_id'], fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->orderBy('count_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }
}
