<?php

namespace App\Application\Reports;

use App\Models\RentalContract;
use App\Models\RentalContractLine;
use App\Models\RentalInvoice;
use App\Models\RentalInvoiceLine;
use App\Models\RentalReturnLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RentalOperationsReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(array $filters = []): array
    {
        $asOfDate = CarbonImmutable::parse($filters['as_of_date'] ?? now()->toDateString())->toDateString();
        $endingSoonDate = CarbonImmutable::parse($asOfDate)->addDays(14)->toDateString();

        $contracts = RentalContract::query()
            ->with([
                'customer:id,code,name',
                'branch:id,code,name,is_active',
                'lines.rentableItem:id,code,name,status',
                'lines.invoiceLines.invoice:id,status',
                'handovers:id,rental_contract_id,status,handover_date',
                'returns:id,rental_contract_id,status,return_date',
                'returns.lines.invoiceLines.invoice:id,status',
                'invoices.lines',
                'invoices.journalEntry:id,number',
            ])
            ->when($filters['branch_id'] ?? null, fn ($query, string $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['customer_id'] ?? null, fn ($query, string $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->where('expected_end_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->where('start_date', '<=', $dateTo))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name->en', 'like', "%{$search}%")
                                ->orWhere('name->ar', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('expected_end_date')
            ->orderBy('number')
            ->get();

        $rows = $contracts->map(fn (RentalContract $contract): array => $this->contractRow($contract, $asOfDate, $endingSoonDate))->values();
        $currencyCodes = $rows->pluck('currency')->filter()->unique()->sort()->values();
        $baseCurrency = $this->baseCurrency();

        return [
            'as_of_date' => $asOfDate,
            'ending_soon_date' => $endingSoonDate,
            'base_currency' => $baseCurrency,
            'currency_codes' => $currencyCodes->all(),
            'single_currency' => $currencyCodes->count() <= 1,
            'display_currency' => (string) ($currencyCodes->first() ?: $baseCurrency),
            'rows' => $rows->all(),
            'summary' => $this->summary($rows),
            'readiness' => $this->readiness($rows, $currencyCodes),
        ];
    }

    private function contractRow(RentalContract $contract, string $asOfDate, string $endingSoonDate): array
    {
        $activeInvoices = $contract->invoices->reject(fn (RentalInvoice $invoice): bool => $invoice->status === 'cancelled');
        $postedInvoices = $activeInvoices->where('status', 'posted');
        $openInvoices = $activeInvoices->whereIn('status', ['draft', 'submitted', 'approved']);
        $postedLines = $postedInvoices->flatMap(fn (RentalInvoice $invoice): Collection => $invoice->lines);
        $allActiveInvoiceLines = $activeInvoices->flatMap(fn (RentalInvoice $invoice): Collection => $invoice->lines);
        $damagePendingMinor = $this->pendingDamageMinor($contract);
        $unbilledLineCount = $this->unbilledContractLineCount($contract);
        $lineCount = $contract->lines->count();
        $returnedLineCount = $contract->returns
            ->where('status', 'completed')
            ->flatMap(fn ($rentalReturn): Collection => $rentalReturn->lines)
            ->pluck('rental_contract_line_id')
            ->unique()
            ->count();

        return [
            'contract_id' => (string) $contract->id,
            'contract_number' => (string) ($contract->number ?? ''),
            'customer_id' => (string) $contract->customer_id,
            'customer_code' => (string) ($contract->customer?->code ?? ''),
            'customer_name' => $contract->customer?->name,
            'branch_id' => $contract->branch_id ? (string) $contract->branch_id : null,
            'branch_code' => (string) ($contract->branch?->code ?? ''),
            'branch_name' => $contract->branch?->name,
            'status' => (string) $contract->status,
            'due_state' => $this->dueState($contract, $asOfDate, $endingSoonDate),
            'contract_date' => optional($contract->contract_date)->toDateString(),
            'start_date' => optional($contract->start_date)->toDateString(),
            'expected_end_date' => optional($contract->expected_end_date)->toDateString(),
            'actual_end_date' => optional($contract->actual_end_date)->toDateString(),
            'currency' => (string) $contract->currency,
            'billing_cycle' => (string) $contract->billing_cycle,
            'line_count' => $lineCount,
            'confirmed_handover_count' => $contract->handovers->where('status', 'confirmed')->count(),
            'returned_line_count' => $returnedLineCount,
            'open_item_count' => max(0, $lineCount - $returnedLineCount),
            'invoice_count' => $activeInvoices->count(),
            'posted_invoice_count' => $postedInvoices->count(),
            'open_invoice_count' => $openInvoices->count(),
            'estimated_rent_minor' => (int) $contract->estimated_rent_minor,
            'deposit_minor' => (int) $contract->deposit_minor,
            'rent_billed_minor' => $this->sumLineType($postedLines, ['rent']),
            'deposit_billed_minor' => $this->sumLineType($postedLines, ['deposit']),
            'charge_billed_minor' => $this->sumLineType($postedLines, ['damage_charge', 'late_fee', 'other_charge']),
            'tax_billed_minor' => (int) $postedInvoices->sum('tax_amount_minor'),
            'total_billed_minor' => (int) $postedInvoices->sum('total_minor'),
            'open_invoice_total_minor' => (int) $openInvoices->sum('total_minor'),
            'unbilled_line_count' => $unbilledLineCount,
            'pending_damage_minor' => $damagePendingMinor,
            'has_unposted_invoice' => $openInvoices->isNotEmpty(),
            'latest_journal_number' => (string) ($postedInvoices->first()?->journalEntry?->number ?? ''),
            'active_invoice_line_count' => $allActiveInvoiceLines->count(),
        ];
    }

    private function pendingDamageMinor(RentalContract $contract): int
    {
        return (int) $contract->returns
            ->where('status', 'completed')
            ->flatMap(fn ($rentalReturn): Collection => $rentalReturn->lines)
            ->sum(function (RentalReturnLine $line): int {
                $billed = $line->invoiceLines
                    ->filter(fn (RentalInvoiceLine $invoiceLine): bool => $invoiceLine->line_type === 'damage_charge' && $invoiceLine->invoice?->status !== 'cancelled')
                    ->sum('line_total_minor');

                return max(0, (int) $line->estimated_damage_charge_minor - (int) $billed);
            });
    }

    private function unbilledContractLineCount(RentalContract $contract): int
    {
        return $contract->lines
            ->filter(function (RentalContractLine $line): bool {
                return ! $line->invoiceLines
                    ->contains(fn (RentalInvoiceLine $invoiceLine): bool => $invoiceLine->line_type === 'rent' && $invoiceLine->invoice?->status !== 'cancelled');
            })
            ->count();
    }

    private function sumLineType(Collection $lines, array $lineTypes): int
    {
        return (int) $lines
            ->whereIn('line_type', $lineTypes)
            ->sum('line_total_minor');
    }

    private function dueState(RentalContract $contract, string $asOfDate, string $endingSoonDate): string
    {
        if ($contract->status === 'cancelled') {
            return 'cancelled';
        }

        if ($contract->status === 'completed') {
            return 'completed';
        }

        if ($contract->status !== 'active') {
            return 'not_active';
        }

        $expectedEndDate = optional($contract->expected_end_date)->toDateString();

        if ($expectedEndDate < $asOfDate) {
            return 'overdue';
        }

        if ($expectedEndDate <= $endingSoonDate) {
            return 'ending_soon';
        }

        return 'active';
    }

    private function summary(Collection $rows): array
    {
        return [
            'contract_count' => $rows->count(),
            'active_contract_count' => $rows->where('status', 'active')->count(),
            'overdue_contract_count' => $rows->where('due_state', 'overdue')->count(),
            'ending_soon_contract_count' => $rows->where('due_state', 'ending_soon')->count(),
            'open_item_count' => (int) $rows->sum('open_item_count'),
            'unbilled_line_count' => (int) $rows->sum('unbilled_line_count'),
            'open_invoice_count' => (int) $rows->sum('open_invoice_count'),
            'posted_invoice_count' => (int) $rows->sum('posted_invoice_count'),
            'rent_billed_minor' => (int) $rows->sum('rent_billed_minor'),
            'deposit_billed_minor' => (int) $rows->sum('deposit_billed_minor'),
            'charge_billed_minor' => (int) $rows->sum('charge_billed_minor'),
            'tax_billed_minor' => (int) $rows->sum('tax_billed_minor'),
            'total_billed_minor' => (int) $rows->sum('total_billed_minor'),
            'open_invoice_total_minor' => (int) $rows->sum('open_invoice_total_minor'),
            'pending_damage_minor' => (int) $rows->sum('pending_damage_minor'),
        ];
    }

    private function readiness(Collection $rows, Collection $currencyCodes): array
    {
        return [
            'has_mixed_currency' => $currencyCodes->count() > 1,
            'has_overdue_contracts' => $rows->contains(fn (array $row): bool => $row['due_state'] === 'overdue'),
            'has_unbilled_lines' => $rows->contains(fn (array $row): bool => $row['unbilled_line_count'] > 0),
            'has_pending_damage' => $rows->contains(fn (array $row): bool => $row['pending_damage_minor'] > 0),
            'has_unposted_invoices' => $rows->contains(fn (array $row): bool => $row['open_invoice_count'] > 0),
        ];
    }

    private function baseCurrency(): string
    {
        return $this->currencyResolver->resolve();
    }
}
