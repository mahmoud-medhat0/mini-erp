<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
            'supplierBills' => $this->supplierBills($normalizedFilters),
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
    private function supplierBills(array $filters): LengthAwarePaginator
    {
        $query = SupplierBill::query()->with([
            'supplier',
            'purchaseOrder',
            'goodsReceipt',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
            'payableEntry',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('supplier_reference', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($filters): void {
                        $supplierQuery->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], SupplierBillService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
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
            ->with(['supplier', 'purchaseOrder', 'lines.product', 'lines.unitOfMeasure', 'lines.purchaseOrderLine'])
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
