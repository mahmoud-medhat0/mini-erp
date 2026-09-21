<?php

namespace App\Application\Reports;

use App\Application\Support\BaseCurrencyResolver;
use App\Models\PartnerTransaction;
use Carbon\Carbon;

/**
 * Phase 32.1 - Statement of Changes in Equity
 * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §11.1), built after Phase 30 as the
 * decision pack requires. A pure read-only report: opening equity balance
 * (equity accounts plus all undistributed net income accrued up to that
 * date, since this codebase never posts an automatic period-close entry into
 * retained earnings - BalanceSheetReportService already computes that
 * cumulative figure for every balance sheet) plus contributions, minus
 * drawings, plus the period's own net income, minus distributions, should
 * equal the actual closing balance computed the same way. Any difference is
 * surfaced as a reconciliation variance rather than hidden, matching the
 * cash flow report's own reconciliation pattern - it would only be non-zero
 * if something posted to an equity account outside the partner_transaction
 * subledger (a manual journal entry, for example).
 */
class EquityStatementReportService
{
    public function __construct(
        private readonly BalanceSheetReportService $balanceSheetReportService,
        private readonly IncomeStatementReportService $incomeStatementReportService,
        private readonly BaseCurrencyResolver $baseCurrencyResolver,
    ) {}

    public function generate(?string $fromDate = null, ?string $toDate = null): array
    {
        $to = $toDate ? Carbon::parse($toDate) : Carbon::now();
        $from = $fromDate ? Carbon::parse($fromDate) : $to->copy()->startOfMonth();
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $openingAsOfStr = $from->copy()->subDay()->format('Y-m-d');

        $openingBalanceSheet = $this->balanceSheetReportService->generate($openingAsOfStr);
        $closingBalanceSheet = $this->balanceSheetReportService->generate($toStr);
        $incomeStatement = $this->incomeStatementReportService->generate($fromStr, $toStr);

        $openingEquityMinor = (int) $openingBalanceSheet['summary']['total_equity_including_net_income_minor'];
        $actualClosingEquityMinor = (int) $closingBalanceSheet['summary']['total_equity_including_net_income_minor'];
        $netIncomeMinor = (int) $incomeStatement['summary']['net_income_minor'];

        $contributionsMinor = (int) PartnerTransaction::query()
            ->where('status', 'posted')
            ->where('transaction_type', 'contribution')
            ->whereBetween('transaction_date', [$fromStr, $toStr])
            ->sum('amount_minor');

        $drawingsMinor = (int) PartnerTransaction::query()
            ->where('status', 'posted')
            ->where('transaction_type', 'drawing')
            ->whereBetween('transaction_date', [$fromStr, $toStr])
            ->sum('amount_minor');

        $distributionsMinor = (int) PartnerTransaction::query()
            ->where('status', 'posted')
            ->where('transaction_type', 'distribution')
            ->whereBetween('transaction_date', [$fromStr, $toStr])
            ->sum('amount_minor');

        $computedClosingEquityMinor = $openingEquityMinor + $contributionsMinor - $drawingsMinor + $netIncomeMinor - $distributionsMinor;
        $varianceMinor = $actualClosingEquityMinor - $computedClosingEquityMinor;

        return [
            'from_date' => $fromStr,
            'to_date' => $toStr,
            'currency' => $this->baseCurrencyResolver->resolve(),
            'opening_equity_minor' => $openingEquityMinor,
            'contributions_minor' => $contributionsMinor,
            'drawings_minor' => $drawingsMinor,
            'net_income_minor' => $netIncomeMinor,
            'distributions_minor' => $distributionsMinor,
            'computed_closing_equity_minor' => $computedClosingEquityMinor,
            'actual_closing_equity_minor' => $actualClosingEquityMinor,
            'variance_minor' => $varianceMinor,
            'is_reconciled' => $varianceMinor === 0,
        ];
    }
}
