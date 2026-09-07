<?php

namespace App\Application\Sales;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class CustomerInvoicePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed}  $filters
     * @return array{
     *     customerInvoices: array,
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
            'customerInvoices' => [],
            'activeCustomers' => Customer::query()->where('status', 'active')->orderBy('code', 'asc')->get(),
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
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = CustomerInvoice::query()
            ->with([
                'customer',
                'salesOrder',
                'deliveryNote',
                'lines.product',
                'lines.unitOfMeasure',
                'journalEntry',
                'receivableEntry',
            ])
            ->leftJoin('customer as invoice_customer', 'invoice_customer.id', '=', 'customer_invoice.customer_id')
            ->select('customer_invoice.*')
            ->when($status && in_array($status, CustomerInvoiceService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('customer_invoice.status', $status));

        return DataTables::eloquent($query)
            ->filterColumn('customer_invoice.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('customer_invoice.number', 'like', "%{$keyword}%")
                        ->orWhere('customer_invoice.reference', 'like', "%{$keyword}%")
                        ->orWhere('customer_invoice.description', 'like', "%{$keyword}%")
                        ->orWhere('invoice_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(invoice_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('invoice_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(invoice_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'invoice_customer.code $1')
            ->addColumn('customer_name', fn (CustomerInvoice $row) => $row->customer?->name ?? '')
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
