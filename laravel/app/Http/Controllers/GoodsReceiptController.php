<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\GoodsReceiptPageData;
use App\Application\Purchasing\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptPageData $pageData,
        private readonly GoodsReceiptService $goodsReceiptService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Purchasing/GoodsReceipts', $this->pageData->indexData($request->only(['search', 'status', 'warehouse_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'uuid'],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'receipt_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->goodsReceiptService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Goods Receipt created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'receipt_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->goodsReceiptService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Goods Receipt updated successfully.'));
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->goodsReceiptService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Goods Receipt confirmed successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->goodsReceiptService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Goods Receipt cancelled successfully.'));
    }
}
