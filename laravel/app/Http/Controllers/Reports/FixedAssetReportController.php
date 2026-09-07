<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\FixedAssetCsvReportExporter;
use App\Application\Reports\FixedAssetReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FixedAssetReportDataTableRequest;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedAssetReportController extends Controller
{
    public function __construct(
        private FixedAssetReportService $reportService,
        private FixedAssetCsvReportExporter $csvExporter,
    ) {}

    public function register(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetRegisterReport', [
            'assets' => [],
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function netBookValues(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetNetBookValueReport', [
            'assets' => [],
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function depreciation(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDepreciationReport', [
            'schedules' => [],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function depreciationRuns(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDepreciationRunReport', [
            'runs' => [],
            'filters' => $request->only(['period_id', 'status']),
        ]);
    }

    public function disposals(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDisposalReport', [
            'disposals' => [],
            'filters' => $request->only(['search', 'disposal_type', 'status']),
        ]);
    }

    public function registerData(FixedAssetReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeReportView();

        return $this->reportService->registerDataTable($request->reportFilters());
    }

    public function netBookValueData(FixedAssetReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeReportView();

        return $this->reportService->netBookValueDataTable($request->reportFilters());
    }

    public function depreciationData(FixedAssetReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeReportView();

        return $this->reportService->depreciationScheduleDataTable($request->reportFilters());
    }

    public function depreciationRunData(FixedAssetReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeReportView();

        return $this->reportService->depreciationRunDataTable($request->reportFilters());
    }

    public function disposalData(FixedAssetReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeReportView();

        return $this->reportService->disposalDataTable($request->reportFilters());
    }

    public function exportRegister(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->register($this->filters($request, ['search', 'category_id', 'status']));
    }

    public function exportNetBookValues(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->netBookValues($this->filters($request, ['search', 'category_id', 'status']));
    }

    public function exportDepreciation(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->depreciation($this->filters($request, ['search', 'status']));
    }

    public function exportDepreciationRuns(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->depreciationRuns($this->filters($request, ['period_id', 'status']));
    }

    public function exportDisposals(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->disposals($this->filters($request, ['search', 'disposal_type', 'status']));
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function filters(ReportFilterRequest $request, array $allowed): array
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

    private function authorizeReportExport(ReportFilterRequest $request): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $user = $request->user();
        if (! $user || (! $user->can('reports.export') && ! $user->can('fixedAssets.export'))) {
            abort(403);
        }
    }
}
