<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ArApReconciliationDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ArApReconciliationDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ArApReconciliationDataTableController extends Controller
{
    public function __construct(
        private readonly ArApReconciliationDataTableService $service,
    ) {}

    public function accountsReceivable(ArApReconciliationDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->accountsReceivable($request->reportFilters());
    }

    public function accountsPayable(ArApReconciliationDataTableRequest $request): JsonResponse
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
