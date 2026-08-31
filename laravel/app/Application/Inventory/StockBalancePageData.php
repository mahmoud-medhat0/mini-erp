<?php

namespace App\Application\Inventory;

use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockBalancePageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     balances: LengthAwarePaginator,
     *     warehouses: Collection<int, Warehouse>,
     *     filters: array{warehouse_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];

        return [
            'balances' => $this->balances($normalizedFilters),
            'warehouses' => $this->inventoryPageOptions->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{warehouse_id: mixed}  $filters
     */
    private function balances(array $filters): LengthAwarePaginator
    {
        return StockBalance::query()
            ->with(['warehouse.branch', 'product', 'unitOfMeasure'])
            ->when($filters['warehouse_id'], fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->paginate(30)
            ->withQueryString();
    }
}
