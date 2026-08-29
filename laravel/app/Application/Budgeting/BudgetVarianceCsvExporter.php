<?php

namespace App\Application\Budgeting;

use App\Application\Reports\CsvReportResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetVarianceCsvExporter
{
    public function __construct(private readonly CsvReportResponse $csvReportResponse) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report): StreamedResponse
    {
        $budgetCode = $report['selected_budget']['code'] ?? 'NO_BUDGET';
        $filename = "budget_variance_{$budgetCode}_".date('Y-m-d').'.csv';

        return $this->csvReportResponse->stream(
            $filename,
            function ($handle) use ($report): void {
                fputcsv($handle, [
                    'budget_code',
                    'budget_version',
                    'fiscal_year',
                    'period_label',
                    'account_code',
                    'account_name',
                    'project',
                    'cost_center',
                    'currency',
                    'budget_minor',
                    'actual_minor',
                    'variance_minor',
                    'variance_percent_bps',
                    'row_type',
                ]);

                $budget = $report['selected_budget'];
                $budgetCodeStr = $budget['code'] ?? '';
                $budgetVersionStr = $budget['version_code'] ?? '';
                $fiscalYearStr = isset($budget['fiscal_year']) ? (string) $budget['fiscal_year'] : '';

                foreach ($report['rows'] as $row) {
                    $periodLabel = isset($row['period_month']) ? "Month {$row['period_month']}" : '';

                    $projectStr = '';
                    if (! empty($row['project_code'])) {
                        $pName = $this->localizedExportName($row['project_name']);
                        $projectStr = $pName !== '' ? "{$row['project_code']} - {$pName}" : $row['project_code'];
                    }

                    $costCenterStr = '';
                    if (! empty($row['cost_center_code'])) {
                        $ccName = $this->localizedExportName($row['cost_center_name']);
                        $costCenterStr = $ccName !== '' ? "{$row['cost_center_code']} - {$ccName}" : $row['cost_center_code'];
                    }

                    fputcsv($handle, [
                        $budgetCodeStr,
                        $budgetVersionStr,
                        $fiscalYearStr,
                        $periodLabel,
                        $row['account_code'],
                        $this->localizedExportName($row['account_name']),
                        $projectStr,
                        $costCenterStr,
                        $row['currency'],
                        $row['budget_minor'],
                        $row['actual_minor'],
                        $row['variance_minor'],
                        $row['variance_percent_bps'] ?? '',
                        $row['row_type'],
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
