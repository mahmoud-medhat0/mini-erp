<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalOperationsCsvExporter
{
    public function __construct(
        private readonly RentalOperationsReportService $reportService,
        private readonly CsvReportResponse $csvReportResponse,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $report = $this->reportService->generate($filters);
        $filename = "rental_operations_{$report['as_of_date']}.csv";

        return $this->csvReportResponse->stream($filename, function ($handle) use ($report): void {
            fputcsv($handle, ['RENTAL OPERATIONS REPORT']);
            fputcsv($handle, ['As Of Date', $report['as_of_date']]);
            fputcsv($handle, ['Ending Soon Date', $report['ending_soon_date']]);
            fputcsv($handle, ['Currencies In Scope', implode(',', $report['currency_codes'])]);
            fputcsv($handle, ['Single Currency', $report['single_currency'] ? 'YES' : 'NO']);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Contract Number',
                'Customer Code',
                'Customer Name',
                'Branch Code',
                'Branch Name',
                'Status',
                'Due State',
                'Start Date',
                'Expected End Date',
                'Currency',
                'Lines',
                'Open Items',
                'Unbilled Lines',
                'Open Invoices',
                'Posted Invoices',
                'Rent Billed Minor',
                'Deposit Billed Minor',
                'Charge Billed Minor',
                'Tax Billed Minor',
                'Total Billed Minor',
                'Open Invoice Total Minor',
                'Pending Damage Minor',
                'Latest Journal Number',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['contract_number'],
                    $row['customer_code'],
                    $this->localizedExportName($row['customer_name']),
                    $row['branch_code'],
                    $this->localizedExportName($row['branch_name']),
                    $row['status'],
                    $row['due_state'],
                    $row['start_date'],
                    $row['expected_end_date'],
                    $row['currency'],
                    $row['line_count'],
                    $row['open_item_count'],
                    $row['unbilled_line_count'],
                    $row['open_invoice_count'],
                    $row['posted_invoice_count'],
                    $row['rent_billed_minor'],
                    $row['deposit_billed_minor'],
                    $row['charge_billed_minor'],
                    $row['tax_billed_minor'],
                    $row['total_billed_minor'],
                    $row['open_invoice_total_minor'],
                    $row['pending_damage_minor'],
                    $row['latest_journal_number'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['SUMMARY']);
            foreach ($report['summary'] as $key => $value) {
                fputcsv($handle, [$key, $value]);
            }
        });
    }

    private function localizedExportName(mixed $name): string
    {
        if (is_array($name)) {
            return (string) ($name['en'] ?? reset($name) ?: '');
        }

        return (string) ($name ?? '');
    }
}
