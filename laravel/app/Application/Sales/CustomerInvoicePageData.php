<?php

namespace App\Application\Sales;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerInvoicePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed}  $filters
     * @return array{
     *     customerInvoices: LengthAwarePaginator,
     *     activeCustomers: Collection<int, Customer>,
     *     eligibleProducts: Collection<int, Product>,
     *     confirmedSalesOrders: Collection<int, SalesOrder>,
     *     confirmedDeliveryNotes: Collection<int, DeliveryNote>,
     *     taxCodes: Collection<int, TaxCode>,
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
            'customerInvoices' => $this->customerInvoices($normalizedFilters),
            'activeCustomers' => Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get(),
            'eligibleProducts' => $this->eligibleProducts(),
            'confirmedSalesOrders' => $this->confirmedSalesOrders(),
            'confirmedDeliveryNotes' => $this->confirmedDeliveryNotes(),
            'taxCodes' => $this->activeTaxCodes(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed}  $filters
     */
    private function customerInvoices(array $filters): LengthAwarePaginator
    {
        $query = CustomerInvoice::query()->with([
            'customer',
            'salesOrder',
            'deliveryNote',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
            'receivableEntry',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($filters): void {
                        $customerQuery->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], CustomerInvoiceService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
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
            ->whereIn('type', ['service', 'non_stock'])
            ->orderBy('code', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, SalesOrder>
     */
    private function confirmedSalesOrders(): Collection
    {
        return SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, DeliveryNote>
     */
    private function confirmedDeliveryNotes(): Collection
    {
        return DeliveryNote::query()
            ->with(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, TaxCode>
     */
    private function activeTaxCodes(): Collection
    {
        return TaxCode::query()
            ->with(['rates' => fn ($query) => $query->where('is_active', true)->orderBy('effective_from', 'desc')])
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();
    }
}
