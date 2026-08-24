<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\OpeningBalanceService;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\OpeningBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpeningBalanceController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly OpeningBalanceService $openingBalanceService) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $fiscalYears = FiscalYear::query()->orderBy('year', 'desc')->get();
        $selectedYearId = $request->query('fiscal_year_id') ?? $fiscalYears->first()?->id;

        $existingBalances = [];
        if ($selectedYearId) {
            $existingBalances = OpeningBalance::query()
                ->where('fiscal_year_id', $selectedYearId)
                ->get()
                ->keyBy('account_id')
                ->toArray();
        }

        return Inertia::render('Accounting/OpeningBalances', [
            'fiscalYears' => $fiscalYears,
            'selectedYearId' => $selectedYearId,
            'accounts' => Account::query()->with('group')->orderBy('code')->get(),
            'existingBalances' => $existingBalances,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'uuid', 'exists:fiscal_year,id'],
            'balances' => ['required', 'array'],
            'balances.*.account_id' => ['required', 'uuid', 'exists:account,id'],
            'balances.*.debit_minor' => ['required', 'integer', 'min:0'],
            'balances.*.credit_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->openingBalanceService->saveDraft($validated['fiscal_year_id'], $validated['balances'], $request->user()->id);

        return redirect()->back()->with('success', __('Opening balance drafts saved successfully.'));
    }

    public function post(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.post');

        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'uuid', 'exists:fiscal_year,id'],
        ]);

        $journal = $this->openingBalanceService->postOpeningBalances($validated['fiscal_year_id'], $request->user()->id);

        return redirect()->route('accounting.journal.show', $journal->id)->with('success', __('Opening balances posted to ledger successfully.'));
    }
}
