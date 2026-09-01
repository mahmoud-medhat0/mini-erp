<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\OperationalReportDataTableService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteReportController extends Controller
{
    public function __construct(private readonly ReportPageOptions $options) {}

    public function index(ReportFilterRequest $request, OperationalReportDataTableService $service): Response
    {
        Gate::authorize('reports.view');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $search = $request->query('search');

        $data = [
            'rows' => [],
            'summary' => $service->deliveryNoteSummary([
                'date_from' => $dateFrom ? (string) $dateFrom : null,
                'date_to' => $dateTo ? (string) $dateTo : null,
                'status' => $status ? (string) $status : null,
                'customer_id' => $customerId ? (string) $customerId : null,
                'supplier_id' => null,
                'product_id' => $productId ? (string) $productId : null,
                'warehouse_id' => $warehouseId ? (string) $warehouseId : null,
                'currency' => null,
                'movement_type' => null,
                'search' => $search ? (string) $search : null,
            ]),
        ];

        return Inertia::render('Reports/DeliveryNotesReport', [
            'reportData' => $data,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'status' => $status ?? '',
                'customer_id' => $customerId ?? '',
                'product_id' => $productId ?? '',
                'warehouse_id' => $warehouseId ?? '',
                'search' => $search ?? '',
            ],
            'customers' => $this->options->activeCustomers(sortBy: 'name', columns: ['id', 'code', 'name']),
            'products' => $this->options->activeProducts(columns: ['id', 'code', 'name']),
            'warehouses' => $this->options->activeWarehouses(
                columns: ['id', 'code', 'name', 'is_default'],
                defaultFirst: true,
            ),
        ]);
    }
}
