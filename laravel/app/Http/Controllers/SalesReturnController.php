<?php

namespace App\Http\Controllers;

use App\Application\Sales\SalesReturnPageData;
use App\Application\Sales\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesReturnController extends Controller
{
    public function __construct(
        private readonly SalesReturnService $salesReturnService,
        private readonly SalesReturnPageData $salesReturnPageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/SalesReturns', $this->salesReturnPageData->indexData([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'customer_id' => $request->query('customer_id'),
            'warehouse_id' => $request->query('warehouse_id'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'delivery_note_id' => ['required', 'uuid'],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'customer_invoice_id' => ['nullable', 'uuid'],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.delivery_note_line_id' => ['required', 'uuid'],
            'lines.*.customer_invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.disposition' => ['required', 'string', 'in:restock_original_cost,restock_manual_value,scrap_no_restock'],
            'lines.*.manual_restock_value_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->salesReturnService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Return created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'customer_invoice_id' => ['nullable', 'uuid'],
            'return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.delivery_note_line_id' => ['required', 'uuid'],
            'lines.*.customer_invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.disposition' => ['required', 'string', 'in:restock_original_cost,restock_manual_value,scrap_no_restock'],
            'lines.*.manual_restock_value_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->salesReturnService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Return updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Return submitted successfully.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Return approved successfully.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Return posted to inventory/GL successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Return cancelled successfully.'));
    }

    public function returnableInvoiceLines(string $invoiceId): JsonResponse
    {
        return response()->json($this->salesReturnPageData->returnableInvoiceLines($invoiceId));
    }
}
