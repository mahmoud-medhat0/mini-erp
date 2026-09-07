<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class PurchaseReturnPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, supplier_id?: mixed, warehouse_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'supplier_id' => $filters['supplier_id'] ?? null,
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];

        return [
            'purchaseReturns' => [],
            'activeSuppliers' => $this->activeSuppliers(),
            'confirmedGoodsReceipts' => $this->confirmedGoodsReceipts(),
            'taxCodes' => $this->activeTaxCodes(),
            'warehouses' => $this->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, supplier_id: mixed, warehouse_id: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $supplierId = (string) ($filters['supplier_id'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = PurchaseReturn::query()->with([
            'supplier',
            'goodsReceipt.purchaseOrder.supplier',
            'warehouse',
            'supplierBill',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
        ])
            ->when($status && in_array($status, PurchaseReturnService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId));

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('number', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%")
                        ->orWhere('notes', 'like', "%{$keyword}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($keyword, $needle): void {
                            $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                                ->orWhere('code', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('goodsReceipt', fn (Builder $receiptQuery) => $receiptQuery->where('number', 'like', "%{$keyword}%"))
                        ->orWhereHas('warehouse', fn (Builder $warehouseQuery) => $warehouseQuery->where('code', 'like', "%{$keyword}%")->orWhereRaw('LOWER(CAST(warehouse.name AS TEXT)) LIKE ?', [$needle]));
                });
            })
            ->addColumn('supplier_name', fn (PurchaseReturn $row) => $row->supplier?->code ?? '')
            ->addColumn('receipt_number', fn (PurchaseReturn $row) => $row->goodsReceipt?->number ?? '')
            ->addColumn('warehouse_name', fn (PurchaseReturn $row) => $row->warehouse?->code ?? '')
            ->addColumn('actions', fn () => '')
            ->toJson();
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function activeSuppliers(): Collection
    {
        return Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get();
    }

    /**
     * @return Collection<int, GoodsReceipt>
     */
    private function confirmedGoodsReceipts(): Collection
    {
        return GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure', 'lines.purchaseOrderLine'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, TaxCode>
     */
    private function activeTaxCodes(): Collection
    {
        return TaxCode::query()->where('is_active', true)->orderBy('code', 'asc')->get();
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
