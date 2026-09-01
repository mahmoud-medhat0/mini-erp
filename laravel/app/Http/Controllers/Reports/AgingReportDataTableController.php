<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\AgingReportDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AgingReportDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AgingReportDataTableController extends Controller
{
    public function __construct(
        private readonly AgingReportDataTableService $service,
    ) {}

    public function accountsReceivable(AgingReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->accountsReceivable($request->reportFilters());
    }

    public function accountsPayable(AgingReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->accountsPayable($request->reportFilters());
    }

    private function authorizeFinancialReport(): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
    }
}
