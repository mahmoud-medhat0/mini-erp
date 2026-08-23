<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\SupplierAdjustmentNoteService;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use App\Models\TaxCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierAdjustmentNoteController extends Controller
{
    public function __construct(
        private readonly SupplierAdjustmentNoteService $supplierAdjustmentNoteService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');

        $query = SupplierAdjustmentNote::query()->with([
            'supplier',
            'supplierBill',
            'purchaseReturn',
            'lines',
            'journalEntry',
            'payableEntry',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('ui_label', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, SupplierAdjustmentNoteService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $supplierAdjustmentNotes = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $activeSuppliers = Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get();

        $postedSupplierBills = SupplierBill::query()
            ->with(['supplier', 'lines'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();

        $postedPurchaseReturns = PurchaseReturn::query()
            ->with(['supplier', 'lines'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();

        $taxCodes = TaxCode::query()->where('is_active', true)->orderBy('code', 'asc')->get();

        return Inertia::render('Purchasing/SupplierAdjustmentNotes', [
            'supplierAdjustmentNotes' => $supplierAdjustmentNotes,
            'activeSuppliers' => $activeSuppliers,
            'postedSupplierBills' => $postedSupplierBills,
            'postedPurchaseReturns' => $postedPurchaseReturns,
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
            'supplier_bill_id' => ['nullable', 'uuid'],
            'purchase_return_id' => ['nullable', 'uuid'],
            'adjustment_date' => ['required', 'date'],
            'direction' => ['required', 'string', 'in:decrease_payable,increase_payable'],
            'currency' => ['required', 'string', 'size:3'],
            'ui_label' => ['nullable', 'string', 'max:255'],
            'tax_mode' => ['nullable', 'string', 'in:none,manual_rate,manual_amount'],
            'tax_rate_bps' => ['nullable', 'integer', 'min:0'],
            'tax_amount_minor' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.supplier_bill_line_id' => ['nullable', 'uuid'],
            'lines.*.purchase_return_line_id' => ['nullable', 'uuid'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity_e6' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_cost_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_rate_bps' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->supplierAdjustmentNoteService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Supplier Adjustment Note created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_bill_id' => ['nullable', 'uuid'],
            'purchase_return_id' => ['nullable', 'uuid'],
            'adjustment_date' => ['nullable', 'date'],
            'direction' => ['nullable', 'string', 'in:decrease_payable,increase_payable'],
            'currency' => ['nullable', 'string', 'size:3'],
            'ui_label' => ['nullable', 'string', 'max:255'],
            'tax_mode' => ['nullable', 'string', 'in:none,manual_rate,manual_amount'],
            'tax_rate_bps' => ['nullable', 'integer', 'min:0'],
            'tax_amount_minor' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.supplier_bill_line_id' => ['nullable', 'uuid'],
            'lines.*.purchase_return_line_id' => ['nullable', 'uuid'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity_e6' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_cost_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_rate_bps' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->supplierAdjustmentNoteService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', 'Supplier Adjustment Note updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->supplierAdjustmentNoteService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Supplier Adjustment Note submitted successfully.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->supplierAdjustmentNoteService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Supplier Adjustment Note approved successfully.');
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->supplierAdjustmentNoteService->post($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Supplier Adjustment Note posted to AP/GL successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->supplierAdjustmentNoteService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Supplier Adjustment Note cancelled successfully.');
    }
}
