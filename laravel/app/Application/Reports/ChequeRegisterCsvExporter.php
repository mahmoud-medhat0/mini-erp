<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ChequeRegisterCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report): StreamedResponse
    {
        return $this->csvReportResponse->stream('cheque_register_report.csv', function ($handle) use ($report): void {
            fputcsv($handle, ['Cheque Register Report']);
            fputcsv($handle, ['Direction', strtoupper((string) $report['direction'])]);
            fputcsv($handle, ['Currency', $report['filters']['currency']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Direction', 'Cheque Number', 'Party Code', 'Party Name', 'Bank Account', 'Due Date', 'Amount', 'Status']);

            foreach ($report['items'] as $item) {
                fputcsv($handle, [
                    strtoupper((string) $item['direction']),
                    $item['cheque_number'],
                    $item['party_code'],
                    $item['party_name'],
                    $item['bank_account_name'],
                    $item['due_date'],
                    $this->formatMinor($item['amount_minor']),
                    strtoupper((string) $item['status']),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Total Count', $report['total_count']]);
            fputcsv($handle, ['Total Incoming', $this->formatMinor($report['incoming_total_minor'])]);
            fputcsv($handle, ['Total Outgoing', $this->formatMinor($report['outgoing_total_minor'])]);
            fputcsv($handle, ['Grand Total', $this->formatMinor($report['total_amount_minor'])]);
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
