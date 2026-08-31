<?php

namespace App\Application\Reports;

use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\CustomerReceipt;
use App\Models\ReceivableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerStatementReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $customerId, string $dateFrom, string $dateTo, ?string $currency = null): array
    {
        $customer = Customer::query()->findOrFail($customerId);
        $targetCurrency = $this->currencyResolver->resolve($currency);

        // ReceivableEntry is the canonical AR subledger. Posted opening balances,
        // receipts, invoices, and adjustments each create exactly one such entry.
        $openingBalanceMinor = (int) ReceivableEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('debit_minor - credit_minor'));

        // Source documents are used only to enrich the human-readable reference;
        // their monetary amounts must never be added beside the subledger entry.
        $recEntries = ReceivableEntry::query()
            ->with('journalEntry:id,number,reference')
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->get();
        $sourceReferences = $this->sourceReferences($recEntries);
        $movements = new Collection;

        foreach ($recEntries as $re) {
            $movements->push([
                'date' => $re->entry_date,
                'type' => $this->typeLabel($re->source_type),
                'reference' => $sourceReferences[$re->source_type.':'.$re->source_id]
                    ?? $re->journalEntry?->reference
                    ?? $re->journalEntry?->number
                    ?? 'RE-'.$re->id,
                'description' => $re->description ?? 'Receivable Entry',
                'debit_minor' => (int) $re->debit_minor,
                'credit_minor' => (int) $re->credit_minor,
                'created_at' => $re->created_at,
            ]);
        }

        // Sort movements chronologically
        $sorted = $movements->sortBy(fn ($item) => $item['date'].' '.$item['created_at'])->values();

        $runningBalance = $openingBalanceMinor;
        $totalDebit = 0;
        $totalCredit = 0;
        $lines = [];

        foreach ($sorted as $item) {
            $dr = $item['debit_minor'];
            $cr = $item['credit_minor'];
            $runningBalance += ($dr - $cr);

            $totalDebit += $dr;
            $totalCredit += $cr;

            $lines[] = array_merge($item, [
                'running_balance_minor' => $runningBalance,
            ]);
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'tax_number' => $customer->tax_number,
                'phone' => $customer->phone,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $targetCurrency,
            ],
            'opening_balance_minor' => $openingBalanceMinor,
            'lines' => $lines,
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'closing_balance_minor' => $runningBalance,
        ];
    }

    /**
     * Resolve references for source documents whose native reference is clearer
     * than a subledger UUID. Amounts still come exclusively from ReceivableEntry.
     *
     * @param  Collection<int, ReceivableEntry>  $entries
     * @return array<string, string>
     */
    private function sourceReferences(Collection $entries): array
    {
        $references = [];

        CustomerOpeningBalance::query()
            ->whereKey($entries->where('source_type', 'customer_opening_balance')->pluck('source_id')->filter()->unique())
            ->get(['id', 'reference'])
            ->each(function (CustomerOpeningBalance $balance) use (&$references): void {
                $references['customer_opening_balance:'.$balance->id] = $balance->reference ?: 'OB-'.$balance->id;
            });

        CustomerReceipt::query()
            ->whereKey($entries->where('source_type', 'customer_receipt')->pluck('source_id')->filter()->unique())
            ->get(['id', 'number', 'reference'])
            ->each(function (CustomerReceipt $receipt) use (&$references): void {
                $references['customer_receipt:'.$receipt->id] = $receipt->number ?: ($receipt->reference ?: 'REC-'.$receipt->id);
            });

        return $references;
    }

    private function typeLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'customer_opening_balance', 'opening_balance' => 'Opening Balance',
            'customer_receipt' => 'Customer Receipt',
            null, '' => 'Receivable Entry',
            default => Str::headline($sourceType),
        };
    }
}
