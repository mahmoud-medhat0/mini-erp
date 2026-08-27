<?php

namespace App\Http\Controllers;

use App\Application\Sales\CustomerInvoicePageData;
use App\Application\Sales\CustomerInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceController extends Controller
{
    public function __construct(
        private readonly CustomerInvoiceService $customerInvoiceService,
        private readonly CustomerInvoicePageData $customerInvoicePageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/CustomerInvoices', $this->customerInvoicePageData->indexData([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'sales_order_id' => ['nullable', 'uuid'],
            'delivery_note_id' => ['nullable', 'uuid'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['nullable', 'uuid'],
            'lines.*.sales_order_line_id' => ['nullable', 'uuid'],
            'lines.*.delivery_note_line_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
        ]);

        $this->customerInvoiceService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Invoice created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['nullable', 'uuid'],
            'lines.*.sales_order_line_id' => ['nullable', 'uuid'],
            'lines.*.delivery_note_line_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
        ]);

        $this->customerInvoiceService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Invoice updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Invoice submitted successfully.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Invoice approved successfully.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Invoice posted to AR/GL successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Invoice cancelled successfully.'));
    }
}
