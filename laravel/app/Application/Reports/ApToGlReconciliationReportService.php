<?php

namespace App\Application\Reports;

use App\Models\AccountingAccountMapping;
use App\Models\LedgerEntry;
use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApToGlReconciliationReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $asOfDate, ?string $currency = null): array
    {
        $targetCurrency = $this->currencyResolver->resolve($currency);

        // 1. Fetch AP control account mapping
        $mapping = AccountingAccountMapping::query()
            ->with('account')
            ->where('key', 'ap_control')
            ->first();

        $mappingConfigured = $mapping !== null && $mapping->account !== null;
        $apControlAccount = $mappingConfigured ? [
            'id' => $mapping->account->id,
            'code' => $mapping->account->code,
            'name' => $mapping->account->name,
        ] : null;

        // 2. Subledger AP Balance (Net credit_minor - debit_minor from payable_entry)
        $entries = PayableEntry::query()
            ->with('supplier')
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<=', $asOfDate.' 23:59:59')
            ->get();

        $subledgerTotalMinor = 0;
        $supplierTotals = [];

        foreach ($entries as $entry) {
            $origNet = (int) $entry->credit_minor - (int) $entry->debit_minor;

            if ($entry->credit_minor >= $entry->debit_minor) {
                $allocatedSum = (int) PayableAllocation::query()
                    ->where('payable_entry_id', $entry->id)
                    ->where('status', 'active')
                    ->where('created_at', '<=', $asOfDate.' 23:59:59')
                    ->sum('amount_minor');

                $targetSettledSum = (int) PayableEntrySettlement::query()
                    ->where('target_payable_entry_id', $entry->id)
                    ->where('status', 'active')
                    ->where('settled_at', '<=', Carbon::parse($asOfDate)->endOfDay())
                    ->sum('amount_minor');

                $netOpen = $origNet - $allocatedSum - $targetSettledSum;
            } else {
                $sourceSettledSum = (int) PayableEntrySettlement::query()
                    ->where('source_payable_entry_id', $entry->id)
                    ->where('status', 'active')
                    ->where('settled_at', '<=', Carbon::parse($asOfDate)->endOfDay())
                    ->sum('amount_minor');

                $netOpen = $origNet + $sourceSettledSum;
            }

            if ($netOpen != 0) {
                $subledgerTotalMinor += $netOpen;

                $sId = $entry->supplier_id;
                if (! isset($supplierTotals[$sId])) {
                    $supplierTotals[$sId] = [
                        'supplier_id' => $sId,
                        'supplier_code' => $entry->supplier?->code ?? '—',
                        'supplier_name' => $entry->supplier?->name ?? 'Unknown Supplier',
                        'subledger_balance_minor' => 0,
                    ];
                }
                $supplierTotals[$sId]['subledger_balance_minor'] += $netOpen;
            }
        }

        // 3. GL AP Control Account Balance (Net Credit - Debit as of date)
        $glTotalMinor = 0;

        if ($mappingConfigured) {
            $glTotalMinor = (int) LedgerEntry::query()
                ->where('account_id', $mapping->account_id)
                ->where('currency', $targetCurrency)
                ->where('entry_date', '<=', $asOfDate.' 23:59:59')
                ->sum(DB::raw('credit_minor - debit_minor'));
        }

        $differenceMinor = $subledgerTotalMinor - $glTotalMinor;

        return [
            'as_of_date' => $asOfDate,
            'currency' => $targetCurrency,
            'mapping_configured' => $mappingConfigured,
            'ap_control_account' => $apControlAccount,
            'subledger_total_minor' => $subledgerTotalMinor,
            'gl_total_minor' => $glTotalMinor,
            'difference_minor' => $differenceMinor,
            'is_reconciled' => $mappingConfigured && $differenceMinor === 0,
            'supplier_breakdown' => array_values($supplierTotals),
        ];
    }
}
