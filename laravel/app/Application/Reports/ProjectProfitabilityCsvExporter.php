<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectProfitabilityCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report): StreamedResponse
    {
        return $this->csvReportResponse->stream(
            "project_profitability_{$report['from_date']}_to_{$report['to_date']}.csv",
            function ($handle) use ($report): void {
                fputcsv($handle, ['PROJECT PROFITABILITY REPORT']);
                fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date']]);
                fputcsv($handle, ['Base Currency', $report['base_currency']]);
                fputcsv($handle, ['Currencies In Scope', implode(',', $report['currency_codes'])]);
                fputcsv($handle, []);
                fputcsv($handle, [
                    'Project Code',
                    'Project Name',
                    'Status',
                    'Unassigned',
                    'Currency',
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
                        $row['project_code'],
                        $this->localizedExportName($row['project_name']),
                        $row['project_status'] ?? '',
                        $row['is_unassigned'] ? 'YES' : 'NO',
                        $row['currency'],
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
                        $row['profit_margin_bps'] ?? '',
                    ]);
                }

                fputcsv($handle, []);
                fputcsv($handle, ['SUMMARY BY CURRENCY']);
                fputcsv($handle, [
                    'Currency',
                    'Ledger Rows',
                    'Net Revenue Minor',
                    'COGS Minor',
                    'Gross Profit Minor',
                    'Operating Expenses Minor',
                    'Net Income Minor',
                    'Profit Margin BPS',
                ]);

                foreach ($report['summary_by_currency'] as $currSummary) {
                    fputcsv($handle, [
                        $currSummary['currency'],
                        $currSummary['ledger_row_count'],
                        $currSummary['net_revenue_minor'],
                        $currSummary['cogs_minor'],
                        $currSummary['gross_profit_minor'],
                        $currSummary['operating_expense_minor'],
                        $currSummary['net_income_minor'],
                        $currSummary['profit_margin_bps'] ?? '',
                    ]);
                }
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
