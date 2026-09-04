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

class ProductStatementController extends Controller
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
        $productId = $validated['product_id'] ?? null;
        $warehouseId = $validated['warehouse_id'] ?? null;
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

        $reportData = $productId ? $this->service->productSummary($filters) : null;

        return Inertia::render('Reports/ProductStatement', [
            'report' => $reportData,
            'products' => $this->options->activeProducts(columns: ['id', 'code', 'name']),
            'warehouses' => $this->options->activeWarehouses(withBranch: true),
            'currencies' => $this->options->currencies(columns: ['code']),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        if (! $productId) {
            abort(400, __('Product ID is required for export.'));
        }

        $report = $this->service->generate('product', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId ?: null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
        ]);

        return $this->csvExporter->product($report);
    }
}
