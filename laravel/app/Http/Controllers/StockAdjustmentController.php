<?php

namespace App\Http\Controllers;

use App\Application\Inventory\StockAdjustmentPageData;
use App\Application\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService,
        private readonly StockAdjustmentPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/StockAdjustments', $this->pageData->indexData($request->only(['search', 'status', 'warehouse_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->stockAdjustmentService->create($this->validatedAdjustment($request), $request->user()?->id);

        return back()->with('success', __('Stock adjustment created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->stockAdjustmentService->update($id, $this->validatedAdjustment($request), $request->user()?->id);

        return back()->with('success', __('Stock adjustment updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->stockAdjustmentService->submit($id, $request->user()?->id);

        return back()->with('success', __('Stock adjustment submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->stockAdjustmentService->approve($id, $request->user()?->id);

        return back()->with('success', __('Stock adjustment approved.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->stockAdjustmentService->post($id, $request->user()?->id);

        return back()->with('success', __('Stock adjustment posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->stockAdjustmentService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Stock adjustment cancelled.'));
    }

    private function validatedAdjustment(Request $request): array
    {
        return $request->validate([
            'adjustment_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouse,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:product,id'],
            'lines.*.quantity_delta_e6' => ['required', 'integer', 'not_in:0'],
            'lines.*.unit_cost_minor' => ['nullable', 'integer', 'min:1'],
            'lines.*.reason' => ['nullable', 'string'],
        ]);
    }
}
