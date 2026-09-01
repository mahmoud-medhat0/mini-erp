<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\OperationalReportDataTableService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierBillReportController extends Controller
{
    public function __construct(private readonly ReportPageOptions $options) {}

    public function index(ReportFilterRequest $request, OperationalReportDataTableService $service): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');
        $productId = $request->query('product_id');
        $currency = $request->query('currency');
        $search = $request->query('search');

        $data = [
            'rows' => [],
            'summary' => $service->supplierBillSummary([
                'date_from' => $dateFrom ? (string) $dateFrom : null,
                'date_to' => $dateTo ? (string) $dateTo : null,
                'status' => $status ? (string) $status : null,
                'customer_id' => null,
                'supplier_id' => $supplierId ? (string) $supplierId : null,
                'product_id' => $productId ? (string) $productId : null,
                'warehouse_id' => null,
                'currency' => $currency ? (string) $currency : null,
                'movement_type' => null,
                'search' => $search ? (string) $search : null,
            ]),
        ];

        return Inertia::render('Reports/SupplierBillsReport', [
            'reportData' => $data,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'status' => $status ?? '',
                'supplier_id' => $supplierId ?? '',
                'product_id' => $productId ?? '',
                'currency' => $currency ?? '',
                'search' => $search ?? '',
            ],
            'suppliers' => $this->options->activeSuppliers(sortBy: 'name', columns: ['id', 'code', 'name']),
            'products' => $this->options->activeProducts(columns: ['id', 'code', 'name']),
            'currencies' => $this->options->currencies(columns: ['code']),
        ]);
    }
}
