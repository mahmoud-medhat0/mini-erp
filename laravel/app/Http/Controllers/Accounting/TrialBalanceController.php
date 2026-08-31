<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\GeneralLedgerService;
use App\Application\Reports\FinancialPeriodReportOptions;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(
        private readonly GeneralLedgerService $glService,
        private readonly FinancialPeriodReportOptions $periodOptions,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $tbData = $this->glService->getTrialBalance($request->all());

        return Inertia::render('Accounting/TrialBalance', [
            'rows' => $tbData['rows'],
            'totals' => [
                'debit' => $tbData['total_debit'],
                'credit' => $tbData['total_credit'],
                'is_balanced' => $tbData['is_balanced'],
            ],
            'displayCurrency' => $tbData['display_currency'],
            'periods' => $this->periodOptions->all(),
            'filters' => $request->only(['period_id', 'start_date', 'end_date', 'include_zero']),
        ]);
    }
}
