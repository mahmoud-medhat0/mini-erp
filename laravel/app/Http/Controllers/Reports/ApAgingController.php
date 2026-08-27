<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ArApCsvReportExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApAgingController extends Controller
{
    public function __construct(
        private readonly ApAgingReportService $service,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly ArApCsvReportExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(Request $request): Response
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $supplierId = $request->query('supplier_id');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $supplierId, $currency);

        return Inertia::render('Reports/ApAging', [
            'report' => $report,
            'suppliers' => $this->options->activeSuppliers(),
            'currencies' => $this->options->currencies(),
            'filters' => [
                'as_of_date' => $asOfDate,
                'supplier_id' => $supplierId,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $supplierId = $request->query('supplier_id');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $supplierId, $currency);

        return $this->csvExporter->apAging($report);
    }
}
