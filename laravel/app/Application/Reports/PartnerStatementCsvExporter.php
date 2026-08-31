<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerStatementCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function customer(array $report): StreamedResponse
    {
        return $this->statement(
            report: $report,
            filename: 'customer_statement_'.$report['customer']['code'].'.csv',
            title: 'Customer Statement Report',
            partnerLabel: 'Customer',
            partner: $report['customer'],
            debitHeading: 'Debit (Increase)',
            creditHeading: 'Credit (Payment)'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function supplier(array $report): StreamedResponse
    {
        return $this->statement(
            report: $report,
            filename: 'supplier_statement_'.$report['supplier']['code'].'.csv',
            title: 'Supplier Statement Report',
            partnerLabel: 'Supplier',
            partner: $report['supplier'],
            debitHeading: 'Debit (Payment)',
            creditHeading: 'Credit (Increase)'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $partner
     */
    private function statement(
        array $report,
        string $filename,
        string $title,
        string $partnerLabel,
        array $partner,
        string $debitHeading,
        string $creditHeading,
    ): StreamedResponse {
        return $this->csvReportResponse->stream($filename, function ($handle) use ($report, $title, $partnerLabel, $partner, $debitHeading, $creditHeading): void {
            fputcsv($handle, [$title]);
            fputcsv($handle, [$partnerLabel, $partner['code'].' - '.$partner['name']]);
            fputcsv($handle, ['Period', $report['filters']['date_from'].' to '.$report['filters']['date_to']]);
            fputcsv($handle, ['Currency', $report['filters']['currency']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Opening Balance', $this->formatMinor($report['opening_balance_minor'])]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Type', 'Reference', 'Description', $debitHeading, $creditHeading, 'Running Balance']);

            foreach ($report['lines'] as $line) {
                fputcsv($handle, [
                    $line['date'],
                    $line['type'],
                    $line['reference'],
                    $line['description'],
                    $this->formatMinor($line['debit_minor']),
                    $this->formatMinor($line['credit_minor']),
                    $this->formatMinor($line['running_balance_minor']),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Totals',
                '',
                '',
                '',
                $this->formatMinor($report['total_debit_minor']),
                $this->formatMinor($report['total_credit_minor']),
                $this->formatMinor($report['closing_balance_minor']),
            ]);
        });
    }

    private function formatMinor(mixed $amountMinor): string
    {
        $amount = (int) $amountMinor;
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
