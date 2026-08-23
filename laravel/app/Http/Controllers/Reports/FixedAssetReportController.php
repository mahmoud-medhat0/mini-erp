<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\FixedAssetReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedAssetReportController extends Controller
{
    public function __construct(
        private FixedAssetReportService $reportService
    ) {}

    public function register(Request $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetRegisterReport', [
            'assets' => $this->reportService->register($this->filters($request, ['search', 'category_id', 'status'])),
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function netBookValues(Request $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetNetBookValueReport', [
            'assets' => $this->reportService->netBookValues($this->filters($request, ['search', 'category_id', 'status'])),
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function depreciation(Request $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDepreciationReport', [
            'schedules' => $this->reportService->depreciationSchedule($this->filters($request, ['search', 'status'])),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function depreciationRuns(Request $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDepreciationRunReport', [
            'runs' => $this->reportService->depreciationRuns($this->filters($request, ['period_id', 'status'])),
            'filters' => $request->only(['period_id', 'status']),
        ]);
    }

    public function disposals(Request $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDisposalReport', [
            'disposals' => $this->reportService->disposals($this->filters($request, ['search', 'disposal_type', 'status'])),
            'filters' => $request->only(['search', 'disposal_type', 'status']),
        ]);
    }

    public function exportRegister(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvResponse(
            'fixed_asset_register_report.csv',
            ['Asset Number', 'Name', 'Category Code', 'Currency', 'Cost Minor', 'Opening Accumulated Depreciation Minor', 'Posted Accumulated Depreciation Minor', 'Total Accumulated Depreciation Minor', 'Net Book Value Minor', 'Status'],
            $this->reportService->allRegisterRows($this->filters($request, ['search', 'category_id', 'status'])),
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

    public function exportNetBookValues(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvResponse(
            'fixed_asset_net_book_value_report.csv',
            ['Asset Number', 'Name', 'Currency', 'Cost Minor', 'Total Accumulated Depreciation Minor', 'Net Book Value Minor', 'Status'],
            $this->reportService->allNetBookValueRows($this->filters($request, ['search', 'category_id', 'status'])),
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

    public function exportDepreciation(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvResponse(
            'fixed_asset_depreciation_schedule_report.csv',
            ['Asset Number', 'Name', 'Period Number', 'Period Start Date', 'Period End Date', 'Depreciation Minor', 'Accumulated Depreciation Minor', 'Net Book Value Minor', 'Status', 'Run Number', 'Journal Number'],
            $this->reportService->allDepreciationScheduleRows($this->filters($request, ['search', 'status'])),
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

    public function exportDepreciationRuns(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvResponse(
            'fixed_asset_depreciation_run_history_report.csv',
            ['Run Number', 'Run Date', 'Financial Year', 'Financial Month', 'Asset Count', 'Total Depreciation Minor', 'Status', 'Journal Number'],
            $this->reportService->allDepreciationRunRows($this->filters($request, ['period_id', 'status'])),
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

    public function exportDisposals(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvResponse(
            'fixed_asset_disposal_history_report.csv',
            ['Disposal Number', 'Asset Number', 'Name', 'Disposal Date', 'Disposal Type', 'Proceeds Minor', 'Net Book Value Minor', 'Gain Minor', 'Loss Minor', 'Status', 'Journal Number'],
            $this->reportService->allDisposalRows($this->filters($request, ['search', 'disposal_type', 'status'])),
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

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $allowed): array
    {
        return array_filter(
            $request->only($allowed),
            fn ($value): bool => $value !== null && $value !== ''
        );
    }

    private function authorizeReportView(): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
    }

    private function authorizeReportExport(Request $request): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $user = $request->user();
        if (! $user || (! $user->can('reports.export') && ! $user->can('fixedAssets.export'))) {
            abort(403);
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private function csvResponse(string $filename, array $headers, iterable $rows, callable $rowMapper): StreamedResponse
    {
        return response()->stream(function () use ($headers, $rows, $rowMapper): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $rowMapper($row));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function englishName(array|string|null $value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value) ?: '');
        }

        return (string) ($value ?? '');
    }
}
