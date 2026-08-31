<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
            'purchaseReturns' => $this->purchaseReturns($normalizedFilters),
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
    private function purchaseReturns(array $filters): LengthAwarePaginator
    {
        $query = PurchaseReturn::query()->with([
            'supplier',
            'goodsReceipt.purchaseOrder.supplier',
            'warehouse',
            'supplierBill',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reason', 'like', "%{$filters['search']}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($filters): void {
                        $supplierQuery->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], PurchaseReturnService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if ($filters['warehouse_id']) {
            $query->where('warehouse_id', $filters['warehouse_id']);
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
