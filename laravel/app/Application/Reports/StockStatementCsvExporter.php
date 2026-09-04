<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class StockStatementCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /** @param  array<string, mixed>  $report */
    public function product(array $report): StreamedResponse
    {
        return $this->statement(
            report: $report,
            filename: $this->csvReportResponse->safeFilename('product_statement_'.$report['entity']['code'].'.csv'),
            title: 'Product Account Statement',
            entityLabel: 'Product',
        );
    }

    /** @param  array<string, mixed>  $report */
    public function warehouse(array $report): StreamedResponse
    {
        return $this->statement(
            report: $report,
            filename: $this->csvReportResponse->safeFilename('warehouse_statement_'.$report['entity']['code'].'.csv'),
            title: 'Warehouse Account Statement',
            entityLabel: 'Warehouse',
        );
    }

    /** @param  array<string, mixed>  $report */
    private function statement(array $report, string $filename, string $title, string $entityLabel): StreamedResponse
    {
        return $this->csvReportResponse->stream($filename, function ($handle) use ($report, $title, $entityLabel): void {
            $singleProduct = (bool) $report['single_product'];

            $this->csvReportResponse->writeRow($handle, [$title]);
            $this->csvReportResponse->writeRow($handle, [$entityLabel, $report['entity']['code'].' - '.$this->localizedExportName($report['entity']['name'])]);
            $this->csvReportResponse->writeRow($handle, ['Period', $report['filters']['date_from'].' to '.$report['filters']['date_to']]);
            $this->csvReportResponse->writeRow($handle, ['Currency', $report['filters']['currency']]);
            $this->csvReportResponse->writeRow($handle, []);
            $this->csvReportResponse->writeRow($handle, [
                'Opening Balance Quantity',
                $singleProduct ? $this->formatQuantity($report['opening_balance_quantity_e6']) : 'Mixed units',
            ]);
            $this->csvReportResponse->writeRow($handle, ['Opening Balance Value', $this->formatMinor($report['opening_balance_value_minor'])]);
            $this->csvReportResponse->writeRow($handle, []);
            $this->csvReportResponse->writeRow($handle, [
                'Date', 'Type', 'Reference', 'Description', 'Product', 'Warehouse',
                'Qty Delta', 'Value Delta', 'Balance Qty', 'Balance Value',
            ]);

            foreach ($report['lines'] as $line) {
                $this->csvReportResponse->writeRow($handle, [
                    $line['date'],
                    $line['type'],
                    $line['reference'],
                    $line['description'],
                    trim($line['product_code'].' '.$this->localizedExportName($line['product_name'])),
                    trim($line['warehouse_code'].' '.$this->localizedExportName($line['warehouse_name'])),
                    $this->formatQuantity($line['quantity_delta_e6']),
                    $this->formatMinor($line['value_delta_minor']),
                    $line['balance_quantity_e6'] === null ? '—' : $this->formatQuantity($line['balance_quantity_e6']),
                    $this->formatMinor($line['balance_valuation_amount_minor']),
                ]);
            }

            $this->csvReportResponse->writeRow($handle, []);
            $this->csvReportResponse->writeRow($handle, [
                'Totals', '', '', '', '', '',
                $singleProduct ? $this->formatQuantity($report['total_in_quantity_e6'] - $report['total_out_quantity_e6']) : 'Mixed units',
                $this->formatMinor($report['total_in_value_minor'] - $report['total_out_value_minor']),
                $singleProduct ? $this->formatQuantity($report['closing_balance_quantity_e6']) : '—',
                $this->formatMinor($report['closing_balance_value_minor']),
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

    private function formatQuantity(mixed $quantityE6): string
    {
        $quantity = (int) $quantityE6;
        $sign = $quantity < 0 ? '-' : '';
        $absolute = abs($quantity);
        $whole = intdiv($absolute, 1_000_000);
        $fraction = rtrim(str_pad((string) ($absolute % 1_000_000), 6, '0', STR_PAD_LEFT), '0');

        return $sign.$whole.($fraction !== '' ? '.'.$fraction : '');
    }

    private function localizedExportName(mixed $name): string
    {
        if (is_array($name)) {
            return (string) ($name['en'] ?? reset($name) ?: '');
        }

        return (string) ($name ?? '');
    }
}
