<?php

namespace App\Application\Inventory;

use App\Models\Currency;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class StockCountPageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     stockCounts: array{},
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
            'stockCounts' => [],
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
    public function datatable(array $filters = []): JsonResponse
    {
        $normalizedFilters = [
            'status' => (string) ($filters['status'] ?? ''),
            'warehouse_id' => (string) ($filters['warehouse_id'] ?? ''),
        ];

        $query = StockCount::query()
            ->with(['warehouse.branch', 'adjustment', 'lines.product', 'lines.unitOfMeasure'])
            ->when(
                $normalizedFilters['status'] !== '' && in_array($normalizedFilters['status'], StockCountService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('stock_count.status', $normalizedFilters['status'])
            )
            ->when($normalizedFilters['warehouse_id'] !== '', fn (Builder $query) => $query->where('stock_count.warehouse_id', $normalizedFilters['warehouse_id']))
            ->orderBy('stock_count.count_date', 'desc')
            ->orderBy('stock_count.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner->where('stock_count.number', 'like', "%{$keyword}%")
                        ->orWhere('stock_count.reference', 'like', "%{$keyword}%")
                        ->orWhere('stock_count.notes', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('warehouse_name', fn (StockCount $row): string => $row->warehouse?->code ?? '')
            ->addColumn('lines_data', fn (StockCount $row): string => (string) ($row->lines?->count() ?? 0))
            ->addColumn('variance_lines', fn (StockCount $row): string => (string) ($row->lines?->where('variance_quantity_e6', '!=', 0)->count() ?? 0))
            ->addColumn('actions', fn (): string => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
