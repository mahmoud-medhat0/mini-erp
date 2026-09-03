<?php

namespace App\Http\Controllers;

use App\Application\Accounting\CustomerReceiptPageData;
use App\Application\Accounting\CustomerReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReceiptController extends Controller
{
    public function __construct(
        private readonly CustomerReceiptPageData $pageData,
        private readonly CustomerReceiptService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('CustomerReceipts/Index', $this->pageData->indexData());
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('customers.view');

        return $this->pageData->datatable($request->only(['status']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'uuid', 'exists:customer,id'],
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'receipt_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cash_account_id' => ['nullable', 'string', 'uuid', 'exists:cash_account,id'],
            'bank_account_id' => ['nullable', 'string', 'uuid', 'exists:bank_account,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->service->create($validated, $request->user()?->id);

        return back()->with('success', __('Customer receipt created as draft.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->service->post($id, $request->user()?->id);

        return back()->with('success', __('Customer receipt posted successfully.'));
    }
}
