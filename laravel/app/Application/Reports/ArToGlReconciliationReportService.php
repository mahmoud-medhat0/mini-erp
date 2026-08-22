<?php

namespace App\Application\Reports;

use App\Models\AccountingAccountMapping;
use App\Models\LedgerEntry;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArToGlReconciliationReportService
{
    public function generate(string $asOfDate, ?string $currency = null): array
    {
        $targetCurrency = $currency ?? 'EGP';

        // 1. Fetch AR control account mapping
        $mapping = AccountingAccountMapping::query()
            ->with('account')
            ->where('key', 'ar_control')
            ->first();

        $mappingConfigured = $mapping !== null && $mapping->account !== null;
        $arControlAccount = $mappingConfigured ? [
            'id' => $mapping->account->id,
            'code' => $mapping->account->code,
            'name' => $mapping->account->name,
        ] : null;

        // 2. Subledger AR Balance (Net debit_minor - credit_minor from receivable_entry)
        $entries = ReceivableEntry::query()
            ->with('customer')
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<=', $asOfDate.' 23:59:59')
            ->get();

        $subledgerTotalMinor = 0;
        $customerTotals = [];

        foreach ($entries as $entry) {
            $origNet = (int) $entry->debit_minor - (int) $entry->credit_minor;

            if ($entry->debit_minor >= $entry->credit_minor) {
                $allocatedSum = (int) ReceivableAllocation::query()
                    ->where('receivable_entry_id', $entry->id)
                    ->where('status', 'active')
                    ->where('created_at', '<=', $asOfDate.' 23:59:59')
                    ->sum('amount_minor');

                $targetSettledSum = (int) ReceivableEntrySettlement::query()
                    ->where('target_receivable_entry_id', $entry->id)
                    ->where('status', 'active')
                    ->where('settled_at', '<=', Carbon::parse($asOfDate)->endOfDay())
                    ->sum('amount_minor');

                $netOpen = $origNet - $allocatedSum - $targetSettledSum;
            } else {
                $sourceSettledSum = (int) ReceivableEntrySettlement::query()
                    ->where('source_receivable_entry_id', $entry->id)
                    ->where('status', 'active')
                    ->where('settled_at', '<=', Carbon::parse($asOfDate)->endOfDay())
                    ->sum('amount_minor');

                $netOpen = $origNet + $sourceSettledSum;
            }

            if ($netOpen != 0) {
                $subledgerTotalMinor += $netOpen;

                $cId = $entry->customer_id;
                if (! isset($customerTotals[$cId])) {
                    $customerTotals[$cId] = [
                        'customer_id' => $cId,
                        'customer_code' => $entry->customer?->code ?? '—',
                        'customer_name' => $entry->customer?->name ?? 'Unknown Customer',
                        'subledger_balance_minor' => 0,
                    ];
                }
                $customerTotals[$cId]['subledger_balance_minor'] += $netOpen;
            }
        }

        // 3. GL AR Control Account Balance (Net Debit - Credit as of date)
        $glTotalMinor = 0;

        if ($mappingConfigured) {
            $glTotalMinor = (int) LedgerEntry::query()
                ->where('account_id', $mapping->account_id)
                ->where('currency', $targetCurrency)
                ->where('entry_date', '<=', $asOfDate.' 23:59:59')
                ->sum(DB::raw('debit_minor - credit_minor'));
        }

        $differenceMinor = $subledgerTotalMinor - $glTotalMinor;

        return [
            'as_of_date' => $asOfDate,
            'currency' => $targetCurrency,
            'mapping_configured' => $mappingConfigured,
            'ar_control_account' => $arControlAccount,
            'subledger_total_minor' => $subledgerTotalMinor,
            'gl_total_minor' => $glTotalMinor,
            'difference_minor' => $differenceMinor,
            'is_reconciled' => $mappingConfigured && $differenceMinor === 0,
            'customer_breakdown' => array_values($customerTotals),
        ];
    }
}
