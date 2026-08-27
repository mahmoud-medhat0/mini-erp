<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialStatementCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function balanceSheet(array $report): StreamedResponse
    {
        return $this->statement(
            filename: "balance_sheet_{$report['as_of_date']}.csv",
            heading: ['BALANCE SHEET REPORT', "As Of: {$report['as_of_date']}"],
            report: $report,
            summaryRows: [
                ['Total Current Assets (Minor)', $report['summary']['total_current_assets_minor']],
                ['Total Non-Current Assets (Minor)', $report['summary']['total_non_current_assets_minor']],
                ['TOTAL ASSETS (Minor)', $report['summary']['total_assets_minor']],
                ['Total Current Liabilities (Minor)', $report['summary']['total_current_liabilities_minor']],
                ['Total Non-Current Liabilities (Minor)', $report['summary']['total_non_current_liabilities_minor']],
                ['Total Liabilities (Minor)', $report['summary']['total_liabilities_minor']],
                ['Total Equity (Minor)', $report['summary']['total_equity_minor']],
                ['Current Period Net Income (Minor)', $report['summary']['current_period_net_income_minor']],
                ['Total Equity including Net Income (Minor)', $report['summary']['total_equity_including_net_income_minor']],
                ['TOTAL LIABILITIES & EQUITY (Minor)', $report['summary']['total_liabilities_and_equity_minor']],
                ['Is Balanced', $report['summary']['is_balanced'] ? 'YES' : 'NO'],
                ['Imbalance (Minor)', $report['summary']['imbalance_minor']],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function incomeStatement(array $report): StreamedResponse
    {
        return $this->statement(
            filename: "income_statement_{$report['from_date']}_to_{$report['to_date']}.csv",
            heading: ['INCOME STATEMENT REPORT', "Period: {$report['from_date']} to {$report['to_date']}"],
            report: $report,
            summaryRows: [
                ['Gross Revenue (Minor)', $report['summary']['total_revenue_minor']],
                ['Sales Returns & Allowances (Minor)', $report['summary']['total_contra_revenue_minor']],
                ['NET REVENUE (Minor)', $report['summary']['net_revenue_minor']],
                ['Cost of Goods Sold (Minor)', $report['summary']['total_cogs_minor']],
                ['GROSS PROFIT (Minor)', $report['summary']['gross_profit_minor']],
                ['Operating Expenses (Minor)', $report['summary']['total_operating_expenses_minor']],
                ['OPERATING INCOME (Minor)', $report['summary']['operating_income_minor']],
                ['Other Income (Minor)', $report['summary']['total_other_income_minor']],
                ['Other Expenses (Minor)', $report['summary']['total_other_expenses_minor']],
                ['NET INCOME / (LOSS) (Minor)', $report['summary']['net_income_minor']],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function cashFlow(array $report): StreamedResponse
    {
        return $this->csvReportResponse->stream(
            "cash_flow_{$report['from_date']}_to_{$report['to_date']}.csv",
            function ($handle) use ($report): void {
                fputcsv($handle, ['CASH FLOW STATEMENT REPORT', "Period: {$report['from_date']} to {$report['to_date']}"]);
                fputcsv($handle, []);

                fputcsv($handle, ['CASH FLOW SUMMARY', 'AMOUNT (Minor)']);
                fputcsv($handle, ['Opening Cash Balance', $report['opening_cash_minor']]);
                fputcsv($handle, []);

                $this->cashFlowSection($handle, 'OPERATING ACTIVITIES', 'Operating', $report['operating']);
                $this->cashFlowSection($handle, 'INVESTING ACTIVITIES', 'Investing', $report['investing']);
                $this->cashFlowSection($handle, 'FINANCING ACTIVITIES', 'Financing', $report['financing']);

                if ((int) $report['unclassified']['net_minor'] !== 0 || ! empty($report['unclassified_warnings'])) {
                    fputcsv($handle, ['UNCLASSIFIED CASH MOVEMENTS']);
                    fputcsv($handle, ['Unclassified Inflows', $report['unclassified']['inflows_minor']]);
                    fputcsv($handle, ['Unclassified Outflows', $report['unclassified']['outflows_minor']]);
                    fputcsv($handle, ['NET UNCLASSIFIED CASH', $report['unclassified']['net_minor']]);
                    fputcsv($handle, []);
                }

                fputcsv($handle, ['RECONCILIATION SUMMARY']);
                fputcsv($handle, ['Net Increase / (Decrease) in Cash', $report['net_cash_change_minor']]);
                fputcsv($handle, ['Closing Cash Balance', $report['closing_cash_minor']]);
                fputcsv($handle, ['Reconciled Closing Cash Balance', $report['reconciled_closing_cash_minor']]);
                fputcsv($handle, ['Is Reconciled', $report['is_reconciled'] ? 'YES' : 'NO']);
            }
        );
    }

    /**
     * @param  array{0: string, 1: string}  $heading
     * @param  array<string, mixed>  $report
     * @param  list<array<int, mixed>>  $summaryRows
     */
    private function statement(array $heading, array $report, string $filename, array $summaryRows): StreamedResponse
    {
        return $this->csvReportResponse->stream($filename, function ($handle) use ($heading, $report, $summaryRows): void {
            fputcsv($handle, $heading);
            fputcsv($handle, []);
            fputcsv($handle, ['Section', 'Line Code', 'Line Name', 'Account Code', 'Account Name', 'Debit (Minor)', 'Credit (Minor)', 'Net Amount (Minor)']);

            foreach ($report['sections'] as $sectionKey => $section) {
                foreach ($section['lines'] as $line) {
                    $lineName = $this->localizedExportName($line['name']);

                    foreach ($line['accounts'] as $account) {
                        fputcsv($handle, [
                            $sectionKey,
                            $line['code'],
                            $lineName,
                            $account['code'],
                            $this->localizedExportName($account['name']),
                            $account['debit_minor'],
                            $account['credit_minor'],
                            $account['net_minor'],
                        ]);
                    }

                    fputcsv($handle, [$sectionKey, $line['code'], "Total {$lineName}", '', '', '', '', $line['total_minor']]);
                }

                fputcsv($handle, [$sectionKey, '', "SECTION TOTAL ({$sectionKey})", '', '', '', '', $section['total_minor']]);
                fputcsv($handle, []);
            }

            fputcsv($handle, ['SUMMARY']);
            foreach ($summaryRows as $row) {
                fputcsv($handle, $row);
            }
        });
    }

    /**
     * @param  array{inflows_minor: mixed, outflows_minor: mixed, net_minor: mixed}  $totals
     */
    private function cashFlowSection(mixed $handle, string $heading, string $labelPrefix, array $totals): void
    {
        fputcsv($handle, [$heading]);
        fputcsv($handle, ["{$labelPrefix} Cash Inflows", $totals['inflows_minor']]);
        fputcsv($handle, ["{$labelPrefix} Cash Outflows", $totals['outflows_minor']]);
        fputcsv($handle, ["NET CASH FROM {$heading}", $totals['net_minor']]);
        fputcsv($handle, []);
    }

    private function localizedExportName(mixed $name): string
    {
        if (is_array($name)) {
            return (string) ($name['en'] ?? reset($name) ?: '');
        }

        return (string) ($name ?? '');
    }
}
