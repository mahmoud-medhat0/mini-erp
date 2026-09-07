<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class GoodsReceiptPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, warehouse_id?: mixed}  $filters
     * @return array{
     *     goodsReceipts: array,
     *     confirmedPurchaseOrders: Collection<int, PurchaseOrder>,
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
            'goodsReceipts' => [],
            'confirmedPurchaseOrders' => $this->confirmedPurchaseOrders(),
            'warehouses' => $this->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = GoodsReceipt::query()->with([
            'purchaseOrder.supplier',
            'warehouse',
            'lines.product',
            'lines.unitOfMeasure',
        ])
            ->when($status && in_array($status, GoodsReceiptService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId));

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('number', 'like', "%{$keyword}%")
                        ->orWhere('reference', 'like', "%{$keyword}%")
                        ->orWhere('notes', 'like', "%{$keyword}%")
                        ->orWhereHas('warehouse', fn (Builder $warehouseQuery) => $warehouseQuery->where('code', 'like', "%{$keyword}%")->orWhereRaw('LOWER(CAST(warehouse.name AS TEXT)) LIKE ?', [$needle]))
                        ->orWhereHas('purchaseOrder', function (Builder $purchaseOrderQuery) use ($keyword, $needle): void {
                            $purchaseOrderQuery->where('number', 'like', "%{$keyword}%")
                                ->orWhereHas('supplier', function (Builder $supplierQuery) use ($keyword, $needle): void {
                                    $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                                        ->orWhere('code', 'like', "%{$keyword}%");
                                });
                        });
                });
            })
            ->addColumn('purchase_order_number', fn (GoodsReceipt $row) => $row->purchaseOrder?->number ?? '')
            ->addColumn('supplier_name', fn (GoodsReceipt $row) => $row->purchaseOrder?->supplier?->code ?? '')
            ->addColumn('warehouse_name', fn (GoodsReceipt $row) => $row->warehouse?->code ?? '')
            ->addColumn('lines_count', fn (GoodsReceipt $row) => $row->lines->count())
            ->addColumn('actions', fn () => '')
            ->toJson();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    private function confirmedPurchaseOrders(): Collection
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'lines.product', 'lines.unitOfMeasure'])
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
