<?php

namespace App\Http\Controllers\Partners;

use App\Application\Partners\PartnerLoanPageData;
use App\Application\Partners\PartnerLoanService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerLoanController extends Controller
{
    public function __construct(
        private readonly PartnerLoanPageData $pageData,
        private readonly PartnerLoanService $loanService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('partners.view');

        return Inertia::render('Partners/Loans', $this->pageData->indexData($request->only(['status', 'partner_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('partners.view');

        return $this->pageData->datatable($request->only(['status', 'partner_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('partners.create');
        Gate::authorize('view_financials');

        $validated = $request->validate([
            'partner_id' => ['required', 'uuid'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'principal_minor' => ['required', 'integer', 'min:1'],
            'disbursement_date' => ['required', 'date'],
            'disbursement_method' => ['required', Rule::in(PartnerLoanService::SETTLEMENT_METHODS)],
            'cash_account_id' => ['nullable', 'uuid'],
            'bank_account_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->loanService->disburse($validated, $request->user()?->id);

        return back()->with('success', __('Partner loan disbursed.'));
    }

    public function repay(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('partners.post');
        Gate::authorize('view_financials');

        $validated = $request->validate([
            'repayment_date' => ['required', 'date'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'repayment_method' => ['required', Rule::in(PartnerLoanService::SETTLEMENT_METHODS)],
            'cash_account_id' => ['nullable', 'uuid'],
            'bank_account_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->loanService->repay($id, $validated, $request->user()?->id);

        return back()->with('success', __('Partner loan repayment recorded.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('partners.edit');

        $this->loanService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Partner loan cancelled.'));
    }
}
