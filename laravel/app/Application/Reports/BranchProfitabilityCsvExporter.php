<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchProfitabilityCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report): StreamedResponse
    {
        return $this->csvReportResponse->stream(
            "branch_profitability_{$report['from_date']}_to_{$report['to_date']}.csv",
            function ($handle) use ($report): void {
                fputcsv($handle, ['BRANCH PROFITABILITY REPORT']);
                fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date']]);
                fputcsv($handle, ['Base Currency', $report['base_currency']]);
                fputcsv($handle, ['Currencies In Scope', implode(',', $report['currency_codes'])]);
                fputcsv($handle, []);
                fputcsv($handle, [
                    'Branch Code',
                    'Branch Name',
                    'Active',
                    'Unassigned',
                    'Ledger Rows',
                    'Revenue Minor',
                    'Returns And Allowances Minor',
                    'Net Revenue Minor',
                    'COGS Minor',
                    'Gross Profit Minor',
                    'Operating Expenses Minor',
                    'Operating Income Minor',
                    'Other Income Minor',
                    'Other Expenses Minor',
                    'Net Income Minor',
                    'Profit Margin BPS',
                ]);

                foreach ($report['rows'] as $row) {
                    fputcsv($handle, [
                        $row['branch_code'],
                        $this->localizedExportName($row['branch_name']),
                        $row['is_active'] ? 'YES' : 'NO',
                        $row['is_unassigned'] ? 'YES' : 'NO',
                        $row['ledger_row_count'],
                        $row['revenue_minor'],
                        $row['contra_revenue_minor'],
                        $row['net_revenue_minor'],
                        $row['cogs_minor'],
                        $row['gross_profit_minor'],
                        $row['operating_expense_minor'],
                        $row['operating_income_minor'],
                        $row['other_income_minor'],
                        $row['other_expense_minor'],
                        $row['net_income_minor'],
                        $row['profit_margin_bps'],
                    ]);
                }

                fputcsv($handle, []);
                fputcsv($handle, ['SUMMARY']);
                fputcsv($handle, ['Ledger Rows', $report['summary']['ledger_row_count']]);
                fputcsv($handle, ['Net Revenue Minor', $report['summary']['net_revenue_minor']]);
                fputcsv($handle, ['COGS Minor', $report['summary']['cogs_minor']]);
                fputcsv($handle, ['Gross Profit Minor', $report['summary']['gross_profit_minor']]);
                fputcsv($handle, ['Operating Expenses Minor', $report['summary']['operating_expense_minor']]);
                fputcsv($handle, ['Net Income Minor', $report['summary']['net_income_minor']]);
                fputcsv($handle, []);
                fputcsv($handle, ['READINESS']);
                fputcsv($handle, ['Branch Dimension Status', $report['readiness']['branch_dimension_status']]);
                fputcsv($handle, ['Unassigned P&L Row Count', $report['readiness']['unassigned_pnl_row_count']]);
                fputcsv($handle, ['Unassigned Net Income Minor', $report['readiness']['unassigned_net_income_minor']]);
            }
        );
    }

    private function localizedExportName(mixed $name): string
    {
        if (is_array($name)) {
            return (string) ($name['en'] ?? reset($name) ?: '');
        }

        return (string) ($name ?? '');
    }
}
