<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\OperationalReportDataTableService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderReportController extends Controller
{
    public function __construct(private readonly ReportPageOptions $options) {}

    public function index(ReportFilterRequest $request, OperationalReportDataTableService $service): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $productId = $request->query('product_id');
        $currency = $request->query('currency');
        $search = $request->query('search');

        $data = [
            'rows' => [],
            'summary' => $service->salesOrderSummary([
                'date_from' => $dateFrom ? (string) $dateFrom : null,
                'date_to' => $dateTo ? (string) $dateTo : null,
                'status' => $status ? (string) $status : null,
                'customer_id' => $customerId ? (string) $customerId : null,
                'supplier_id' => null,
                'product_id' => $productId ? (string) $productId : null,
                'warehouse_id' => null,
                'currency' => $currency ? (string) $currency : null,
                'movement_type' => null,
                'search' => $search ? (string) $search : null,
            ]),
        ];

        return Inertia::render('Reports/SalesOrdersReport', [
            'reportData' => $data,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'status' => $status ?? '',
                'customer_id' => $customerId ?? '',
                'product_id' => $productId ?? '',
                'currency' => $currency ?? '',
                'search' => $search ?? '',
            ],
            'customers' => $this->options->activeCustomers(sortBy: 'name', columns: ['id', 'code', 'name']),
            'products' => $this->options->activeProducts(columns: ['id', 'code', 'name']),
            'currencies' => $this->options->currencies(columns: ['code']),
        ]);
    }
}
