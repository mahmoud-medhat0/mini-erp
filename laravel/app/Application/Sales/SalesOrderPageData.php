<?php

namespace App\Application\Sales;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, customer_id?: mixed}  $filters
     * @return array{
     *     salesOrders: array,
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
            'salesOrders' => [],
            'customers' => Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code', 'asc')->get(),
            'products' => $this->eligibleProducts(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, customer_id: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $customerId = (string) ($filters['customer_id'] ?? '');

        $query = SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->leftJoin('customer', 'customer.id', '=', 'sales_order.customer_id')
            ->select('sales_order.*')
            ->when($status && in_array($status, SalesOrderService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('sales_order.status', $status))
            ->when($customerId, fn (Builder $query) => $query->where('sales_order.customer_id', $customerId));

        return DataTables::eloquent($query)
            ->filterColumn('sales_order.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('sales_order.number', 'like', "%{$keyword}%")
                        ->orWhere('sales_order.reference', 'like', "%{$keyword}%")
                        ->orWhere('sales_order.notes', 'like', "%{$keyword}%")
                        ->orWhere('customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'customer.code $1')
            ->addColumn('customer_name', fn (SalesOrder $row) => $row->customer?->name ?? '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
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
            ->where('is_sales_enabled', true)
            ->orderBy('code', 'asc')
            ->get();
    }
}
