<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GoodsReceiptPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, warehouse_id?: mixed}  $filters
     * @return array{
     *     goodsReceipts: LengthAwarePaginator,
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
            'goodsReceipts' => $this->goodsReceipts($normalizedFilters),
            'confirmedPurchaseOrders' => $this->confirmedPurchaseOrders(),
            'warehouses' => $this->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    private function goodsReceipts(array $filters): LengthAwarePaginator
    {
        $query = GoodsReceipt::query()->with([
            'purchaseOrder.supplier',
            'warehouse',
            'lines.product',
            'lines.unitOfMeasure',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('purchaseOrder', function (Builder $purchaseOrderQuery) use ($filters): void {
                        $purchaseOrderQuery->where('number', 'like', "%{$filters['search']}%")
                            ->orWhereHas('supplier', function (Builder $supplierQuery) use ($filters): void {
                                $supplierQuery->where('name', 'like', "%{$filters['search']}%");
                            });
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], GoodsReceiptService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['warehouse_id']) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
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
