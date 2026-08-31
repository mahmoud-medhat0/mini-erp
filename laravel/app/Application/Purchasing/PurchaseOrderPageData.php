<?php

namespace App\Application\Purchasing;

use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseOrderPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, supplier_id?: mixed}  $filters
     * @return array{
     *     purchaseOrders: LengthAwarePaginator,
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
            'purchaseOrders' => $this->purchaseOrders($normalizedFilters),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code', 'asc')->get(),
            'products' => $this->eligibleProducts(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, supplier_id: mixed}  $filters
     */
    private function purchaseOrders(array $filters): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['supplier', 'lines.product', 'lines.unitOfMeasure']);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($filters): void {
                        $supplierQuery->where('name', 'like', "%{$filters['search']}%")
                            ->orWhere('code', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], PurchaseOrderService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
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
