<?php

namespace App\Application\Purchasing;

use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class PurchaseOrderPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, supplier_id?: mixed}  $filters
     * @return array{
     *     purchaseOrders: array,
     *     suppliers: Collection<int, Supplier>,
     *     currencies: Collection<int, Currency>,
     *     products: Collection<int, Product>,
     *     filters: array{search: mixed, status: mixed, supplier_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'supplier_id' => $filters['supplier_id'] ?? null,
        ];

        return [
            'purchaseOrders' => [],
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code', 'asc')->get(),
            'products' => $this->eligibleProducts(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, supplier_id: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $supplierId = (string) ($filters['supplier_id'] ?? '');

        $query = PurchaseOrder::query()
            ->with(['supplier', 'lines.product', 'lines.unitOfMeasure'])
            ->when($status && in_array($status, PurchaseOrderService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId));

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('number', 'like', "%{$keyword}%")
                        ->orWhere('reference', 'like', "%{$keyword}%")
                        ->orWhere('notes', 'like', "%{$keyword}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($keyword, $needle): void {
                            $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                                ->orWhere('code', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('supplier_name', fn (PurchaseOrder $row) => $row->supplier?->code ?? '')
            ->addColumn('lines_count', fn (PurchaseOrder $row) => $row->lines->count())
            ->addColumn('actions', fn () => '')
            ->toJson();
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
            ->orderBy('code', 'asc')
            ->get();
    }
}
