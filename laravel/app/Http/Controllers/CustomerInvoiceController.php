<?php

namespace App\Http\Controllers;

use App\Application\Sales\CustomerInvoiceService;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceController extends Controller
{
    public function __construct(
        private readonly CustomerInvoiceService $customerInvoiceService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = CustomerInvoice::query()->with([
            'customer',
            'salesOrder',
            'deliveryNote',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
            'receivableEntry',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, CustomerInvoiceService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        $customerInvoices = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $activeCustomers = Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get();

        // Non-stock and service products ONLY for Slice 5
        $eligibleProducts = Product::query()
            ->with('unitOfMeasure')
            ->where('status', 'active')
            ->where('is_sales_enabled', true)
            ->whereIn('type', ['service', 'non_stock'])
            ->orderBy('code', 'asc')
            ->get();

        $confirmedSalesOrders = SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();

        $confirmedDeliveryNotes = DeliveryNote::query()
            ->with(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();

        return Inertia::render('Sales/CustomerInvoices', [
            'customerInvoices' => $customerInvoices,
            'activeCustomers' => $activeCustomers,
            'eligibleProducts' => $eligibleProducts,
            'confirmedSalesOrders' => $confirmedSalesOrders,
            'confirmedDeliveryNotes' => $confirmedDeliveryNotes,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'sales_order_id' => ['nullable', 'uuid'],
            'delivery_note_id' => ['nullable', 'uuid'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'fx_rate_e6' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['nullable', 'uuid'],
            'lines.*.sales_order_line_id' => ['nullable', 'uuid'],
            'lines.*.delivery_note_line_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->customerInvoiceService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Invoice created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['nullable', 'uuid'],
            'lines.*.sales_order_line_id' => ['nullable', 'uuid'],
            'lines.*.delivery_note_line_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->customerInvoiceService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Invoice updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Invoice submitted successfully.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Invoice approved successfully.');
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Invoice posted to AR/GL successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->customerInvoiceService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Invoice cancelled successfully.');
    }
}
