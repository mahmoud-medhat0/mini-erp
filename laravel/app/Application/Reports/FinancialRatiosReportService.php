<?php

namespace App\Application\Reports;

use App\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;

class FinancialRatiosReportService
{
    public function __construct(
        private readonly BalanceSheetReportService $balanceSheetService,
        private readonly IncomeStatementReportService $incomeStatementService,
    ) {}

    /** @return array{mode: string, periods: list<array<string, mixed>>} */
    public function generate(string $periodId): array
    {
        $period = FinancialPeriod::query()->with('fiscalYear')->findOrFail($periodId);

        return [
            'mode' => 'single',
            'periods' => [$this->ratiosForPeriod($period)],
        ];
    }

    /**
     * @param  list<string>  $periodIds
     * @return array{mode: string, periods: list<array<string, mixed>>}
     */
    public function generateTrend(array $periodIds): array
    {
        $periods = FinancialPeriod::query()
            ->with('fiscalYear')
            ->whereIn('id', $periodIds)
            ->orderBy('start_date')
            ->get();

        return [
            'mode' => 'trend',
            'periods' => $periods->map(fn (FinancialPeriod $period): array => $this->ratiosForPeriod($period))->values()->all(),
        ];
    }

    /**
     * All ratios that compare a balance-sheet stock against an income-statement
     * flow (ROA, ROE, turnovers) use the average of the opening and closing
     * balance rather than a single point-in-time figure, matching standard
     * ratio-analysis convention. Point-in-time ratios (current/quick/leverage)
     * use the period-end balance sheet only.
     *
     * @return array<string, mixed>
     */
    private function ratiosForPeriod(FinancialPeriod $period): array
    {
        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');
        $priorDate = $period->start_date->copy()->subDay()->format('Y-m-d');

        $bsEnd = $this->balanceSheetService->generate($endDate)['summary'];
        $bsStart = $this->balanceSheetService->generate($priorDate)['summary'];
        $isSummary = $this->incomeStatementService->generate($startDate, $endDate)['summary'];

        $inventoryEnd = $this->inventoryValueAsOf($endDate);
        $inventoryStart = $this->inventoryValueAsOf($priorDate);
        $receivablesEnd = $this->receivablesAsOf($endDate);
        $receivablesStart = $this->receivablesAsOf($priorDate);

        $avgInventory = ($inventoryStart + $inventoryEnd) / 2;
        $avgReceivables = ($receivablesStart + $receivablesEnd) / 2;
        $avgTotalAssets = ($bsStart['total_assets_minor'] + $bsEnd['total_assets_minor']) / 2;
        $avgEquity = ($bsStart['total_equity_including_net_income_minor'] + $bsEnd['total_equity_including_net_income_minor']) / 2;

        $periodDays = $period->start_date->diffInDays($period->end_date) + 1;
        $receivablesTurnover = $this->safeDivide($isSummary['net_revenue_minor'], $avgReceivables);

        return [
            'period' => [
                'id' => $period->id,
                'year' => $period->fiscalYear?->year,
                'month' => $period->month,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'label' => sprintf('%s-%02d', $period->fiscalYear?->year ?? '—', $period->month),
            ],
            'liquidity' => [
                'current_ratio' => $this->safeDivide($bsEnd['total_current_assets_minor'], $bsEnd['total_current_liabilities_minor']),
                'quick_ratio' => $this->safeDivide($bsEnd['total_current_assets_minor'] - $inventoryEnd, $bsEnd['total_current_liabilities_minor']),
                'working_capital_minor' => $bsEnd['total_current_assets_minor'] - $bsEnd['total_current_liabilities_minor'],
            ],
            'profitability' => [
                'gross_profit_margin' => $this->safeDivide($isSummary['gross_profit_minor'], $isSummary['net_revenue_minor']),
                'operating_margin' => $this->safeDivide($isSummary['operating_income_minor'], $isSummary['net_revenue_minor']),
                'net_profit_margin' => $this->safeDivide($isSummary['net_income_minor'], $isSummary['net_revenue_minor']),
                'return_on_assets' => $this->safeDivide($isSummary['net_income_minor'], $avgTotalAssets),
                'return_on_equity' => $this->safeDivide($isSummary['net_income_minor'], $avgEquity),
            ],
            'leverage' => [
                'debt_to_equity' => $this->safeDivide($bsEnd['total_liabilities_minor'], $bsEnd['total_equity_including_net_income_minor']),
                'debt_to_assets' => $this->safeDivide($bsEnd['total_liabilities_minor'], $bsEnd['total_assets_minor']),
                'equity_ratio' => $this->safeDivide($bsEnd['total_equity_including_net_income_minor'], $bsEnd['total_assets_minor']),
            ],
            'efficiency' => [
                'inventory_turnover' => $this->safeDivide($isSummary['total_cogs_minor'], $avgInventory),
                'receivables_turnover' => $receivablesTurnover,
                'average_collection_period_days' => $receivablesTurnover ? round($periodDays / $receivablesTurnover, 1) : null,
                'asset_turnover' => $this->safeDivide($isSummary['net_revenue_minor'], $avgTotalAssets),
            ],
            'inputs' => [
                'total_current_assets_minor' => $bsEnd['total_current_assets_minor'],
                'total_current_liabilities_minor' => $bsEnd['total_current_liabilities_minor'],
                'total_assets_minor' => $bsEnd['total_assets_minor'],
                'total_liabilities_minor' => $bsEnd['total_liabilities_minor'],
                'total_equity_minor' => $bsEnd['total_equity_including_net_income_minor'],
                'inventory_minor' => $inventoryEnd,
                'receivables_minor' => $receivablesEnd,
                'net_revenue_minor' => $isSummary['net_revenue_minor'],
                'gross_profit_minor' => $isSummary['gross_profit_minor'],
                'operating_income_minor' => $isSummary['operating_income_minor'],
                'net_income_minor' => $isSummary['net_income_minor'],
                'total_cogs_minor' => $isSummary['total_cogs_minor'],
            ],
        ];
    }

    /**
     * Total inventory valuation as of a date, derived from the stock ledger's
     * per-movement value deltas (the balance sheet's current-assets line is a
     * single lumped total with no dedicated inventory sub-line to read from).
     */
    private function inventoryValueAsOf(string $date): int
    {
        return (int) DB::table('stock_movement_ledger')
            ->where('movement_date', '<=', $date)
            ->sum('value_delta_minor');
    }

    /**
     * Outstanding accounts receivable as of a date, from the AR subledger
     * (same source and pattern CustomerStatementReportService uses).
     */
    private function receivablesAsOf(string $date): int
    {
        return (int) DB::table('receivable_entry')
            ->where('entry_date', '<=', $date)
            ->sum(DB::raw('debit_minor - credit_minor'));
    }

    private function safeDivide(int|float $numerator, int|float $denominator): ?float
    {
        if ($denominator == 0) {
            return null;
        }

        return $numerator / $denominator;
    }
}
