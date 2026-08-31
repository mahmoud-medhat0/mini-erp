<?php

namespace App\Application\Inventory;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockTransferPageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     transfers: LengthAwarePaginator,
     *     warehouses: Collection<int, Warehouse>,
     *     products: Collection<int, Product>,
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
            'transfers' => $this->transfers($normalizedFilters),
            'warehouses' => $this->inventoryPageOptions->activeWarehouses(),
            'products' => $this->inventoryPageOptions->stockProducts(),
            'statuses' => StockTransferService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    private function transfers(array $filters): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->with(['sourceWarehouse.branch', 'destinationWarehouse.branch', 'lines.product', 'lines.unitOfMeasure', 'receipts.lines'])
            ->when($filters['search'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('number', 'like', "%{$filters['search']}%")
                        ->orWhere('reference', 'like', "%{$filters['search']}%")
                        ->orWhere('reason', 'like', "%{$filters['search']}%");
                });
            })
            ->when(
                $filters['status'] && in_array($filters['status'], StockTransferService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when($filters['warehouse_id'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('source_warehouse_id', $filters['warehouse_id'])
                        ->orWhere('destination_warehouse_id', $filters['warehouse_id']);
                });
            })
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }
}
