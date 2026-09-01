<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ChequeRegisterCsvExporter;
use App\Application\Reports\ChequeRegisterDataTableService;
use App\Application\Reports\ChequeRegisterReportService;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChequeRegisterReportController extends Controller
{
    public function __construct(
        private readonly ChequeRegisterReportService $service,
        private readonly ChequeRegisterDataTableService $dataTableService,
        private readonly ReportCurrencyResolver $currencyResolver,
        private readonly ChequeRegisterCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $direction = $request->query('direction', 'all');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $supplierId = $request->query('supplier_id');
        $bankAccountId = $request->query('bank_account_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->dataTableService->summary([
            'direction' => $direction,
            'status' => $status,
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'bank_account_id' => $bankAccountId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
        ]);

        return Inertia::render('Reports/ChequeRegister', [
            'report' => $report,
            'customers' => $this->options->activeCustomers(),
            'suppliers' => $this->options->activeSuppliers(),
            'bankAccounts' => $this->options->activeBankAccounts(),
            'currencies' => $this->options->currencies(),
            'filters' => [
                'direction' => $direction,
                'status' => $status,
                'customer_id' => $customerId,
                'supplier_id' => $supplierId,
                'bank_account_id' => $bankAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $direction = $request->query('direction', 'all');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $supplierId = $request->query('supplier_id');
        $bankAccountId = $request->query('bank_account_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $currency = $this->currencyResolver->resolve($request->query('currency'));

        $report = $this->service->generate(
            $direction,
            $status,
            $customerId,
            $supplierId,
            $bankAccountId,
            $dateFrom,
            $dateTo,
            $currency
        );

        return $this->csvExporter->export($report);
    }
}
