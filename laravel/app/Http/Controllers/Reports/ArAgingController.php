<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\AgingReportDataTableService;
use App\Application\Reports\ArAgingReportService;
use App\Application\Reports\ArApCsvReportExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArAgingController extends Controller
{
    public function __construct(
        private readonly ArAgingReportService $service,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly ArApCsvReportExporter $csvExporter,
        private readonly ReportPageOptions $options,
        private readonly AgingReportDataTableService $dataTableService,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        $asOfDate = (string) ($validated['as_of_date'] ?? date('Y-m-d'));
        $customerId = $validated['customer_id'] ?? null;
        $currency = $this->currencyResolver->resolve($validated['currency'] ?? null);
        $filters = [
            'as_of_date' => $asOfDate,
            'customer_id' => $customerId,
            'currency' => $currency,
        ];

        return Inertia::render('Reports/ArAging', [
            'report' => [
                'as_of_date' => $asOfDate,
                'currency' => $currency,
                'grand_totals' => $this->dataTableService->accountsReceivableSummary($filters),
            ],
            'customers' => $this->options->activeCustomers(),
            'currencies' => $this->options->currencies(),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $customerId = $request->query('customer_id');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $customerId, $currency);

        return $this->csvExporter->arAging($report);
    }
}
