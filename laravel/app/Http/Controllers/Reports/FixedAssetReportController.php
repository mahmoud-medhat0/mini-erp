<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\FixedAssetCsvReportExporter;
use App\Application\Reports\FixedAssetReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
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
            'assets' => $this->reportService->register($this->filters($request, ['search', 'category_id', 'status'])),
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function netBookValues(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetNetBookValueReport', [
            'assets' => $this->reportService->netBookValues($this->filters($request, ['search', 'category_id', 'status'])),
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function depreciation(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDepreciationReport', [
            'schedules' => $this->reportService->depreciationSchedule($this->filters($request, ['search', 'status'])),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function depreciationRuns(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDepreciationRunReport', [
            'runs' => $this->reportService->depreciationRuns($this->filters($request, ['period_id', 'status'])),
            'filters' => $request->only(['period_id', 'status']),
        ]);
    }

    public function disposals(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/FixedAssetDisposalReport', [
            'disposals' => $this->reportService->disposals($this->filters($request, ['search', 'disposal_type', 'status'])),
            'filters' => $request->only(['search', 'disposal_type', 'status']),
        ]);
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
