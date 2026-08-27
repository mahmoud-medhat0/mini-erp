<?php

namespace App\Application\Sales;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesOrderPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, customer_id?: mixed}  $filters
     * @return array{
     *     salesOrders: LengthAwarePaginator,
     *     customers: Collection<int, Customer>,
     *     currencies: Collection<int, Currency>,
     *     products: Collection<int, Product>,
     *     filters: array{search: mixed, status: mixed, customer_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'customer_id' => $filters['customer_id'] ?? null,
        ];

        return [
            'salesOrders' => $this->salesOrders($normalizedFilters),
            'customers' => Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code', 'asc')->get(),
            'products' => $this->eligibleProducts(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, customer_id: mixed}  $filters
     */
    private function salesOrders(array $filters): LengthAwarePaginator
    {
        $query = SalesOrder::query()->with(['customer', 'lines.product', 'lines.unitOfMeasure']);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($filters): void {
                        $customerQuery->where('name', 'like', "%{$filters['search']}%")
                            ->orWhere('code', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], SalesOrderService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['customer_id']) {
            $query->where('customer_id', $filters['customer_id']);
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
            ->where('is_sales_enabled', true)
            ->orderBy('code', 'asc')
            ->get();
    }
}
