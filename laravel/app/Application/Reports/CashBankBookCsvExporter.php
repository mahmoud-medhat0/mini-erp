<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CashBankBookCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function cash(array $report): StreamedResponse
    {
        return $this->book(
            report: $report,
            filename: 'cash_book_'.$report['cash_account']['code'].'.csv',
            title: 'Cash Book Report',
            accountLabel: 'Cash Account',
            account: $report['cash_account'],
            debitHeading: 'Receipts (In)',
            creditHeading: 'Payments (Out)'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function bank(array $report): StreamedResponse
    {
        return $this->book(
            report: $report,
            filename: 'bank_book_'.$report['bank_account']['code'].'.csv',
            title: 'Bank Book Report',
            accountLabel: 'Bank Account',
            account: $report['bank_account'],
            debitHeading: 'Deposits (In)',
            creditHeading: 'Withdrawals (Out)'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $account
     */
    private function book(
        array $report,
        string $filename,
        string $title,
        string $accountLabel,
        array $account,
        string $debitHeading,
        string $creditHeading,
    ): StreamedResponse {
        return $this->csvReportResponse->stream($filename, function ($handle) use ($report, $title, $accountLabel, $account, $debitHeading, $creditHeading): void {
            fputcsv($handle, [$title]);
            fputcsv($handle, [$accountLabel, $account['code'].' - '.$account['name']]);
            fputcsv($handle, ['Period', $report['date_from'].' to '.$report['date_to']]);
            fputcsv($handle, ['Currency', $report['currency']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Opening Balance', $this->formatMinor($report['opening_balance_minor'])]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Journal Ref', 'Line Description', $debitHeading, $creditHeading, 'Running Balance']);

            foreach ($report['entries'] as $item) {
                fputcsv($handle, [
                    $item['entry_date'],
                    $item['journal_number'],
                    $item['description'],
                    $this->formatMinor($item['debit_minor']),
                    $this->formatMinor($item['credit_minor']),
                    $this->formatMinor($item['balance_after_minor']),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Totals',
                '',
                '',
                $this->formatMinor($report['period_debit_minor']),
                $this->formatMinor($report['period_credit_minor']),
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
