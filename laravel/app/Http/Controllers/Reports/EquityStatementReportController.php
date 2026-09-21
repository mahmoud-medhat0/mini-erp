<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\EquityStatementReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EquityStatementReportController extends Controller
{
    public function __construct(
        private readonly EquityStatementReportService $equityStatementReportService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $report = $this->equityStatementReportService->generate(
            $fromDate ? (string) $fromDate : null,
            $toDate ? (string) $toDate : null,
        );

        return Inertia::render('Reports/EquityStatement', [
            'report' => $report,
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
            ],
        ]);
    }
}
