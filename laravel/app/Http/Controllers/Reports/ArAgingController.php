<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ArAgingReportService;
use App\Application\Reports\ArApCsvReportExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): Response
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $customerId = $request->query('customer_id');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $customerId, $currency);

        return Inertia::render('Reports/ArAging', [
            'report' => $report,
            'customers' => $this->options->activeCustomers(),
            'currencies' => $this->options->currencies(),
            'filters' => [
                'as_of_date' => $asOfDate,
                'customer_id' => $customerId,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $customerId = $request->query('customer_id');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate($asOfDate, $customerId, $currency);

        return $this->csvExporter->arAging($report);
    }
}
