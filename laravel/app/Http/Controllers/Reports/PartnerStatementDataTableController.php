<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\PartnerStatementDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\PartnerStatementDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PartnerStatementDataTableController extends Controller
{
    public function __construct(
        private readonly PartnerStatementDataTableService $service,
    ) {}

    public function customer(PartnerStatementDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->customer($request->reportFilters());
    }

    public function supplier(PartnerStatementDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->supplier($request->reportFilters());
    }

    private function authorizeFinancialReport(): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
    }
}
