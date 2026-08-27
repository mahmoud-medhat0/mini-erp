<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ArApCsvReportExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function arAging(array $report): StreamedResponse
    {
        return $this->aging(
            report: $report,
            filename: 'ar_aging_'.$report['as_of_date'].'.csv',
            title: 'AR Aging Report',
            partnerGroupKey: 'customers',
            partnerKey: 'customer',
            partnerCodeHeading: 'Customer Code',
            partnerNameHeading: 'Customer Name'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function apAging(array $report): StreamedResponse
    {
        return $this->aging(
            report: $report,
            filename: 'ap_aging_'.$report['as_of_date'].'.csv',
            title: 'AP Aging Report',
            partnerGroupKey: 'suppliers',
            partnerKey: 'supplier',
            partnerCodeHeading: 'Supplier Code',
            partnerNameHeading: 'Supplier Name'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function arToGlReconciliation(array $report): StreamedResponse
    {
        return $this->reconciliation(
            report: $report,
            filename: 'ar_to_gl_reconciliation_'.$report['as_of_date'].'.csv',
            title: 'AR to GL Reconciliation Report',
            subledgerLabel: 'AR Subledger Total Balance',
            glLabel: 'AR Control GL Account Balance',
            breakdownKey: 'customer_breakdown',
            partnerCodeHeading: 'Customer Code',
            partnerNameHeading: 'Customer Name',
            codeKey: 'customer_code',
            nameKey: 'customer_name'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function apToGlReconciliation(array $report): StreamedResponse
    {
        return $this->reconciliation(
            report: $report,
            filename: 'ap_to_gl_reconciliation_'.$report['as_of_date'].'.csv',
            title: 'AP to GL Reconciliation Report',
            subledgerLabel: 'AP Subledger Total Balance',
            glLabel: 'AP Control GL Account Balance',
            breakdownKey: 'supplier_breakdown',
            partnerCodeHeading: 'Supplier Code',
            partnerNameHeading: 'Supplier Name',
            codeKey: 'supplier_code',
            nameKey: 'supplier_name'
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function aging(
        array $report,
        string $filename,
        string $title,
        string $partnerGroupKey,
        string $partnerKey,
        string $partnerCodeHeading,
        string $partnerNameHeading,
    ): StreamedResponse {
        return $this->csvReportResponse->stream($filename, function ($handle) use ($report, $title, $partnerGroupKey, $partnerKey, $partnerCodeHeading, $partnerNameHeading): void {
            fputcsv($handle, [$title]);
            fputcsv($handle, ['As Of Date', $report['as_of_date']]);
            fputcsv($handle, ['Currency', $report['currency']]);
            fputcsv($handle, []);
            fputcsv($handle, [$partnerCodeHeading, $partnerNameHeading, 'Document Ref', 'Entry Date', 'Due Date', 'Basis Used', 'Age (Days)', 'Original Amount', 'Allocated Amount', 'Open Balance', 'Bucket']);

            foreach ($report[$partnerGroupKey] as $group) {
                foreach ($group['items'] as $item) {
                    fputcsv($handle, [
                        $group[$partnerKey]['code'],
                        $group[$partnerKey]['name'],
                        $item['reference'],
                        $item['entry_date'],
                        $item['due_date'] ?? 'N/A',
                        $item['basis_used'],
                        $item['age_days'],
                        $this->formatMinor($item['original_amount_minor']),
                        $this->formatMinor($item['allocated_minor']),
                        $this->formatMinor($item['unapplied_minor']),
                        $item['bucket'],
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Grand Totals', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90 Days', 'Total Open Balance']);
            fputcsv($handle, [
                '',
                $this->formatMinor($report['grand_totals']['current']),
                $this->formatMinor($report['grand_totals']['b1_30']),
                $this->formatMinor($report['grand_totals']['b31_60']),
                $this->formatMinor($report['grand_totals']['b61_90']),
                $this->formatMinor($report['grand_totals']['over_90']),
                $this->formatMinor($report['grand_totals']['total']),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function reconciliation(
        array $report,
        string $filename,
        string $title,
        string $subledgerLabel,
        string $glLabel,
        string $breakdownKey,
        string $partnerCodeHeading,
        string $partnerNameHeading,
        string $codeKey,
        string $nameKey,
    ): StreamedResponse {
        return $this->csvReportResponse->stream($filename, function ($handle) use ($report, $title, $subledgerLabel, $glLabel, $breakdownKey, $partnerCodeHeading, $partnerNameHeading, $codeKey, $nameKey): void {
            fputcsv($handle, [$title]);
            fputcsv($handle, ['As Of Date', $report['as_of_date']]);
            fputcsv($handle, ['Currency', $report['currency']]);
            fputcsv($handle, []);
            fputcsv($handle, [$subledgerLabel, $this->formatMinor($report['subledger_total_minor'])]);
            fputcsv($handle, [$glLabel, $this->formatMinor($report['gl_total_minor'])]);
            fputcsv($handle, ['Difference', $this->formatMinor($report['difference_minor'])]);
            fputcsv($handle, ['Reconciled Status', $report['is_reconciled'] ? 'RECONCILED' : 'UNRECONCILED DIFFERENCE']);
            fputcsv($handle, []);
            fputcsv($handle, [$partnerCodeHeading, $partnerNameHeading, 'Subledger Balance']);

            foreach ($report[$breakdownKey] as $row) {
                fputcsv($handle, [
                    $row[$codeKey],
                    $row[$nameKey],
                    $this->formatMinor($row['subledger_balance_minor']),
                ]);
            }
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
