<?php

namespace App\Http\Controllers;

use App\Application\Accounting\SupplierPaymentPageData;
use App\Application\Accounting\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function __construct(
        private readonly SupplierPaymentPageData $pageData,
        private readonly SupplierPaymentService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('SupplierPayments/Index', $this->pageData->indexData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'string', 'uuid', 'exists:supplier,id'],
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cash_account_id' => ['nullable', 'string', 'uuid', 'exists:cash_account,id'],
            'bank_account_id' => ['nullable', 'string', 'uuid', 'exists:bank_account,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->service->create($validated, $request->user()?->id);

        return back()->with('success', __('Supplier payment created as draft.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->service->post($id, $request->user()?->id);

        return back()->with('success', __('Supplier payment posted successfully.'));
    }
}
