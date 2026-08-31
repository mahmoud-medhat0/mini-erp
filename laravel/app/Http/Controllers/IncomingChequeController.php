<?php

namespace App\Http\Controllers;

use App\Application\Accounting\IncomingChequePageData;
use App\Application\Accounting\IncomingChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomingChequeController extends Controller
{
    public function __construct(
        private readonly IncomingChequePageData $pageData,
        private readonly IncomingChequeService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('IncomingCheques/Index', $this->pageData->indexData($request->only(['status', 'customer_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'uuid', 'exists:customer,id'],
            'cheque_number' => ['required', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->createDraft($validated, (int) $request->user()->id);

        return back()->with('success', __('Incoming cheque created as draft.'));
    }

    public function receive(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'received_date' => ['required', 'date'],
        ]);

        $period = $this->pageData->period($validated['financial_period_id']);
        $this->service->receive($id, (string) $period->fiscal_year_id, $period->id, $validated['received_date'], (int) $request->user()->id);

        return back()->with('success', __('Incoming cheque received successfully.'));
    }

    public function deposit(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'string', 'uuid', 'exists:bank_account,id'],
            'deposited_date' => ['required', 'date'],
        ]);

        $this->service->deposit($id, $validated, (int) $request->user()->id);

        return back()->with('success', __('Incoming cheque deposited successfully.'));
    }

    public function clear(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'bank_account_id' => ['nullable', 'string', 'uuid', 'exists:bank_account,id'],
            'cleared_date' => ['required', 'date'],
        ]);

        $period = $this->pageData->period($validated['financial_period_id']);
        $this->service->clear($id, (string) $period->fiscal_year_id, $period->id, $validated['cleared_date'], $validated['bank_account_id'] ?? null, (int) $request->user()->id);

        return back()->with('success', __('Incoming cheque cleared successfully.'));
    }

    public function bounce(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'bounced_date' => ['required', 'date'],
            'bounce_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $period = $this->pageData->period($validated['financial_period_id']);
        $this->service->bounceBeforeClear($id, (string) $period->fiscal_year_id, $period->id, $validated['bounced_date'], $validated['bounce_reason'], (int) $request->user()->id);

        return back()->with('success', __('Incoming cheque bounced successfully.'));
    }

    public function return(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'returned_date' => ['required', 'date'],
            'return_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $period = $this->pageData->period($validated['financial_period_id']);
        $this->service->returnBeforeClear($id, (string) $period->fiscal_year_id, $period->id, $validated['returned_date'], $validated['return_reason'], (int) $request->user()->id);

        return back()->with('success', __('Incoming cheque returned successfully.'));
    }
}
