<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Application\Reports\StockStatementCsvExporter;
use App\Application\Reports\StockStatementReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseStatementController extends Controller
{
    public function __construct(
        private readonly StockStatementReportService $service,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly StockStatementCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        $warehouseId = $validated['warehouse_id'] ?? null;
        $productId = $validated['product_id'] ?? null;
        $dateFrom = (string) ($validated['date_from'] ?? date('Y-01-01'));
        $dateTo = (string) ($validated['date_to'] ?? date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($validated['currency'] ?? null);
        $filters = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
        ];

        $reportData = $warehouseId ? $this->service->warehouseSummary($filters) : null;

        return Inertia::render('Reports/WarehouseStatement', [
            'report' => $reportData,
            'products' => $this->options->activeProducts(columns: ['id', 'code', 'name']),
            'warehouses' => $this->options->activeWarehouses(withBranch: true),
            'currencies' => $this->options->currencies(columns: ['code']),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $productId = $request->query('product_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        if (! $warehouseId) {
            abort(400, __('Warehouse ID is required for export.'));
        }

        $report = $this->service->generate('warehouse', [
            'product_id' => $productId ?: null,
            'warehouse_id' => $warehouseId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
        ]);

        return $this->csvExporter->warehouse($report);
    }
}
