<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CashBankBookDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\CashBankBookDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CashBankBookDataTableController extends Controller
{
    public function __construct(
        private readonly CashBankBookDataTableService $service,
    ) {}

    public function cashBook(CashBankBookDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->cashBook($request->reportFilters());
    }

    public function bankBook(CashBankBookDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->bankBook($request->reportFilters());
    }

    private function authorizeFinancialReport(): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
    }
}
