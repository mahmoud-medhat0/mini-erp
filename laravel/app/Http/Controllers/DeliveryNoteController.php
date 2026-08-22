<?php

namespace App\Http\Controllers;

use App\Application\Sales\DeliveryNoteService;
use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private readonly DeliveryNoteService $deliveryNoteService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = DeliveryNote::query()->with(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', function ($sq) use ($search): void {
                        $sq->where('number', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($cq) use ($search): void {
                                $cq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($status && in_array($status, DeliveryNoteService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        $deliveryNotes = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $confirmedSalesOrders = SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();

        return Inertia::render('Sales/DeliveryNotes', [
            'deliveryNotes' => $deliveryNotes,
            'confirmedSalesOrders' => $confirmedSalesOrders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sales_order_id' => ['required', 'uuid'],
            'delivery_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->deliveryNoteService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Delivery Note created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
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

        return redirect()->back()->with('success', 'Delivery Note updated successfully.');
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->deliveryNoteService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Delivery Note confirmed successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->deliveryNoteService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Delivery Note cancelled successfully.');
    }
}
