<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CustomerStatementReportService;
use App\Application\Reports\PartnerStatementCsvExporter;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerStatementReportService $service,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly PartnerStatementCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(Request $request): Response
    {
        $customerId = $request->query('customer_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $reportData = null;
        if ($customerId) {
            $reportData = $this->service->generate($customerId, $dateFrom, $dateTo, $currency);
        }

        return Inertia::render('Reports/CustomerStatement', [
            'report' => $reportData,
            'customers' => $this->options->activeCustomers(),
            'currencies' => $this->options->currencies(),
            'filters' => [
                'customer_id' => $customerId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $customerId = $request->query('customer_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        if (! $customerId) {
            abort(400, __('Customer ID is required for export.'));
        }

        $report = $this->service->generate($customerId, $dateFrom, $dateTo, $currency);

        return $this->csvExporter->customer($report);
    }
}
