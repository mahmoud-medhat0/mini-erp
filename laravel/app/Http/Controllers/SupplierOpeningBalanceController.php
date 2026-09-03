<?php

namespace App\Http\Controllers;

use App\Application\Accounting\SupplierOpeningBalancePageData;
use App\Application\Accounting\SupplierOpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierOpeningBalanceController extends Controller
{
    public function __construct(
        private readonly SupplierOpeningBalancePageData $pageData,
        private readonly SupplierOpeningBalanceService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('SupplierOpeningBalances/Index', $this->pageData->indexData());
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('suppliers.view');

        return $this->pageData->datatable($request->only(['status']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'string', 'uuid', 'exists:supplier,id'],
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'entry_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->service->create($validated, $request->user()?->id);

        return back()->with('success', __('Supplier opening balance created as draft.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->service->post($id, $request->user()?->id);

        return back()->with('success', __('Supplier opening balance posted successfully.'));
    }
}
