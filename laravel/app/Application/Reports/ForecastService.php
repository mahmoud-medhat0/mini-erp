<?php

namespace App\Application\Reports;

use App\Application\Support\BaseCurrencyResolver;
use Carbon\Carbon;

/**
 * Phase 31 - Simple forecasting (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §10,
 * decisions 1-2): a moving average of recent posted months plus a
 * user-supplied manual growth percentage - not a statistical model. Sales
 * and expense are the two projected inputs; cash flow and profit are derived
 * from them rather than forecast independently, per the decision pack's
 * recommended scope. Reuses IncomeStatementReportService for every historical
 * month so the same GL-derived figures backing the income statement and the
 * Phase 25 dashboard snapshot are the ones being projected forward.
 */
class ForecastService
{
    public function __construct(
        private readonly IncomeStatementReportService $incomeStatementReportService,
        private readonly BaseCurrencyResolver $baseCurrencyResolver,
    ) {}

    public function forecast(
        int $lookbackMonths = 3,
        int $monthsAhead = 3,
        float $salesGrowthPct = 0.0,
        float $expenseGrowthPct = 0.0,
    ): array {
        $lookbackMonths = max(1, min($lookbackMonths, 24));
        $monthsAhead = max(1, min($monthsAhead, 12));

        $history = $this->monthlyHistory($lookbackMonths);
        $avgSalesMinor = $this->average(array_column($history, 'sales_minor'));
        $avgExpenseMinor = $this->average(array_column($history, 'expense_minor'));

        $projection = [];
        $salesMinor = $avgSalesMinor;
        $expenseMinor = $avgExpenseMinor;
        $cursor = Carbon::now()->startOfMonth();

        for ($i = 1; $i <= $monthsAhead; $i++) {
            $cursor = $cursor->copy()->addMonthNoOverflow();
            $salesMinor = (int) round($salesMinor * (1 + $salesGrowthPct / 100));
            $expenseMinor = (int) round($expenseMinor * (1 + $expenseGrowthPct / 100));

            $projection[] = [
                'month' => $cursor->format('Y-m'),
                'sales_minor' => $salesMinor,
                'expense_minor' => $expenseMinor,
                'profit_minor' => $salesMinor - $expenseMinor,
                'cash_flow_minor' => $salesMinor - $expenseMinor,
            ];
        }

        return [
            'currency' => $this->baseCurrencyResolver->resolve(),
            'lookback_months' => $lookbackMonths,
            'months_ahead' => $monthsAhead,
            'assumptions' => [
                'sales_growth_pct' => $salesGrowthPct,
                'expense_growth_pct' => $expenseGrowthPct,
            ],
            'history' => $history,
            'baseline' => [
                'average_sales_minor' => $avgSalesMinor,
                'average_expense_minor' => $avgExpenseMinor,
            ],
            'projection' => $projection,
        ];
    }

    private function monthlyHistory(int $months): array
    {
        $result = [];
        $now = Carbon::now()->startOfMonth();

        for ($i = $months; $i >= 1; $i--) {
            $monthStart = $now->copy()->subMonthsNoOverflow($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $report = $this->incomeStatementReportService->generate($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'));
            $salesMinor = (int) $report['summary']['net_revenue_minor'];
            $expenseMinor = (int) $report['summary']['total_cogs_minor'] + (int) $report['summary']['total_operating_expenses_minor'];

            $result[] = [
                'month' => $monthStart->format('Y-m'),
                'sales_minor' => $salesMinor,
                'expense_minor' => $expenseMinor,
                'profit_minor' => $salesMinor - $expenseMinor,
            ];
        }

        return $result;
    }

    private function average(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }
}
