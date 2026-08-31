<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\PurchaseReturnPageData;
use App\Application\Purchasing\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnService $purchaseReturnService,
        private readonly PurchaseReturnPageData $purchaseReturnPageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Purchasing/PurchaseReturns', $this->purchaseReturnPageData->indexData([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'supplier_id' => $request->query('supplier_id'),
            'warehouse_id' => $request->query('warehouse_id'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid'],
            'goods_receipt_id' => ['required', 'uuid'],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'supplier_bill_id' => ['nullable', 'uuid'],
            'return_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'uuid'],
            'lines.*.supplier_bill_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->purchaseReturnService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Purchase Return created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'supplier_bill_id' => ['nullable', 'uuid'],
            'return_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'uuid'],
            'lines.*.supplier_bill_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->purchaseReturnService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Purchase Return updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Purchase Return submitted successfully.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Purchase Return approved successfully.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Purchase Return posted to inventory/AP/GL successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Purchase Return cancelled successfully.'));
    }
}
