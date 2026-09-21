<?php

namespace App\Http\Controllers\Sales;

use App\Application\Sales\SalesQuotationPageData;
use App\Application\Sales\SalesQuotationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalesQuotationController extends Controller
{
    public function __construct(
        private readonly SalesQuotationPageData $pageData,
        private readonly SalesQuotationService $quotationService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/Quotations', $this->pageData->indexData($request->only(['search', 'status', 'customer_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('sales.view');

        return $this->pageData->datatable($request->only(['status', 'customer_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->quotationService->create($this->validatedQuotation($request), $request->user()?->id);

        return back()->with('success', __('Quotation created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->quotationService->update($id, $this->validatedQuotation($request, true), $request->user()?->id);

        return back()->with('success', __('Quotation updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->quotationService->submit($id, $request->user()?->id);

        return back()->with('success', __('Quotation submitted successfully.'));
    }

    public function accept(Request $request, string $id): RedirectResponse
    {
        $this->quotationService->accept($id, $request->user()?->id);

        return back()->with('success', __('Quotation accepted.'));
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $this->quotationService->reject($id, $request->user()?->id);

        return back()->with('success', __('Quotation rejected.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->quotationService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Quotation cancelled.'));
    }

    public function convert(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
        ]);

        $this->quotationService->convertToSalesOrder($id, $validated, $request->user()?->id);

        return back()->with('success', __('Quotation converted to a Sales Order.'));
    }

    private function validatedQuotation(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'customer_id' => ['required', 'uuid'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:1'],
        ]);
    }
}
