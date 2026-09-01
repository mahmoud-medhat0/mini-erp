<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\AgingReportDataTableService;
use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ArApCsvReportExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
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
        private readonly AgingReportDataTableService $dataTableService,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        $asOfDate = (string) ($validated['as_of_date'] ?? date('Y-m-d'));
        $supplierId = $validated['supplier_id'] ?? null;
        $currency = $this->currencyResolver->resolve($validated['currency'] ?? null);
        $filters = [
            'as_of_date' => $asOfDate,
            'supplier_id' => $supplierId,
            'currency' => $currency,
        ];

        return Inertia::render('Reports/ApAging', [
            'report' => [
                'as_of_date' => $asOfDate,
                'currency' => $currency,
                'grand_totals' => $this->dataTableService->accountsPayableSummary($filters),
            ],
            'suppliers' => $this->options->activeSuppliers(),
            'currencies' => $this->options->currencies(),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $supplierId = $request->query('supplier_id');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $supplierId, $currency);

        return $this->csvExporter->apAging($report);
    }
}
