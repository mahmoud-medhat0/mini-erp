<?php

namespace App\Http\Controllers;

use App\Application\Accounting\BankReconciliationPageData;
use App\Application\Accounting\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
        private readonly BankReconciliationPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('BankReconciliations/Index', $this->pageData->indexData([
            'status' => $request->query('status'),
            'bank_account_id' => $request->query('bank_account_id'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'string', 'uuid', 'exists:bank_account,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'statement_reference' => ['nullable', 'string', 'max:100'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'statement_opening_balance_minor' => ['required', 'integer'],
            'statement_closing_balance_minor' => ['required', 'integer'],
        ]);

        $recon = $this->service->createDraft($validated, (int) $request->user()->id);

        return redirect()->route('bank-reconciliations.show', $recon->id)
            ->with('success', __('Bank reconciliation draft created.'));
    }

    public function show(string $id): Response
    {
        return Inertia::render('BankReconciliations/Show', $this->pageData->showData($id));
    }

    public function addLine(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'statement_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'debit_minor' => ['required', 'integer', 'min:0'],
            'credit_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->service->addLine($id, $validated, (int) $request->user()->id);

        return back()->with('success', __('Statement line added.'));
    }

    public function updateLine(Request $request, string $id, string $lineId): RedirectResponse
    {
        $validated = $request->validate([
            'statement_date' => ['sometimes', 'required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'debit_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'credit_minor' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $this->service->updateLine($lineId, $validated, (int) $request->user()->id);

        return back()->with('success', __('Statement line updated.'));
    }

    public function deleteLine(Request $request, string $id, string $lineId): RedirectResponse
    {
        $this->service->deleteLine($lineId, (int) $request->user()->id);

        return back()->with('success', __('Statement line deleted.'));
    }

    public function matchLine(Request $request, string $id, string $lineId): RedirectResponse
    {
        $validated = $request->validate([
            'ledger_entry_id' => ['required', 'string', 'uuid', 'exists:ledger_entry,id'],
        ]);

        $this->service->matchLine($lineId, $validated['ledger_entry_id'], (int) $request->user()->id);

        return back()->with('success', __('Statement line matched to system ledger entry.'));
    }

    public function unmatchLine(Request $request, string $id, string $lineId): RedirectResponse
    {
        $this->service->unmatchLine($lineId, (int) $request->user()->id);

        return back()->with('success', __('Statement line unmatched.'));
    }

    public function finalize(Request $request, string $id): RedirectResponse
    {
        $this->service->finalize($id, (int) $request->user()->id);

        return back()->with('success', __('Bank reconciliation finalized successfully.'));
    }
}
