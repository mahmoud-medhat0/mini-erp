<?php

namespace App\Http\Controllers\Partners;

use App\Application\Partners\PartnerTransactionPageData;
use App\Application\Partners\PartnerTransactionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerTransactionController extends Controller
{
    public function __construct(
        private readonly PartnerTransactionPageData $pageData,
        private readonly PartnerTransactionService $transactionService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('partners.view');

        return Inertia::render('Partners/Transactions', $this->pageData->indexData($request->only(['status', 'partner_id', 'transaction_type'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('partners.view');

        return $this->pageData->datatable($request->only(['status', 'partner_id', 'transaction_type']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('partners.create');

        $validated = $request->validate([
            'partner_id' => ['required', 'uuid'],
            'transaction_type' => ['required', Rule::in(PartnerTransactionService::TRANSACTION_TYPES)],
            'transaction_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->transactionService->create($validated, $request->user()?->id);

        return back()->with('success', __('Partner transaction created.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('partners.post');
        Gate::authorize('view_financials');

        $validated = $request->validate([
            'settlement_method' => ['required', Rule::in(PartnerTransactionService::SETTLEMENT_METHODS)],
            'cash_account_id' => ['nullable', 'uuid'],
            'bank_account_id' => ['nullable', 'uuid'],
        ]);

        $this->transactionService->post($id, $validated, $request->user()?->id);

        return back()->with('success', __('Partner transaction posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('partners.edit');

        $this->transactionService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Partner transaction cancelled.'));
    }
}
