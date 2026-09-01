<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CashFlowReportService;
use App\Application\Reports\FinancialPeriodReportOptions;
use App\Application\Reports\FinancialStatementCsvExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowReportController extends Controller
{
    public function __construct(
        private CashFlowReportService $service,
        private FinancialStatementCsvExporter $csvExporter,
        private FinancialPeriodReportOptions $periodOptions,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $periodId = $request->query('period_id');

        $report = $this->service->generate(
            $fromDate ? (string) $fromDate : null,
            $toDate ? (string) $toDate : null,
            $periodId ? (string) $periodId : null
        );

        return Inertia::render('Reports/CashFlow', [
            'report' => $report,
            'periods' => $this->periodOptions->all(),
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
                'period_id' => $report['period_id'],
            ],
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $periodId = $request->query('period_id');

        $report = $this->service->generate(
            $fromDate ? (string) $fromDate : null,
            $toDate ? (string) $toDate : null,
            $periodId ? (string) $periodId : null
        );

        return $this->csvExporter->cashFlow($report);
    }
}
