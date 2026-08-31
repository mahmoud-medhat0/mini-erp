<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\LandedCostAllocation;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class LandedCostAllocationPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     landedCosts: LengthAwarePaginator,
     *     activeSuppliers: EloquentCollection<int, Supplier>,
     *     confirmedGoodsReceipts: EloquentCollection<int, GoodsReceipt>,
     *     statuses: array<int, string>,
     *     allocationMethods: array<int, string>,
     *     filters: array{search: mixed, status: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
        ];

        return [
            'landedCosts' => $this->landedCosts($normalizedFilters),
            'activeSuppliers' => $this->activeSuppliers(),
            'confirmedGoodsReceipts' => $this->confirmedGoodsReceipts(),
            'statuses' => LandedCostAllocationService::ALLOWED_STATUSES,
            'allocationMethods' => LandedCostAllocationService::ALLOCATION_METHODS,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed}  $filters
     */
    private function landedCosts(array $filters): LengthAwarePaginator
    {
        return LandedCostAllocation::query()
            ->with([
                'supplier',
                'goodsReceipt.purchaseOrder.supplier',
                'goodsReceipt.warehouse.branch',
                'lines.product',
                'lines.unitOfMeasure',
                'journalEntry',
                'payableEntry',
            ])
            ->when($filters['search'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('number', 'like', "%{$filters['search']}%")
                        ->orWhere('reference', 'like', "%{$filters['search']}%")
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$filters['search']}%"))
                        ->orWhereHas('goodsReceipt', fn (Builder $receiptQuery) => $receiptQuery->where('number', 'like', "%{$filters['search']}%"));
                });
            })
            ->when(
                $filters['status'] && in_array($filters['status'], LandedCostAllocationService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->orderBy('allocation_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return EloquentCollection<int, Supplier>
     */
    private function activeSuppliers(): EloquentCollection
    {
        return Supplier::query()
            ->where('status', 'active')
            ->orderBy('name', 'asc')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return EloquentCollection<int, GoodsReceipt>
     */
    private function confirmedGoodsReceipts(): EloquentCollection
    {
        return GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'warehouse.branch', 'lines.product', 'lines.unitOfMeasure', 'lines.purchaseOrderLine'])
            ->where('status', 'confirmed')
            ->whereHas('lines.product', fn (Builder $lineQuery) => $lineQuery->where('type', 'stock'))
            ->orderBy('receipt_date', 'desc')
            ->orderBy('number', 'asc')
            ->get();
    }
}
