<?php

namespace App\Http\Controllers;

use App\Application\Sales\SalesReturnService;
use App\Models\Customer;
use App\Models\CustomerCreditNoteLine;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\DeliveryNote;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesReturnController extends Controller
{
    public function __construct(
        private readonly SalesReturnService $salesReturnService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');

        $query = SalesReturn::query()->with([
            'customer',
            'deliveryNote',
            'customerInvoice',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, SalesReturnService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $salesReturns = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $activeCustomers = Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get();

        $confirmedDeliveryNotes = DeliveryNote::query()
            ->with(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();

        $postedCustomerInvoices = CustomerInvoice::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();

        return Inertia::render('Sales/SalesReturns', [
            'salesReturns' => $salesReturns,
            'activeCustomers' => $activeCustomers,
            'confirmedDeliveryNotes' => $confirmedDeliveryNotes,
            'postedCustomerInvoices' => $postedCustomerInvoices,
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
            'delivery_note_id' => ['required', 'uuid'],
            'customer_invoice_id' => ['nullable', 'uuid'],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.delivery_note_line_id' => ['required', 'uuid'],
            'lines.*.customer_invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.disposition' => ['required', 'string', 'in:restock_original_cost,restock_manual_value,scrap_no_restock'],
            'lines.*.manual_restock_value_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->salesReturnService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Return created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'customer_invoice_id' => ['nullable', 'uuid'],
            'return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.delivery_note_line_id' => ['required', 'uuid'],
            'lines.*.customer_invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.disposition' => ['required', 'string', 'in:restock_original_cost,restock_manual_value,scrap_no_restock'],
            'lines.*.manual_restock_value_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->salesReturnService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Return updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Return submitted successfully.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Return approved successfully.');
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Return posted to inventory/GL successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->salesReturnService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Sales Return cancelled successfully.');
    }

    public function returnableInvoiceLines(string $invoiceId): JsonResponse
    {
        /** @var CustomerInvoice|null $invoice */
        $invoice = CustomerInvoice::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('id', $invoiceId)
            ->first();

        abort_unless($invoice && $invoice->status === 'posted', 404);

        $lines = $invoice->lines->map(function (CustomerInvoiceLine $line): array {
            $returnedFromSalesReturnsE6 = (int) SalesReturnLine::query()
                ->where('customer_invoice_line_id', $line->id)
                ->whereHas('salesReturn', fn ($q) => $q->where('status', 'posted'))
                ->sum('quantity_e6');

            $returnedFromCreditNotesE6 = (int) CustomerCreditNoteLine::query()
                ->where('customer_invoice_line_id', $line->id)
                ->whereHas('customerCreditNote', fn ($q) => $q->where('status', 'posted'))
                ->sum('quantity_e6');

            $originalQuantityE6 = (int) $line->quantity_e6;

            return [
                'id' => $line->id,
                'description' => $line->description,
                'original_quantity_e6' => $originalQuantityE6,
                'returned_quantity_e6' => $returnedFromSalesReturnsE6,
                'credited_quantity_e6' => $returnedFromCreditNotesE6,
                'max_returnable_quantity_e6' => max(0, $originalQuantityE6 - $returnedFromSalesReturnsE6 - $returnedFromCreditNotesE6),
                'unit_price_minor' => (int) $line->unit_price_minor,
            ];
        })->values()->all();

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'currency' => $invoice->currency,
                'customer' => $invoice->customer?->only(['id', 'name']),
            ],
            'lines' => $lines,
        ]);
    }
}
