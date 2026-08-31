<?php

namespace App\Application\Rentals;

use App\Models\Currency;
use App\Models\RentalContract;
use App\Models\RentalInvoice;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RentalInvoicePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, invoice_type?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
            'invoice_type' => (string) ($filters['invoice_type'] ?? ''),
        ];

        return [
            'invoices' => $this->invoices($normalizedFilters),
            'contracts' => $this->billableContracts(),
            'currencies' => $this->currencies(),
            'taxCodes' => $this->activeTaxCodes(),
            'statuses' => RentalInvoiceService::STATUSES,
            'invoiceTypes' => RentalInvoiceService::INVOICE_TYPES,
            'lineTypes' => RentalInvoiceService::LINE_TYPES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: string, status: string, invoice_type: string}  $filters
     */
    private function invoices(array $filters): LengthAwarePaginator
    {
        return RentalInvoice::query()
            ->with([
                'contract.customer',
                'contract.branch',
                'customer',
                'branch',
                'journalEntry',
                'receivableEntry',
                'lines.contractLine.rentableItem',
                'lines.rentalReturn',
                'lines.rentalReturnLine.rentableItem',
                'lines.taxCode',
            ])
            ->when($filters['status'] !== '' && in_array($filters['status'], RentalInvoiceService::STATUSES, true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['invoice_type'] !== '' && in_array($filters['invoice_type'], RentalInvoiceService::INVOICE_TYPES, true), fn (Builder $query) => $query->where('invoice_type', $filters['invoice_type']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('number', 'like', "%{$filters['search']}%")
                        ->orWhere('reference', 'like', "%{$filters['search']}%")
                        ->orWhereHas('contract', fn (Builder $contract) => $contract->where('number', 'like', "%{$filters['search']}%"))
                        ->orWhereHas('customer', function (Builder $customer) use ($filters): void {
                            $customer->where('code', 'like', "%{$filters['search']}%")
                                ->orWhere('name->en', 'like', "%{$filters['search']}%")
                                ->orWhere('name->ar', 'like', "%{$filters['search']}%");
                        });
                });
            })
            ->latest('invoice_date')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return Collection<int, RentalContract>
     */
    private function billableContracts(): Collection
    {
        return RentalContract::query()
            ->with([
                'customer',
                'branch',
                'lines.rentableItem',
                'lines.invoiceLines.invoice',
                'returns' => fn ($query) => $query->where('status', 'completed')->with(['lines.rentableItem', 'lines.invoiceLines.invoice']),
            ])
            ->whereIn('status', ['approved', 'active', 'completed'])
            ->orderBy('number')
            ->get();
    }

    /**
     * @return Collection<int, Currency>
     */
    private function currencies(): Collection
    {
        return Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']);
    }

    /**
     * @return Collection<int, TaxCode>
     */
    private function activeTaxCodes(): Collection
    {
        return TaxCode::query()
            ->with(['rates' => fn ($query) => $query->where('is_active', true)->orderByDesc('effective_from')])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'calculation_mode', 'recoverability_mode']);
    }
}
