<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\BankReconciliationReportService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationReportController extends Controller
{
    public function __construct(
        private readonly BankReconciliationReportService $service,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $bankAccountId = $request->query('bank_account_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Keep a single compatibility row in the initial Inertia contract; the
        // visible report history is loaded through the bounded server feed.
        $report = $this->service->generateIndex($bankAccountId, $status, $dateFrom, $dateTo, 1);

        return Inertia::render('Reports/BankReconciliation', [
            'report' => $report,
            'bankAccounts' => $this->options->activeBankAccounts(),
            'filters' => [
                'bank_account_id' => $bankAccountId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'bank_account_id' => ['nullable', 'uuid', 'exists:bank_account,id'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'in_progress', 'reconciled'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        return $this->service->indexDataTable($filters);
    }

    public function show(string $id): Response
    {
        $detail = $this->service->generateDetail($id, false);

        return Inertia::render('Reports/BankReconciliationDetail', [
            'detail' => $detail,
        ]);
    }

    public function detailData(string $id): JsonResponse
    {
        return $this->service->detailDataTable($id);
    }
}
