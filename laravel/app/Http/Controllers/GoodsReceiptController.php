<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\GoodsReceiptService;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = GoodsReceipt::query()->with(['purchaseOrder.supplier', 'lines.product', 'lines.unitOfMeasure']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', function ($pq) use ($search): void {
                        $pq->where('number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($sq) use ($search): void {
                                $sq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($status && in_array($status, GoodsReceiptService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        $goodsReceipts = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $confirmedPurchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();

        return Inertia::render('Purchasing/GoodsReceipts', [
            'goodsReceipts' => $goodsReceipts,
            'confirmedPurchaseOrders' => $confirmedPurchaseOrders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'uuid'],
            'receipt_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->goodsReceiptService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Goods Receipt created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->goodsReceiptService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Goods Receipt updated successfully.');
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->goodsReceiptService->confirm($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Goods Receipt confirmed successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->goodsReceiptService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Goods Receipt cancelled successfully.');
    }
}
