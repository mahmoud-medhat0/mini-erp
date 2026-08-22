<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\PurchaseOrderService;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');

        $query = PurchaseOrder::query()->with(['supplier', 'lines.product', 'lines.unitOfMeasure']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, PurchaseOrderService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get();
        $currencies = Currency::query()->orderBy('code', 'asc')->get();
        $products = Product::query()
            ->with('unitOfMeasure')
            ->where('status', 'active')
            ->where('is_purchase_enabled', true)
            ->orderBy('code', 'asc')
            ->get();

        return Inertia::render('Purchasing/PurchaseOrders', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier_id' => $supplierId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_receipt_date' => ['nullable', 'date', 'after_or_equal:order_date'],
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

        $this->purchaseOrderService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Order created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_receipt_date' => ['nullable', 'date', 'after_or_equal:order_date'],
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

        $this->purchaseOrderService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Order updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->purchaseOrderService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Order submitted successfully.');
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->purchaseOrderService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Order confirmed successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->purchaseOrderService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Order cancelled successfully.');
    }
}
