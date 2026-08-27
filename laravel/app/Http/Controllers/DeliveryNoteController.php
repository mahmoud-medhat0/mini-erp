<?php

namespace App\Http\Controllers;

use App\Application\Sales\DeliveryNotePageData;
use App\Application\Sales\DeliveryNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private readonly DeliveryNotePageData $pageData,
        private readonly DeliveryNoteService $deliveryNoteService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/DeliveryNotes', $this->pageData->indexData($request->only(['search', 'status', 'warehouse_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sales_order_id' => ['required', 'uuid'],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'delivery_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->deliveryNoteService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Delivery Note created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouse', 'id')->where('is_active', true)],
            'delivery_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->deliveryNoteService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Delivery Note updated successfully.'));
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->deliveryNoteService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Delivery Note confirmed successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->deliveryNoteService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Delivery Note cancelled successfully.'));
    }
}
