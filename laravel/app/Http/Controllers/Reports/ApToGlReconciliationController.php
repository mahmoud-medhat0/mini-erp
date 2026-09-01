<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ApToGlReconciliationReportService;
use App\Application\Reports\ArApCsvReportExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApToGlReconciliationController extends Controller
{
    public function __construct(
        private readonly ApToGlReconciliationReportService $service,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly ArApCsvReportExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->summary($asOfDate, $currency);

        return Inertia::render('Reports/ApGlReconciliation', [
            'report' => $report,
            'currencies' => $this->options->currencies(),
            'filters' => [
                'as_of_date' => $asOfDate,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $currency);

        return $this->csvExporter->apToGlReconciliation($report);
    }
}
