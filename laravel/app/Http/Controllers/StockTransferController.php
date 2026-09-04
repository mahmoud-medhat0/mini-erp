<?php

namespace App\Http\Controllers;

use App\Application\Inventory\StockTransferPageData;
use App\Application\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $stockTransferService,
        private readonly StockTransferPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/StockTransfers', $this->pageData->indexData($request->only(['search', 'status', 'warehouse_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->pageData->datatable($request->only(['search', 'status', 'warehouse_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedTransfer($request);

        $this->stockTransferService->create($validated, $request->user()?->id);

        return back()->with('success', __('Stock transfer created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validatedTransfer($request);
        $validated['lock_version'] = $request->integer('lock_version');

        $this->stockTransferService->update($id, $validated, $request->user()?->id);

        return back()->with('success', __('Stock transfer updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->stockTransferService->submit($id, $request->user()?->id);

        return back()->with('success', __('Stock transfer submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->stockTransferService->approve($id, $request->user()?->id);

        return back()->with('success', __('Stock transfer approved.'));
    }

    public function issue(Request $request, string $id): RedirectResponse
    {
        $this->stockTransferService->issue($id, $request->user()?->id);

        return back()->with('success', __('Stock transfer issued.'));
    }

    public function receive(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_date' => ['required', 'date'],
            'lines' => ['nullable', 'array'],
            'lines.*.stock_transfer_line_id' => ['required_with:lines', 'uuid', 'exists:stock_transfer_line,id'],
            'lines.*.quantity_e6' => ['required_with:lines', 'integer', 'min:1'],
        ]);

        $this->stockTransferService->receive($id, $validated, $request->user()?->id);

        return back()->with('success', __('Stock transfer received.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->stockTransferService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Stock transfer cancelled.'));
    }

    private function validatedTransfer(Request $request): array
    {
        return $request->validate([
            'transfer_date' => ['required', 'date'],
            'source_warehouse_id' => ['required', 'uuid', 'exists:warehouse,id'],
            'destination_warehouse_id' => ['required', 'uuid', 'exists:warehouse,id', 'different:source_warehouse_id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:product,id'],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', 'exists:unit_of_measure,id'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);
    }
}
