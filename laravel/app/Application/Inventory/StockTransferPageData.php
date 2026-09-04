<?php

namespace App\Application\Inventory;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class StockTransferPageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     transfers: array,
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
            'transfers' => [],
            'warehouses' => $this->inventoryPageOptions->activeWarehouses(),
            'products' => $this->inventoryPageOptions->stockProducts(),
            'statuses' => StockTransferService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * Server-side DataTables feed for stock transfers grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = StockTransfer::query()
            ->with(['sourceWarehouse.branch', 'destinationWarehouse.branch', 'lines.product', 'lines.unitOfMeasure', 'receipts.lines'])
            ->when($status && in_array($status, StockTransferService::ALLOWED_STATUSES, true), fn ($q) => $q->where('stock_transfer.status', $status))
            ->when($warehouseId, function (Builder $query) use ($warehouseId): void {
                $query->where(function (Builder $inner) use ($warehouseId): void {
                    $inner->where('stock_transfer.source_warehouse_id', $warehouseId)
                        ->orWhere('stock_transfer.destination_warehouse_id', $warehouseId);
                });
            })
            ->orderBy('stock_transfer.transfer_date', 'desc')
            ->orderBy('stock_transfer.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('number', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('stock_transfer.number', 'like', "%{$keyword}%")
                        ->orWhere('stock_transfer.reference', 'like', "%{$keyword}%")
                        ->orWhere('stock_transfer.reason', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('source_warehouse_name', fn ($row) => $row->sourceWarehouse?->code ?? '')
            ->addColumn('destination_warehouse_name', fn ($row) => $row->destinationWarehouse?->code ?? '')
            ->addColumn('lines_data', fn ($row) => (string) ($row->lines?->count() ?? 0))
            ->addColumn('actions', fn ($row) => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
