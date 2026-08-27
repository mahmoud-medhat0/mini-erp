<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class VatCsvReportExporter
{
    public function __construct(
        private readonly VatRegisterReportService $registerService,
        private readonly VatSummaryReportService $summaryService,
        private readonly VatToGlReconciliationService $reconciliationService,
        private readonly CsvReportResponse $csvReportResponse,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function register(array $filters): StreamedResponse
    {
        $report = $this->registerService->generate($filters);

        return $this->csvReportResponse->fromRows(
            "vat_register_{$report['from_date']}_to_{$report['to_date']}.csv",
            ['Document Date', 'Document Type', 'Document Number', 'Entity Type', 'Entity Name', 'Tax Category', 'Tax Code', 'Rate Bps', 'Subtotal Minor', 'Tax Minor', 'Gross Minor'],
            $report['rows'],
            fn (array $row): array => [
                $row['document_date'],
                $row['document_type'],
                $row['document_number'],
                $row['entity_type'],
                $row['entity_name'],
                $row['tax_category'],
                $row['tax_code'],
                $row['tax_rate_bps'],
                $row['subtotal_minor'],
                $row['tax_amount_minor'],
                $row['gross_amount_minor'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summary(array $filters): StreamedResponse
    {
        $report = $this->summaryService->generate($filters);

        return $this->csvReportResponse->stream(
            "vat_summary_{$report['from_date']}_to_{$report['to_date']}.csv",
            function ($handle) use ($report): void {
                fputcsv($handle, ['VAT Summary Report']);
                fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date']]);
                fputcsv($handle, []);

                fputcsv($handle, ['OUTPUT VAT SUMMARY']);
                fputcsv($handle, ['Tax Code', 'Tax Rate Bps', 'Subtotal Minor', 'Tax Minor', 'Gross Minor']);
                foreach ($report['output_vat_breakdown'] as $row) {
                    fputcsv($handle, [$row['code'], $row['rate_bps'], $row['subtotal_minor'], $row['tax_amount_minor'], $row['gross_amount_minor']]);
                }
                fputcsv($handle, ['Total Output VAT', '', $report['summary']['total_output_subtotal_minor'], $report['summary']['total_output_tax_minor'], $report['summary']['total_output_gross_minor']]);
                fputcsv($handle, []);

                fputcsv($handle, ['INPUT VAT SUMMARY']);
                fputcsv($handle, ['Tax Code', 'Tax Rate Bps', 'Subtotal Minor', 'Tax Minor', 'Gross Minor']);
                foreach ($report['input_vat_breakdown'] as $row) {
                    fputcsv($handle, [$row['code'], $row['rate_bps'], $row['subtotal_minor'], $row['tax_amount_minor'], $row['gross_amount_minor']]);
                }
                fputcsv($handle, ['Total Input VAT', '', $report['summary']['total_input_subtotal_minor'], $report['summary']['total_input_tax_minor'], $report['summary']['total_input_gross_minor']]);
                fputcsv($handle, []);

                fputcsv($handle, ['NET VAT PAYABLE', '', '', $report['summary']['net_vat_payable_minor']]);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function reconciliation(array $filters): StreamedResponse
    {
        $report = $this->reconciliationService->generate($filters);

        return $this->csvReportResponse->stream(
            "vat_gl_reconciliation_{$report['from_date']}_to_{$report['to_date']}.csv",
            function ($handle) use ($report): void {
                fputcsv($handle, ['VAT to GL Reconciliation Report']);
                fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date'], 'Currency', $report['currency']]);
                fputcsv($handle, ['Reconciled Status', $report['is_reconciled'] ? 'RECONCILED' : 'UNRECONCILED DIFFERENCE']);
                fputcsv($handle, []);

                fputcsv($handle, ['Category', 'Register Tax Minor', 'GL Ledger Movement Minor', 'Signed Difference Minor']);
                fputcsv($handle, ['Output VAT', $report['register_output_tax_minor'], $report['gl_output_tax_minor'], $report['output_tax_difference_minor']]);
                fputcsv($handle, ['Input VAT', $report['register_input_tax_minor'], $report['gl_input_tax_minor'], $report['input_tax_difference_minor']]);
                fputcsv($handle, ['Net VAT', $report['register_net_vat_minor'], $report['gl_net_vat_minor'], $report['net_vat_difference_minor']]);
            }
        );
    }
}
