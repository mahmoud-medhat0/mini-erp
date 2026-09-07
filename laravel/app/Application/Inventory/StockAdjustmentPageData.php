<?php

namespace App\Application\Inventory;

use App\Models\Currency;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class StockAdjustmentPageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     adjustments: array{},
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
            'adjustments' => [],
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
    public function datatable(array $filters = []): JsonResponse
    {
        $normalizedFilters = [
            'status' => (string) ($filters['status'] ?? ''),
            'warehouse_id' => (string) ($filters['warehouse_id'] ?? ''),
        ];

        $query = StockAdjustment::query()
            ->with(['warehouse.branch', 'lines.product', 'lines.unitOfMeasure', 'lines.movement'])
            ->when(
                $normalizedFilters['status'] !== '' && in_array($normalizedFilters['status'], StockAdjustmentService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('stock_adjustment.status', $normalizedFilters['status'])
            )
            ->when($normalizedFilters['warehouse_id'] !== '', fn (Builder $query) => $query->where('stock_adjustment.warehouse_id', $normalizedFilters['warehouse_id']))
            ->orderBy('stock_adjustment.adjustment_date', 'desc')
            ->orderBy('stock_adjustment.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner->where('stock_adjustment.number', 'like', "%{$keyword}%")
                        ->orWhere('stock_adjustment.reference', 'like', "%{$keyword}%")
                        ->orWhere('stock_adjustment.reason', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('warehouse_name', fn (StockAdjustment $row): string => $row->warehouse?->code ?? '')
            ->addColumn('lines_data', fn (StockAdjustment $row): string => (string) ($row->lines?->count() ?? 0))
            ->addColumn('actions', fn (): string => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
