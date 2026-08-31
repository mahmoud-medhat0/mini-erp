<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CostCenterActualsCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report): StreamedResponse
    {
        return $this->csvReportResponse->stream(
            "cost_center_actuals_{$report['from_date']}_to_{$report['to_date']}.csv",
            function ($handle) use ($report): void {
                fputcsv($handle, ['COST CENTER ACTUALS REPORT']);
                fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date']]);
                fputcsv($handle, ['Base Currency', $report['base_currency']]);
                fputcsv($handle, ['Currencies In Scope', implode(',', $report['currency_codes'])]);
                fputcsv($handle, []);
                fputcsv($handle, [
                    'Cost Center Code',
                    'Cost Center Name',
                    'Status',
                    'Unassigned',
                    'Currency',
                    'Account Code',
                    'Account Name',
                    'Account Type',
                    'Account Nature',
                    'Debit Minor',
                    'Credit Minor',
                    'Net Minor',
                    'Ledger Rows',
                ]);

                foreach ($report['rows'] as $row) {
                    if (empty($row['accounts'])) {
                        fputcsv($handle, [
                            $row['cost_center_code'],
                            $this->localizedExportName($row['cost_center_name']),
                            $row['cost_center_status'] ?? '',
                            $row['is_unassigned'] ? 'YES' : 'NO',
                            $row['currency'],
                            '',
                            '',
                            '',
                            '',
                            $row['debit_minor'],
                            $row['credit_minor'],
                            $row['net_minor'],
                            $row['ledger_row_count'],
                        ]);
                    } else {
                        foreach ($row['accounts'] as $acc) {
                            fputcsv($handle, [
                                $row['cost_center_code'],
                                $this->localizedExportName($row['cost_center_name']),
                                $row['cost_center_status'] ?? '',
                                $row['is_unassigned'] ? 'YES' : 'NO',
                                $row['currency'],
                                $acc['account_code'],
                                $this->localizedExportName($acc['account_name']),
                                $acc['account_type'],
                                $acc['account_nature'],
                                $acc['debit_minor'],
                                $acc['credit_minor'],
                                $acc['net_minor'],
                                $acc['ledger_row_count'],
                            ]);
                        }
                    }
                }

                fputcsv($handle, []);
                fputcsv($handle, ['SUMMARY BY CURRENCY']);
                fputcsv($handle, [
                    'Currency',
                    'Ledger Rows',
                    'Debit Minor',
                    'Credit Minor',
                    'Net Minor',
                ]);

                foreach ($report['summary_by_currency'] as $currSummary) {
                    fputcsv($handle, [
                        $currSummary['currency'],
                        $currSummary['ledger_row_count'],
                        $currSummary['debit_minor'],
                        $currSummary['credit_minor'],
                        $currSummary['net_minor'],
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
