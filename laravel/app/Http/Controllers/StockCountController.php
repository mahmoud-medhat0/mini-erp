<?php

namespace App\Http\Controllers;

use App\Application\Inventory\StockCountPageData;
use App\Application\Inventory\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function __construct(
        private readonly StockCountService $stockCountService,
        private readonly StockCountPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/StockCounts', $this->pageData->indexData($request->only(['search', 'status', 'warehouse_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->stockCountService->create($this->validatedCount($request), $request->user()?->id);

        return back()->with('success', __('Stock count created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->stockCountService->update($id, $this->validatedCount($request), $request->user()?->id);

        return back()->with('success', __('Stock count updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->stockCountService->submit($id, $request->user()?->id);

        return back()->with('success', __('Stock count submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->stockCountService->approve($id, $request->user()?->id);

        return back()->with('success', __('Stock count approved.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->stockCountService->post($id, $request->user()?->id);

        return back()->with('success', __('Stock count posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->stockCountService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Stock count cancelled.'));
    }

    private function validatedCount(Request $request): array
    {
        return $request->validate([
            'count_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouse,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:product,id'],
            'lines.*.expected_quantity_e6' => ['nullable', 'integer', 'min:0'],
            'lines.*.counted_quantity_e6' => ['required', 'integer', 'min:0'],
            'lines.*.unit_cost_minor' => ['nullable', 'integer', 'min:1'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);
    }
}
