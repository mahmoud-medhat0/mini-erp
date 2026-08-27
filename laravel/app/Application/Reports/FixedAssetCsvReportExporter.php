<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedAssetCsvReportExporter
{
    public function __construct(
        private readonly FixedAssetReportService $reportService,
        private readonly CsvReportResponse $csvReportResponse,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function register(array $filters): StreamedResponse
    {
        return $this->csvReportResponse->fromRows(
            'fixed_asset_register_report.csv',
            ['Asset Number', 'Name', 'Category Code', 'Currency', 'Cost Minor', 'Opening Accumulated Depreciation Minor', 'Posted Accumulated Depreciation Minor', 'Total Accumulated Depreciation Minor', 'Net Book Value Minor', 'Status'],
            $this->reportService->allRegisterRows($filters),
            fn (array $row): array => [
                $row['asset_number'],
                $this->englishName($row['name']),
                $row['category']['code'] ?? '',
                $row['currency'],
                $row['cost_minor'],
                $row['opening_accumulated_depreciation_minor'],
                $row['posted_accumulated_depreciation_minor'],
                $row['total_accumulated_depreciation_minor'],
                $row['net_book_value_minor'],
                $row['status'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function netBookValues(array $filters): StreamedResponse
    {
        return $this->csvReportResponse->fromRows(
            'fixed_asset_net_book_value_report.csv',
            ['Asset Number', 'Name', 'Currency', 'Cost Minor', 'Total Accumulated Depreciation Minor', 'Net Book Value Minor', 'Status'],
            $this->reportService->allNetBookValueRows($filters),
            fn (array $row): array => [
                $row['asset_number'],
                $this->englishName($row['name']),
                $row['currency'],
                $row['cost_minor'],
                $row['total_accumulated_depreciation_minor'],
                $row['net_book_value_minor'],
                $row['status'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function depreciation(array $filters): StreamedResponse
    {
        return $this->csvReportResponse->fromRows(
            'fixed_asset_depreciation_schedule_report.csv',
            ['Asset Number', 'Name', 'Period Number', 'Period Start Date', 'Period End Date', 'Depreciation Minor', 'Accumulated Depreciation Minor', 'Net Book Value Minor', 'Status', 'Run Number', 'Journal Number'],
            $this->reportService->allDepreciationScheduleRows($filters),
            fn (array $row): array => [
                $row['asset']['asset_number'] ?? '',
                $this->englishName($row['asset']['name'] ?? null),
                $row['period_number'],
                $row['period_start_date'],
                $row['period_end_date'],
                $row['depreciation_minor'],
                $row['accumulated_depreciation_minor'],
                $row['net_book_value_minor'],
                $row['status'],
                $row['depreciation_run_number'],
                $row['journal_number'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function depreciationRuns(array $filters): StreamedResponse
    {
        return $this->csvReportResponse->fromRows(
            'fixed_asset_depreciation_run_history_report.csv',
            ['Run Number', 'Run Date', 'Financial Year', 'Financial Month', 'Asset Count', 'Total Depreciation Minor', 'Status', 'Journal Number'],
            $this->reportService->allDepreciationRunRows($filters),
            fn (array $row): array => [
                $row['number'],
                $row['run_date'],
                $row['financial_period']['year'] ?? '',
                $row['financial_period']['month'] ?? '',
                $row['asset_count'],
                $row['total_depreciation_minor'],
                $row['status'],
                $row['journal_number'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function disposals(array $filters): StreamedResponse
    {
        return $this->csvReportResponse->fromRows(
            'fixed_asset_disposal_history_report.csv',
            ['Disposal Number', 'Asset Number', 'Name', 'Disposal Date', 'Disposal Type', 'Proceeds Minor', 'Net Book Value Minor', 'Gain Minor', 'Loss Minor', 'Status', 'Journal Number'],
            $this->reportService->allDisposalRows($filters),
            fn (array $row): array => [
                $row['number'],
                $row['asset']['asset_number'] ?? '',
                $this->englishName($row['asset']['name'] ?? null),
                $row['disposal_date'],
                $row['disposal_type'],
                $row['proceeds_minor'],
                $row['net_book_value_minor'],
                $row['gain_minor'],
                $row['loss_minor'],
                $row['status'],
                $row['journal_number'],
            ]
        );
    }

    private function englishName(array|string|null $value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value) ?: '');
        }

        return (string) ($value ?? '');
    }
}
