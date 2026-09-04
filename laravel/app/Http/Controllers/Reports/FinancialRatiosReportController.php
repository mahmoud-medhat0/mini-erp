<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\FinancialPeriodReportOptions;
use App\Application\Reports\FinancialRatiosReportService;
use App\Application\Reports\FinancialStatementCsvExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FinancialRatiosReportRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialRatiosReportController extends Controller
{
    public function __construct(
        private readonly FinancialRatiosReportService $service,
        private readonly FinancialStatementCsvExporter $csvExporter,
        private readonly FinancialPeriodReportOptions $periodOptions,
    ) {}

    public function index(FinancialRatiosReportRequest $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $periods = $this->periodOptions->all();
        [$mode, $periodId, $periodIds] = $this->resolveFilters($request, $periods);

        $report = $mode === 'trend'
            ? ($periodIds !== [] ? $this->service->generateTrend($periodIds) : null)
            : ($periodId ? $this->service->generate($periodId) : null);

        return Inertia::render('Reports/FinancialRatios', [
            'report' => $report,
            'periods' => $periods,
            'filters' => [
                'mode' => $mode,
                'period_id' => $periodId,
                'period_ids' => $periodIds,
            ],
        ]);
    }

    public function exportCsv(FinancialRatiosReportRequest $request): StreamedResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $periods = $this->periodOptions->all();
        [$mode, $periodId, $periodIds] = $this->resolveFilters($request, $periods);

        if ($mode === 'trend') {
            if ($periodIds === []) {
                abort(400, __('At least one period is required for a trend export.'));
            }

            $report = $this->service->generateTrend($periodIds);
        } else {
            if (! $periodId) {
                abort(400, __('A period is required for export.'));
            }

            $report = $this->service->generate($periodId);
        }

        return $this->csvExporter->financialRatios($report);
    }

    /**
     * @param  Collection<int, array{id: string}>  $periods
     * @return array{0: string, 1: string|null, 2: list<string>}
     */
    private function resolveFilters(FinancialRatiosReportRequest $request, Collection $periods): array
    {
        $validated = $request->validated();
        $mode = $validated['mode'] ?? 'single';
        $periodIds = $validated['period_ids'] ?? [];
        $periodId = $validated['period_id'] ?? null;

        if ($mode === 'single' && ! $periodId) {
            $periodId = $periods->first()['id'] ?? null;
        }

        return [$mode, $periodId, $periodIds];
    }
}
