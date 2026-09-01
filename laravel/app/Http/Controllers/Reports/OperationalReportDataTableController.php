<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\OperationalReportDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\OperationalReportDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OperationalReportDataTableController extends Controller
{
    public function __construct(private readonly OperationalReportDataTableService $service) {}

    public function salesOrders(OperationalReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->salesOrders($request->reportFilters());
    }

    public function purchaseOrders(OperationalReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->purchaseOrders($request->reportFilters());
    }

    public function deliveryNotes(OperationalReportDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');

        return $this->service->deliveryNotes($request->reportFilters());
    }

    public function goodsReceipts(OperationalReportDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');

        return $this->service->goodsReceipts($request->reportFilters());
    }

    public function customerInvoices(OperationalReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->customerInvoices($request->reportFilters());
    }

    public function supplierBills(OperationalReportDataTableRequest $request): JsonResponse
    {
        $this->authorizeFinancialReport();

        return $this->service->supplierBills($request->reportFilters());
    }

    public function stockMovements(OperationalReportDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');

        return $this->service->stockMovements($request->reportFilters());
    }

    private function authorizeFinancialReport(): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
    }
}
