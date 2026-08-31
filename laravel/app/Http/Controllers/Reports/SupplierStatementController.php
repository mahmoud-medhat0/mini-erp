<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\PartnerStatementCsvExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Application\Reports\SupplierStatementReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierStatementController extends Controller
{
    public function __construct(
        private readonly SupplierStatementReportService $service,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly PartnerStatementCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $reportData = null;
        if ($supplierId) {
            $reportData = $this->service->generate($supplierId, $dateFrom, $dateTo, $currency);
        }

        return Inertia::render('Reports/SupplierStatement', [
            'report' => $reportData,
            'suppliers' => $this->options->activeSuppliers(),
            'currencies' => $this->options->currencies(),
            'filters' => [
                'supplier_id' => $supplierId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $supplierId = $request->query('supplier_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        if (! $supplierId) {
            abort(400, __('Supplier ID is required for export.'));
        }

        $report = $this->service->generate($supplierId, $dateFrom, $dateTo, $currency);

        return $this->csvExporter->supplier($report);
    }
}
