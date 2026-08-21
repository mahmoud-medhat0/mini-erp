<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\BankReconciliationReportService;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationReportController extends Controller
{
    public function __construct(
        private readonly BankReconciliationReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $bankAccountId = $request->query('bank_account_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $report = $this->service->generateIndex($bankAccountId, $status, $dateFrom, $dateTo);

        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('code')->get();

        return Inertia::render('Reports/BankReconciliation', [
            'report' => $report,
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'bank_account_id' => $bankAccountId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function show(string $id): Response
    {
        $detail = $this->service->generateDetail($id);

        return Inertia::render('Reports/BankReconciliationDetail', [
            'detail' => $detail,
        ]);
    }
}
