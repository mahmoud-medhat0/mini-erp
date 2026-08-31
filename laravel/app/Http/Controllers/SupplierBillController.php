<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\SupplierBillPageData;
use App\Application\Purchasing\SupplierBillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierBillController extends Controller
{
    public function __construct(
        private readonly SupplierBillService $supplierBillService,
        private readonly SupplierBillPageData $supplierBillPageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Purchasing/SupplierBills', $this->supplierBillPageData->indexData([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid'],
            'purchase_order_id' => ['nullable', 'uuid'],
            'goods_receipt_id' => ['nullable', 'uuid'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['nullable', 'uuid'],
            'lines.*.purchase_order_line_id' => ['nullable', 'uuid'],
            'lines.*.goods_receipt_line_id' => ['nullable', 'uuid'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->supplierBillService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Supplier Bill created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'bill_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lock_version' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['nullable', 'uuid'],
            'lines.*.purchase_order_line_id' => ['nullable', 'uuid'],
            'lines.*.goods_receipt_line_id' => ['nullable', 'uuid'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->supplierBillService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Supplier Bill updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->supplierBillService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Supplier Bill submitted successfully.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->supplierBillService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Supplier Bill approved successfully.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->supplierBillService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Supplier Bill posted successfully to AP/GL.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->supplierBillService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Supplier Bill cancelled successfully.'));
    }
}
