<?php

namespace App\Http\Controllers\Reports;

use App\Application\Accounting\BankBookQueryService;
use App\Application\Reports\CashBankBookCsvExporter;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankBookController extends Controller
{
    public function __construct(
        private readonly BankBookQueryService $queryService,
        private readonly CashBankBookCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(Request $request): Response
    {
        $bankAccounts = $this->options->activeBankAccounts();
        $bankAccountId = $request->query('bank_account_id', $bankAccounts->first()?->id);
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $reportData = null;
        if ($bankAccountId) {
            $reportData = $this->queryService->getStatement($bankAccountId, $dateFrom, $dateTo);
        }

        return Inertia::render('Reports/BankBook', [
            'report' => $reportData,
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'bank_account_id' => $bankAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $bankAccountId = $request->query('bank_account_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        if (! $bankAccountId) {
            abort(400, __('Bank account ID is required for export.'));
        }

        $report = $this->queryService->getStatement($bankAccountId, $dateFrom, $dateTo);

        return $this->csvExporter->bank($report);
    }
}
