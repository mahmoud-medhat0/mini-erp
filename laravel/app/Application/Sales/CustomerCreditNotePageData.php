<?php

namespace App\Application\Sales;

use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\SalesReturn;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class CustomerCreditNotePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, customer_id?: mixed}  $filters
     * @return array{
     *     customerCreditNotes: array,
     *     activeCustomers: Collection<int, Customer>,
     *     postedCustomerInvoices: Collection<int, CustomerInvoice>,
     *     postedSalesReturns: Collection<int, SalesReturn>,
     *     taxCodes: Collection<int, TaxCode>,
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
            'customerCreditNotes' => [],
            'activeCustomers' => $this->activeCustomers(),
            'postedCustomerInvoices' => $this->postedCustomerInvoices(),
            'postedSalesReturns' => $this->postedSalesReturns(),
            'taxCodes' => $this->activeTaxCodes(),
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

        $query = CustomerCreditNote::query()
            ->with(['customer', 'customerInvoice', 'salesReturn', 'lines', 'journalEntry', 'receivableEntry'])
            ->leftJoin('customer as credit_customer', 'credit_customer.id', '=', 'customer_credit_note.customer_id')
            ->leftJoin('customer_invoice as credited_invoice', 'credited_invoice.id', '=', 'customer_credit_note.customer_invoice_id')
            ->leftJoin('sales_return as credited_return', 'credited_return.id', '=', 'customer_credit_note.sales_return_id')
            ->select('customer_credit_note.*')
            ->when($status && in_array($status, CustomerCreditNoteService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('customer_credit_note.status', $status))
            ->when($customerId, fn (Builder $query) => $query->where('customer_credit_note.customer_id', $customerId));

        return DataTables::eloquent($query)
            ->filterColumn('customer_credit_note.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('customer_credit_note.number', 'like', "%{$keyword}%")
                        ->orWhere('customer_credit_note.reason', 'like', "%{$keyword}%")
                        ->orWhere('customer_credit_note.notes', 'like', "%{$keyword}%")
                        ->orWhere('credit_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(credit_customer.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhere('credited_invoice.number', 'like', "%{$keyword}%")
                        ->orWhere('credited_return.number', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('credit_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(credit_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('invoice_number', fn (Builder $query, string $keyword) => $query->where('credited_invoice.number', 'like', "%{$keyword}%"))
            ->filterColumn('sales_return_number', fn (Builder $query, string $keyword) => $query->where('credited_return.number', 'like', "%{$keyword}%"))
            ->orderColumn('customer_name', 'credit_customer.code $1')
            ->orderColumn('invoice_number', 'credited_invoice.number $1')
            ->orderColumn('sales_return_number', 'credited_return.number $1')
            ->addColumn('customer_name', fn (CustomerCreditNote $row) => $row->customer?->name ?? '')
            ->addColumn('invoice_number', fn (CustomerCreditNote $row) => $row->customerInvoice?->number ?? '')
            ->addColumn('sales_return_number', fn (CustomerCreditNote $row) => $row->salesReturn?->number ?? '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * @return Collection<int, Customer>
     */
    private function activeCustomers(): Collection
    {
        return Customer::query()->where('status', 'active')->orderBy('code', 'asc')->get();
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
     * @return Collection<int, SalesReturn>
     */
    private function postedSalesReturns(): Collection
    {
        return SalesReturn::query()
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
}
