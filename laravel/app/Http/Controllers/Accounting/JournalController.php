<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\JournalPageData;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(
        private readonly JournalDraftService $draftService,
        private readonly PostingEngine $postingEngine,
        private readonly ReversalService $reversalService,
        private readonly JournalPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/GeneralJournal', $this->pageData->indexData($request->all()));
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.create');

        return Inertia::render('Accounting/JournalForm', $this->pageData->createData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'financial_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:account,id'],
            'lines.*.branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'lines.*.debit_minor' => ['required', 'integer', 'min:0'],
            'lines.*.credit_minor' => ['required', 'integer', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->draftService->createDraft($validated, $validated['lines'], $request->user()->id);

        return redirect()->route('accounting.journal.show', $entry->id)->with('success', __('Journal draft created successfully.'));
    }

    public function show(Request $request, JournalEntry $journalEntry): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/JournalDetail', $this->pageData->showData($journalEntry));
    }

    public function submit(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.submit');

        $this->draftService->submit($journalEntry, $request->user()->id);

        return redirect()->back()->with('success', __('Journal submitted successfully.'));
    }

    public function approve(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.approve');

        $this->draftService->approve($journalEntry, $request->user()->id);

        return redirect()->back()->with('success', __('Journal approved successfully.'));
    }

    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.post');

        $this->postingEngine->post($journalEntry, $request->user()->id, allowControlAccounts: $request->user()->can('accounting.override_control'));

        return redirect()->back()->with('success', __('Journal posted to ledger successfully.'));
    }

    public function reverse(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.reverse');

        $validated = $request->validate([
            'reversal_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
        ]);

        $reversal = $this->reversalService->reverse($journalEntry, $validated['reversal_period_id'], $request->user()->id);

        return redirect()->route('accounting.journal.show', $reversal->id)->with('success', __('Journal reversed successfully.'));
    }
}
