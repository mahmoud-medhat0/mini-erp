<?php

namespace App\Application\Sales;

use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\SalesReturn;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerCreditNotePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, customer_id?: mixed}  $filters
     * @return array{
     *     customerCreditNotes: LengthAwarePaginator,
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
            'customerCreditNotes' => $this->customerCreditNotes($normalizedFilters),
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
    private function customerCreditNotes(array $filters): LengthAwarePaginator
    {
        $query = CustomerCreditNote::query()->with([
            'customer',
            'customerInvoice',
            'salesReturn',
            'lines',
            'journalEntry',
            'receivableEntry',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('reason', 'like', "%{$filters['search']}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($filters): void {
                        $customerQuery->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], CustomerCreditNoteService::ALLOWED_STATUSES, true)) {
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
     * @return Collection<int, Customer>
     */
    private function activeCustomers(): Collection
    {
        return Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get();
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
