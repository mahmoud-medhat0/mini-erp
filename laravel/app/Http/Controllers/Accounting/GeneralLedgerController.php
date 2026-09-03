<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\GeneralLedgerPageData;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly GeneralLedgerPageData $pageData) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $validated = $request->validate([
            'account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'period_id' => ['nullable', 'uuid', 'exists:financial_period,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return Inertia::render('Accounting/GeneralLedger', $this->pageData->indexData($validated));
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'accounting.view');

        return $this->pageData->datatable($request->all());
    }
}
