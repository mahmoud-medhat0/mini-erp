<?php

namespace App\Application\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\LandedCostAllocation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class LandedCostAllocationPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     landedCosts: array,
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
            'landedCosts' => [],
            'summary' => $this->summary($normalizedFilters),
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
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = LandedCostAllocation::query()
            ->with([
                'supplier',
                'goodsReceipt.purchaseOrder.supplier',
                'goodsReceipt.warehouse.branch',
                'lines.product',
                'lines.unitOfMeasure',
                'journalEntry',
                'payableEntry',
            ])
            ->when($status && in_array($status, LandedCostAllocationService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('status', $status));

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('number', 'like', "%{$keyword}%")
                        ->orWhere('reference', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($keyword, $needle): void {
                            $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                                ->orWhere('code', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('goodsReceipt', fn (Builder $receiptQuery) => $receiptQuery->where('number', 'like', "%{$keyword}%"));
                });
            })
            ->addColumn('supplier_name', fn (LandedCostAllocation $row) => $row->supplier?->code ?? '')
            ->addColumn('receipt_number', fn (LandedCostAllocation $row) => $row->goodsReceipt?->number ?? '')
            ->addColumn('warehouse_name', fn (LandedCostAllocation $row) => $row->goodsReceipt?->warehouse?->code ?? '')
            ->addColumn('actions', fn () => '')
            ->toJson();
    }

    /**
     * @param  array{search: mixed, status: mixed}  $filters
     * @return array{posted_count: int, pipeline_count: int, total_amount_minor: int}
     */
    private function summary(array $filters): array
    {
        $query = LandedCostAllocation::query()
            ->when(
                $filters['status'] && in_array($filters['status'], LandedCostAllocationService::ALLOWED_STATUSES, true),
                fn (Builder $builder) => $builder->where('status', $filters['status'])
            );

        return [
            'posted_count' => (clone $query)->where('status', 'posted')->count(),
            'pipeline_count' => (clone $query)->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
            'total_amount_minor' => (int) (clone $query)->sum('total_amount_minor'),
        ];
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
