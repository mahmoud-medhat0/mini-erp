<?php

namespace App\Http\Controllers\Reports;

use App\Application\Accounting\CashBookQueryService;
use App\Application\Reports\CashBankBookCsvExporter;
use App\Application\Reports\CashBankBookDataTableService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashBookController extends Controller
{
    public function __construct(
        private readonly CashBookQueryService $queryService,
        private readonly CashBankBookCsvExporter $csvExporter,
        private readonly CashBankBookDataTableService $dataTableService,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $cashAccounts = $this->options->activeCashAccounts();
        $cashAccountId = $request->query('cash_account_id', $cashAccounts->first()?->id);
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $reportData = null;
        if ($cashAccountId) {
            $reportData = $this->dataTableService->cashSummary($cashAccountId, $dateFrom, $dateTo);
        }

        return Inertia::render('Reports/CashBook', [
            'report' => $reportData,
            'cashAccounts' => $cashAccounts,
            'filters' => [
                'cash_account_id' => $cashAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        $cashAccountId = $request->query('cash_account_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        if (! $cashAccountId) {
            abort(400, __('Cash account ID is required for export.'));
        }

        $report = $this->queryService->getStatement($cashAccountId, $dateFrom, $dateTo);

        return $this->csvExporter->cash($report);
    }
}
