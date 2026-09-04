<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\StockStatementReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StockStatementDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StockStatementDataTableController extends Controller
{
    public function __construct(
        private readonly StockStatementReportService $service,
    ) {}

    public function product(StockStatementDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');

        return $this->service->product($request->reportFilters());
    }

    public function warehouse(StockStatementDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');

        return $this->service->warehouse($request->reportFilters());
    }
}
