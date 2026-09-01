<?php

namespace App\Application\Reports;

use App\Models\AccountingAccountMapping;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

class ArToGlReconciliationReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly ArApReconciliationQueryService $queryService,
    ) {}

    public function summary(string $asOfDate, ?string $currency = null): array
    {
        $targetCurrency = $this->currencyResolver->resolve($currency);

        return $this->buildSummary($asOfDate, $targetCurrency);
    }

    /**
     * Preserve the export/integration contract while loading the complete
     * partner breakdown with one aggregate query and no per-entry queries.
     */
    public function generate(string $asOfDate, ?string $currency = null): array
    {
        $targetCurrency = $this->currencyResolver->resolve($currency);
        $report = $this->buildSummary($asOfDate, $targetCurrency);
        $breakdown = $this->queryService
            ->partnerBalances('receivable', $asOfDate, $targetCurrency)
            ->orderBy('partner_code')
            ->orderBy('partner_id')
            ->get()
            ->map(fn (object $row): array => [
                'customer_id' => (string) $row->partner_id,
                'customer_code' => (string) $row->partner_code,
                'customer_name' => $this->queryService->localizedName($row->partner_name),
                'subledger_balance_minor' => (int) $row->subledger_balance_minor,
            ])
            ->all();

        return [...$report, 'customer_breakdown' => $breakdown];
    }

    private function buildSummary(string $asOfDate, string $currency): array
    {
        $mapping = AccountingAccountMapping::query()
            ->with('account')
            ->where('key', 'ar_control')
            ->first();
        $mappingConfigured = $mapping !== null && $mapping->account !== null;
        $controlAccount = $mappingConfigured ? [
            'id' => $mapping->account->id,
            'code' => $mapping->account->code,
            'name' => $mapping->account->name,
        ] : null;
        $subledgerTotalMinor = $this->queryService->subledgerTotal('receivable', $asOfDate, $currency);
        $glTotalMinor = $mappingConfigured
            ? (int) LedgerEntry::query()
                ->where('account_id', $mapping->account_id)
                ->where('currency', $currency)
                ->where('entry_date', '<=', $asOfDate)
                ->sum(DB::raw('debit_minor - credit_minor'))
            : 0;
        $differenceMinor = $subledgerTotalMinor - $glTotalMinor;

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'mapping_configured' => $mappingConfigured,
            'ar_control_account' => $controlAccount,
            'subledger_total_minor' => $subledgerTotalMinor,
            'gl_total_minor' => $glTotalMinor,
            'difference_minor' => $differenceMinor,
            'is_reconciled' => $mappingConfigured && $differenceMinor === 0,
        ];
    }
}
