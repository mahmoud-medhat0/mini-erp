<?php

namespace App\Http\Controllers\Taxes;

use App\Application\Taxes\WithholdingTaxPageData;
use App\Application\Taxes\WithholdingTaxService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WithholdingTaxController extends Controller
{
    public function __construct(
        private readonly WithholdingTaxPageData $pageData,
        private readonly WithholdingTaxService $whtService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('taxes.view');

        return Inertia::render('Taxes/Withholding/Index', $this->pageData->indexData($request->only(['status', 'supplier_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('taxes.view');

        return $this->pageData->datatable($request->only(['status', 'supplier_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('taxes.edit');

        $validated = $request->validate([
            'tax_code_id' => ['required', 'uuid'],
            'supplier_id' => ['required', 'uuid'],
            'reference' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'base_amount_minor' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->whtService->create($validated, $request->user()?->id);

        return back()->with('success', __('Withholding tax entry created.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('taxes.file');

        $this->whtService->post($id, $request->user()?->id);

        return back()->with('success', __('Withholding tax entry posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('taxes.edit');

        $this->whtService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Withholding tax entry cancelled.'));
    }
}
