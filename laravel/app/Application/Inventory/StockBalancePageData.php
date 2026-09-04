<?php

namespace App\Application\Inventory;

use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class StockBalancePageData
{
    public function __construct(
        private readonly InventoryPageOptions $inventoryPageOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     balances: array,
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
            'balances' => [],
            'warehouses' => $this->inventoryPageOptions->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * Server-side DataTables feed for stock balances grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = StockBalance::query()
            ->with(['warehouse.branch', 'product', 'unitOfMeasure'])
            ->leftJoin('warehouse', 'warehouse.id', '=', 'stock_balance.warehouse_id')
            ->leftJoin('product', 'product.id', '=', 'stock_balance.product_id')
            ->select('stock_balance.*')
            ->when($warehouseId, fn ($q) => $q->where('stock_balance.warehouse_id', $warehouseId))
            ->orderBy('warehouse.code')
            ->orderBy('product.code');

        return DataTables::eloquent($query)
            ->filterColumn('product_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('product.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(product.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->addColumn('warehouse_name', fn ($row) => $row->warehouse?->code ?? '')
            ->addColumn('branch_name', fn ($row) => $row->warehouse?->branch?->code ?? '')
            ->addColumn('product_name', fn ($row) => $row->product?->code ?? '')
            ->addColumn('uom_name', fn ($row) => $row->unitOfMeasure?->code ?? '')
            ->editColumn('quantity_e6', fn ($row) => (int) $row->quantity_e6)
            ->editColumn('avg_unit_cost_e6', fn ($row) => (int) $row->avg_unit_cost_e6)
            ->editColumn('valuation_amount_minor', fn ($row) => (int) $row->valuation_amount_minor)
            ->toJson();
    }
}
