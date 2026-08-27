<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\BalanceSheetReportService;
use App\Application\Reports\FinancialStatementCsvExporter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetReportController extends Controller
{
    public function __construct(
        private BalanceSheetReportService $service,
        private FinancialStatementCsvExporter $csvExporter,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $asOfDate = (string) $request->query('as_of_date', date('Y-m-d'));
        $report = $this->service->generate($asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $report,
            'filters' => [
                'as_of_date' => $asOfDate,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $asOfDate = (string) $request->query('as_of_date', date('Y-m-d'));
        $report = $this->service->generate($asOfDate);

        return $this->csvExporter->balanceSheet($report);
    }
}
