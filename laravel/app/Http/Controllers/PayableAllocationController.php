<?php

namespace App\Http\Controllers;

use App\Application\Accounting\PayableAllocationService;
use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayableAllocationController extends Controller
{
    public function __construct(
        private readonly PayableAllocationService $service,
    ) {}

    public function index(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');

        // Posted payments with unapplied > 0
        $paymentsQuery = SupplierPayment::query()
            ->with('supplier')
            ->where('status', 'posted')
            ->where('unapplied_minor', '>', 0);

        if ($supplierId) {
            $paymentsQuery->where('supplier_id', $supplierId);
        }

        $payments = $paymentsQuery->orderBy('created_at', 'desc')->get();

        // Selected payment details if payment_id query parameter is present
        $selectedPayment = null;
        $openPayables = [];

        if ($request->query('payment_id')) {
            $selectedPayment = SupplierPayment::query()->with('supplier')->find($request->query('payment_id'));

            if ($selectedPayment) {
                $openPayables = PayableEntry::query()
                    ->where('supplier_id', $selectedPayment->supplier_id)
                    ->where('currency', $selectedPayment->currency)
                    ->where('unapplied_minor', '>', 0)
                    ->orderBy('entry_date', 'asc')
                    ->get();
            }
        }

        $existingAllocations = PayableAllocation::query()
            ->with(['supplierPayment', 'payableEntry', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $suppliers = Supplier::query()->where('status', 'active')->orderBy('code')->get();

        return Inertia::render('PayableAllocations/Index', [
            'payments' => $payments,
            'selectedPayment' => $selectedPayment,
            'openPayables' => $openPayables,
            'existingAllocations' => $existingAllocations,
            'suppliers' => $suppliers,
            'filters' => [
                'supplier_id' => $supplierId,
                'payment_id' => $request->query('payment_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'string', 'uuid', 'exists:supplier_payment,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.payable_entry_id' => ['required', 'string', 'uuid', 'exists:payable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->allocatePayment(
            $validated['payment_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', 'Payment allocated successfully.');
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->service->reverseAllocation($id, (int) $request->user()->id);

        return back()->with('success', 'Allocation reversed successfully.');
    }
}
