<?php

namespace App\Application\Sales;

use App\Models\Customer;
use App\Models\CustomerCreditNoteLine;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\DeliveryNote;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\TaxCode;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class SalesReturnPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, customer_id?: mixed, warehouse_id?: mixed}  $filters
     * @return array{
     *     salesReturns: array,
     *     activeCustomers: Collection<int, Customer>,
     *     confirmedDeliveryNotes: Collection<int, DeliveryNote>,
     *     postedCustomerInvoices: Collection<int, CustomerInvoice>,
     *     taxCodes: Collection<int, TaxCode>,
     *     warehouses: Collection<int, Warehouse>,
     *     filters: array{search: mixed, status: mixed, customer_id: mixed, warehouse_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'customer_id' => $filters['customer_id'] ?? null,
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];

        return [
            'salesReturns' => [],
            'activeCustomers' => $this->activeCustomers(),
            'confirmedDeliveryNotes' => $this->confirmedDeliveryNotes(),
            'postedCustomerInvoices' => $this->postedCustomerInvoices(),
            'taxCodes' => $this->activeTaxCodes(),
            'warehouses' => $this->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array{
     *     invoice: array{id: string, number: string|null, currency: string, customer: array{id: string, name: string}|null},
     *     lines: list<array{
     *         id: string,
     *         description: string|null,
     *         original_quantity_e6: int,
     *         returned_quantity_e6: int,
     *         credited_quantity_e6: int,
     *         max_returnable_quantity_e6: int,
     *         unit_price_minor: int
     *     }>
     * }
     */
    public function returnableInvoiceLines(string $invoiceId): array
    {
        /** @var CustomerInvoice|null $invoice */
        $invoice = CustomerInvoice::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('id', $invoiceId)
            ->first();

        abort_unless($invoice && $invoice->status === 'posted', 404);

        $lines = $invoice->lines->map(function (CustomerInvoiceLine $line): array {
            $returnedFromSalesReturnsE6 = (int) SalesReturnLine::query()
                ->where('customer_invoice_line_id', $line->id)
                ->whereHas('salesReturn', fn (Builder $query) => $query->where('status', 'posted'))
                ->sum('quantity_e6');

            $returnedFromCreditNotesE6 = (int) CustomerCreditNoteLine::query()
                ->where('customer_invoice_line_id', $line->id)
                ->whereHas('customerCreditNote', fn (Builder $query) => $query->where('status', 'posted'))
                ->sum('quantity_e6');

            $originalQuantityE6 = (int) $line->quantity_e6;

            return [
                'id' => $line->id,
                'description' => $line->description,
                'original_quantity_e6' => $originalQuantityE6,
                'returned_quantity_e6' => $returnedFromSalesReturnsE6,
                'credited_quantity_e6' => $returnedFromCreditNotesE6,
                'max_returnable_quantity_e6' => max(0, $originalQuantityE6 - $returnedFromSalesReturnsE6 - $returnedFromCreditNotesE6),
                'unit_price_minor' => (int) $line->unit_price_minor,
            ];
        })->values()->all();

        return [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'currency' => $invoice->currency,
                'customer' => $invoice->customer?->only(['id', 'name']),
            ],
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, customer_id: mixed, warehouse_id: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $customerId = (string) ($filters['customer_id'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = SalesReturn::query()
            ->with([
                'customer',
                'deliveryNote',
                'warehouse',
                'customerInvoice',
                'lines.product',
                'lines.unitOfMeasure',
                'journalEntry',
            ])
            ->leftJoin('customer as return_customer', 'return_customer.id', '=', 'sales_return.customer_id')
            ->leftJoin('delivery_note as returned_delivery', 'returned_delivery.id', '=', 'sales_return.delivery_note_id')
            ->leftJoin('customer_invoice as returned_invoice', 'returned_invoice.id', '=', 'sales_return.customer_invoice_id')
            ->leftJoin('warehouse as return_warehouse', 'return_warehouse.id', '=', 'sales_return.warehouse_id')
            ->select('sales_return.*')
            ->when($status && in_array($status, SalesReturnService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('sales_return.status', $status))
            ->when($customerId, fn (Builder $query) => $query->where('sales_return.customer_id', $customerId))
            ->when($warehouseId, fn (Builder $query) => $query->where('sales_return.warehouse_id', $warehouseId));

        return DataTables::eloquent($query)
            ->filterColumn('sales_return.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('sales_return.number', 'like', "%{$keyword}%")
                        ->orWhere('sales_return.reason', 'like', "%{$keyword}%")
                        ->orWhere('sales_return.notes', 'like', "%{$keyword}%")
                        ->orWhere('return_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(return_customer.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhere('returned_delivery.number', 'like', "%{$keyword}%")
                        ->orWhere('returned_invoice.number', 'like', "%{$keyword}%")
                        ->orWhere('return_warehouse.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(return_warehouse.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('return_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(return_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('delivery_note_number', fn (Builder $query, string $keyword) => $query->where('returned_delivery.number', 'like', "%{$keyword}%"))
            ->filterColumn('invoice_number', fn (Builder $query, string $keyword) => $query->where('returned_invoice.number', 'like', "%{$keyword}%"))
            ->filterColumn('warehouse_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('return_warehouse.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(return_warehouse.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'return_customer.code $1')
            ->orderColumn('delivery_note_number', 'returned_delivery.number $1')
            ->orderColumn('invoice_number', 'returned_invoice.number $1')
            ->orderColumn('warehouse_name', 'return_warehouse.code $1')
            ->addColumn('customer_name', fn (SalesReturn $row) => $row->customer?->name ?? '')
            ->addColumn('delivery_note_number', fn (SalesReturn $row) => $row->deliveryNote?->number ?? '')
            ->addColumn('invoice_number', fn (SalesReturn $row) => $row->customerInvoice?->number ?? '')
            ->addColumn('warehouse_name', fn (SalesReturn $row) => $row->warehouse?->name ?? '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * @return Collection<int, Customer>
     */
    private function activeCustomers(): Collection
    {
        return Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get();
    }

    /**
     * @return Collection<int, DeliveryNote>
     */
    private function confirmedDeliveryNotes(): Collection
    {
        return DeliveryNote::query()
            ->with(['salesOrder.customer', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, CustomerInvoice>
     */
    private function postedCustomerInvoices(): Collection
    {
        return CustomerInvoice::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'posted')
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
