<?php

namespace App\Http\Controllers;

use App\Application\Accounting\OutgoingChequePageData;
use App\Application\Accounting\OutgoingChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutgoingChequeController extends Controller
{
    public function __construct(
        private readonly OutgoingChequePageData $pageData,
        private readonly OutgoingChequeService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('OutgoingCheques/Index', $this->pageData->indexData($request->only(['status', 'supplier_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'string', 'uuid', 'exists:supplier,id'],
            'bank_account_id' => ['required', 'string', 'uuid', 'exists:bank_account,id'],
            'cheque_number' => ['required', 'string', 'max:50'],
            'due_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->createDraft($validated, (int) $request->user()->id);

        return back()->with('success', __('Outgoing cheque created as draft.'));
    }

    public function issue(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'issued_date' => ['required', 'date'],
        ]);

        $this->service->issue($id, $validated, (int) $request->user()->id);

        return back()->with('success', __('Outgoing cheque issued successfully.'));
    }

    public function clear(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'cleared_date' => ['required', 'date'],
        ]);

        $this->service->clear($id, $validated, (int) $request->user()->id);

        return back()->with('success', __('Outgoing cheque cleared successfully.'));
    }

    public function return(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'returned_date' => ['required', 'date'],
            'return_reason' => ['nullable', 'string'],
        ]);

        $this->service->return($id, $validated, (int) $request->user()->id);

        return back()->with('success', __('Outgoing cheque returned successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'cancelled_date' => ['required', 'date'],
            'cancel_reason' => ['nullable', 'string'],
        ]);

        $this->service->cancel($id, $validated, (int) $request->user()->id);

        return back()->with('success', __('Outgoing cheque cancelled successfully.'));
    }
}
