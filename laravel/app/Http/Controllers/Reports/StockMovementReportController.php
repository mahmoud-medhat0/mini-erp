<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ReportPageOptions;
use App\Application\Reports\StockMovementReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementReportController extends Controller
{
    public function __construct(private readonly ReportPageOptions $options) {}

    public function index(Request $request, StockMovementReportService $service): Response
    {
        Gate::authorize('reports.view');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $movementType = $request->query('movement_type');
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $currency = $request->query('currency');
        $search = $request->query('search');

        $data = $service->generate(
            dateFrom: $dateFrom ? (string) $dateFrom : null,
            dateTo: $dateTo ? (string) $dateTo : null,
            movementType: $movementType ? (string) $movementType : null,
            productId: $productId ? (string) $productId : null,
            warehouseId: $warehouseId ? (string) $warehouseId : null,
            currency: $currency ? (string) $currency : null,
            search: $search ? (string) $search : null
        );

        return Inertia::render('Reports/StockMovementsReport', [
            'reportData' => $data,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'movement_type' => $movementType ?? '',
                'product_id' => $productId ?? '',
                'warehouse_id' => $warehouseId ?? '',
                'currency' => $currency ?? '',
                'search' => $search ?? '',
            ],
            'products' => $this->options->activeProducts(columns: ['id', 'code', 'name']),
            'warehouses' => $this->options->activeWarehouses(withBranch: true),
            'currencies' => $this->options->currencies(columns: ['code']),
        ]);
    }
}
