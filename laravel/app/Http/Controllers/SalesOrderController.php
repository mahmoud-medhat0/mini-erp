<?php

namespace App\Http\Controllers;

use App\Application\Sales\SalesOrderPageData;
use App\Application\Sales\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderPageData $pageData,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/SalesOrders', $this->pageData->indexData($request->only(['search', 'status', 'customer_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:1'],
        ]);

        $this->salesOrderService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Order created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:1'],
        ]);

        $this->salesOrderService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Order updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->salesOrderService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Order submitted successfully.'));
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->salesOrderService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Order confirmed successfully.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->salesOrderService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Sales Order cancelled successfully.'));
    }
}
