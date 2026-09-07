<?php

namespace App\Application\Rentals;

use App\Models\Currency;
use App\Models\RentalContract;
use App\Models\RentalInvoice;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

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
            'invoices' => [],
            'invoiceSummary' => $this->invoiceSummary($normalizedFilters),
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
    public function datatable(array $filters = []): JsonResponse
    {
        $normalizedFilters = [
            'search' => '',
            'status' => (string) ($filters['status'] ?? ''),
            'invoice_type' => (string) ($filters['invoice_type'] ?? ''),
        ];

        $query = $this->filteredInvoiceQuery($normalizedFilters)
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
            ->leftJoin('rental_contract as invoice_contract', 'invoice_contract.id', '=', 'rental_invoice.rental_contract_id')
            ->leftJoin('customer as invoice_customer', 'invoice_customer.id', '=', 'rental_invoice.customer_id')
            ->select('rental_invoice.*');

        return DataTables::eloquent($query)
            ->filterColumn('rental_invoice.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rental_invoice.number', 'like', "%{$keyword}%")
                        ->orWhere('rental_invoice.reference', 'like', "%{$keyword}%")
                        ->orWhere('invoice_contract.number', 'like', "%{$keyword}%")
                        ->orWhere('invoice_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(invoice_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('contract_number', fn (Builder $query, string $keyword) => $query->where('invoice_contract.number', 'like', "%{$keyword}%"))
            ->orderColumn('contract_number', 'invoice_contract.number $1')
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('invoice_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(invoice_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'invoice_customer.code $1')
            ->addColumn('contract_number', fn () => '')
            ->addColumn('customer_name', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * @param  array{search: string, status: string, invoice_type: string}  $filters
     * @return array{total_count: int, open_count: int, posted_count: int, currency: string|null, total_minor: int|null}
     */
    private function invoiceSummary(array $filters): array
    {
        $query = $this->filteredInvoiceQuery($filters);
        $currencyTotals = (clone $query)
            ->selectRaw('currency, SUM(total_minor) as aggregate_total_minor')
            ->groupBy('currency')
            ->get();

        return [
            'total_count' => (clone $query)->count(),
            'open_count' => (clone $query)->whereIn('rental_invoice.status', ['draft', 'submitted', 'approved'])->count(),
            'posted_count' => (clone $query)->where('rental_invoice.status', 'posted')->count(),
            'currency' => $currencyTotals->count() === 1 ? (string) $currencyTotals->first()->currency : null,
            'total_minor' => $currencyTotals->count() === 1 ? (int) $currencyTotals->first()->aggregate_total_minor : null,
        ];
    }

    /** @param  array{search: string, status: string, invoice_type: string}  $filters */
    private function filteredInvoiceQuery(array $filters): Builder
    {
        return RentalInvoice::query()
            ->when($filters['status'] !== '' && in_array($filters['status'], RentalInvoiceService::STATUSES, true), fn (Builder $query) => $query->where('rental_invoice.status', $filters['status']))
            ->when($filters['invoice_type'] !== '' && in_array($filters['invoice_type'], RentalInvoiceService::INVOICE_TYPES, true), fn (Builder $query) => $query->where('rental_invoice.invoice_type', $filters['invoice_type']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('rental_invoice.number', 'like', "%{$filters['search']}%")
                        ->orWhere('rental_invoice.reference', 'like', "%{$filters['search']}%")
                        ->orWhereHas('contract', fn (Builder $contract) => $contract->where('number', 'like', "%{$filters['search']}%"))
                        ->orWhereHas('customer', function (Builder $customer) use ($filters): void {
                            $customer->where('code', 'like', "%{$filters['search']}%")
                                ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', ['%'.mb_strtolower($filters['search']).'%']);
                        });
                });
            });
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
