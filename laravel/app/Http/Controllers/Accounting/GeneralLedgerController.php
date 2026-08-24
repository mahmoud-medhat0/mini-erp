<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\GeneralLedgerService;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\FinancialPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly GeneralLedgerService $glService) {}

    public function __invoke(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $ledgerData = $this->glService->getGeneralLedger($request->all());

        return Inertia::render('Accounting/GeneralLedger', [
            'ledger' => $ledgerData['entries'],
            'totals' => [
                'debit' => $ledgerData['total_debit'],
                'credit' => $ledgerData['total_credit'],
                'net' => $ledgerData['net_movement'],
            ],
            'accounts' => Account::query()->orderBy('code')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get(),
            'filters' => $request->only(['account_id', 'period_id', 'start_date', 'end_date']),
        ]);
    }
}
