<?php

namespace App\Application\Reports;

use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierOpeningBalance;
use App\Models\SupplierPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierStatementReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $supplierId, string $dateFrom, string $dateTo, ?string $currency = null): array
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $targetCurrency = $this->currencyResolver->resolve($currency);

        // PayableEntry is the canonical AP subledger. Posted opening balances,
        // payments, bills, and adjustments each create exactly one such entry.
        $openingBalanceMinor = (int) PayableEntry::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('credit_minor - debit_minor'));

        // Source documents are used only to enrich the human-readable reference;
        // their monetary amounts must never be added beside the subledger entry.
        $payEntries = PayableEntry::query()
            ->with('journalEntry:id,number,reference')
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->orderBy('entry_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $sourceReferences = $this->sourceReferences($payEntries);
        $movements = new Collection;

        foreach ($payEntries as $pe) {
            $movements->push([
                'date' => $pe->entry_date,
                'type' => $this->typeLabel($pe->source_type),
                'reference' => $sourceReferences[$pe->source_type.':'.$pe->source_id]
                    ?? $pe->journalEntry?->reference
                    ?? $pe->journalEntry?->number
                    ?? 'PE-'.$pe->id,
                'description' => $pe->description ?? 'Payable Entry',
                'debit_minor' => (int) $pe->debit_minor,
                'credit_minor' => (int) $pe->credit_minor,
                'created_at' => $pe->created_at,
            ]);
        }

        $runningBalance = $openingBalanceMinor;
        $totalDebit = 0;
        $totalCredit = 0;
        $lines = [];

        foreach ($movements as $item) {
            $dr = $item['debit_minor'];
            $cr = $item['credit_minor'];
            $runningBalance += ($cr - $dr);

            $totalDebit += $dr;
            $totalCredit += $cr;

            $lines[] = array_merge($item, [
                'running_balance_minor' => $runningBalance,
            ]);
        }

        return [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'tax_number' => $supplier->tax_number,
                'phone' => $supplier->phone,
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
     * than a subledger UUID. Amounts still come exclusively from PayableEntry.
     *
     * @param  Collection<int, PayableEntry>  $entries
     * @return array<string, string>
     */
    private function sourceReferences(Collection $entries): array
    {
        $references = [];

        SupplierOpeningBalance::query()
            ->whereKey($entries->where('source_type', 'supplier_opening_balance')->pluck('source_id')->filter()->unique())
            ->get(['id', 'reference'])
            ->each(function (SupplierOpeningBalance $balance) use (&$references): void {
                $references['supplier_opening_balance:'.$balance->id] = $balance->reference ?: 'OB-'.$balance->id;
            });

        SupplierPayment::query()
            ->whereKey($entries->where('source_type', 'supplier_payment')->pluck('source_id')->filter()->unique())
            ->get(['id', 'number', 'reference'])
            ->each(function (SupplierPayment $payment) use (&$references): void {
                $references['supplier_payment:'.$payment->id] = $payment->number ?: ($payment->reference ?: 'PAY-'.$payment->id);
            });

        return $references;
    }

    private function typeLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'supplier_opening_balance', 'opening_balance' => 'Opening Balance',
            'supplier_payment' => 'Supplier Payment',
            null, '' => 'Payable Entry',
            default => Str::headline($sourceType),
        };
    }
}
