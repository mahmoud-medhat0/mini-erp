<?php

namespace App\Http\Controllers;

use App\Application\Sales\SalesOrderService;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');

        $query = SalesOrder::query()->with(['customer', 'lines.product', 'lines.unitOfMeasure']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, SalesOrderService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $salesOrders = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get();
        $currencies = Currency::query()->orderBy('code', 'asc')->get();
        $products = Product::query()
            ->with('unitOfMeasure')
            ->where('status', 'active')
            ->where('is_sales_enabled', true)
            ->orderBy('code', 'asc')
            ->get();

        return Inertia::render('Sales/SalesOrders', [
            'salesOrders' => $salesOrders,
            'customers' => $customers,
            'currencies' => $currencies,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'customer_id' => $customerId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['required', 'string', 'size:3'],
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

        return redirect()->back()->with('success', 'Sales Order created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['required', 'string', 'size:3'],
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

        return redirect()->back()->with('success', 'Sales Order updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->salesOrderService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Order submitted successfully.');
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->salesOrderService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Order confirmed successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->salesOrderService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Order cancelled successfully.');
    }
}
