<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\PayrollReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayrollReportController extends Controller
{
    public function __construct(
        private readonly PayrollReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
        Gate::authorize('view_payroll');

        return Inertia::render('Reports/Payroll', $this->reportService->indexData($request->only(['employee_id', 'payroll_run_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
        Gate::authorize('view_payroll');

        return $this->reportService->datatable($request->only(['employee_id', 'payroll_run_id']));
    }
}
