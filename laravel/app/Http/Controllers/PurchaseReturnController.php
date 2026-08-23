<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\PurchaseReturnService;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\TaxCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnService $purchaseReturnService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');

        $query = PurchaseReturn::query()->with([
            'supplier',
            'goodsReceipt.purchaseOrder.supplier',
            'supplierBill',
            'lines.product',
            'lines.unitOfMeasure',
            'journalEntry',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, PurchaseReturnService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $purchaseReturns = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $activeSuppliers = Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get();

        $confirmedGoodsReceipts = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'lines.product', 'lines.unitOfMeasure', 'lines.purchaseOrderLine'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();

        $taxCodes = TaxCode::query()->where('is_active', true)->orderBy('code', 'asc')->get();

        return Inertia::render('Purchasing/PurchaseReturns', [
            'purchaseReturns' => $purchaseReturns,
            'activeSuppliers' => $activeSuppliers,
            'confirmedGoodsReceipts' => $confirmedGoodsReceipts,
            'taxCodes' => $taxCodes,
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
            'goods_receipt_id' => ['required', 'uuid'],
            'supplier_bill_id' => ['nullable', 'uuid'],
            'return_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'uuid'],
            'lines.*.supplier_bill_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->purchaseReturnService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Return created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_bill_id' => ['nullable', 'uuid'],
            'return_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'uuid'],
            'lines.*.supplier_bill_line_id' => ['nullable', 'uuid'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
        ]);

        $this->purchaseReturnService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Return updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Return submitted successfully.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Return approved successfully.');
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Return posted to inventory/AP/GL successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->purchaseReturnService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Purchase Return cancelled successfully.');
    }
}
