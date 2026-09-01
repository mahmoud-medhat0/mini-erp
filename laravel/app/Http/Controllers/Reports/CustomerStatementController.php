<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CustomerStatementReportService;
use App\Application\Reports\PartnerStatementCsvExporter;
use App\Application\Reports\PartnerStatementDataTableService;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
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
        private readonly PartnerStatementDataTableService $dataTableService,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $validated = $request->validated();
        $customerId = $validated['customer_id'] ?? null;
        $dateFrom = (string) ($validated['date_from'] ?? date('Y-01-01'));
        $dateTo = (string) ($validated['date_to'] ?? date('Y-m-d'));
        $currency = $this->currencyResolver->resolve($validated['currency'] ?? null);
        $filters = [
            'customer_id' => $customerId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
        ];

        $reportData = null;
        if ($customerId) {
            $reportData = $this->dataTableService->customerSummary($filters);
        }

        return Inertia::render('Reports/CustomerStatement', [
            'report' => $reportData,
            'customers' => $this->options->activeCustomers(),
            'currencies' => $this->options->currencies(),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
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
