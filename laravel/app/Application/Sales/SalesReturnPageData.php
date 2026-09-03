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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesReturnPageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, customer_id?: mixed, warehouse_id?: mixed}  $filters
     * @return array{
     *     salesReturns: LengthAwarePaginator,
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
            'salesReturns' => $this->salesReturns($normalizedFilters),
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
    private function salesReturns(array $filters): LengthAwarePaginator
    {
        $query = SalesReturn::query()->with([
            'customer',
            'deliveryNote',
            'warehouse',
            'customerInvoice',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $search = (string) $filters['search'];
                $query->where('number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                        $customerQuery->where('code', 'like', "%{$search}%")
                            ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', ['%'.mb_strtolower($search).'%']);
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], SalesReturnService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['customer_id']) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if ($filters['warehouse_id']) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
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
