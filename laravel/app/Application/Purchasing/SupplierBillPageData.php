<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class SupplierBillPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
        ];

        return [
            'supplierBills' => [],
            'activeSuppliers' => $this->activeSuppliers(),
            'eligibleProducts' => $this->eligibleProducts(),
            'confirmedPurchaseOrders' => $this->confirmedPurchaseOrders(),
            'confirmedGoodsReceipts' => $this->confirmedGoodsReceipts(),
            'taxCodes' => $this->activeTaxCodes(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = SupplierBill::query()->with([
            'supplier',
            'purchaseOrder',
            'goodsReceipt',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
            'payableEntry',
        ])->when($status && in_array($status, SupplierBillService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('status', $status));

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('number', 'like', "%{$keyword}%")
                        ->orWhere('supplier_reference', 'like', "%{$keyword}%")
                        ->orWhere('reference', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($keyword, $needle): void {
                            $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                                ->orWhere('code', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('purchaseOrder', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->where('number', 'like', "%{$keyword}%"))
                        ->orWhereHas('goodsReceipt', fn (Builder $receiptQuery) => $receiptQuery->where('number', 'like', "%{$keyword}%"));
                });
            })
            ->addColumn('supplier_name', fn (SupplierBill $row) => $row->supplier?->code ?? '')
            ->addColumn('source_number', fn (SupplierBill $row) => $row->goodsReceipt?->number ?? $row->purchaseOrder?->number ?? '')
            ->addColumn('lines_count', fn (SupplierBill $row) => $row->lines->count())
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
     * @return Collection<int, Product>
     */
    private function eligibleProducts(): Collection
    {
        return Product::query()
            ->with('unitOfMeasure')
            ->where('status', 'active')
            ->where('is_purchase_enabled', true)
            ->whereIn('type', ['service', 'non_stock'])
            ->orderBy('code', 'asc')
            ->get();
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
     * @return Collection<int, GoodsReceipt>
     */
    private function confirmedGoodsReceipts(): Collection
    {
        return GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'lines.product', 'lines.unitOfMeasure', 'lines.purchaseOrderLine'])
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
}
