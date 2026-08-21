<?php

namespace App\Http\Controllers;

use App\Application\Accounting\ReceivableAllocationService;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableAllocationController extends Controller
{
    public function __construct(
        private readonly ReceivableAllocationService $service,
    ) {}

    public function index(Request $request): Response
    {
        $customerId = $request->query('customer_id');

        // Posted receipts with unapplied > 0
        $receiptsQuery = CustomerReceipt::query()
            ->with('customer')
            ->where('status', 'posted')
            ->where('unapplied_minor', '>', 0);

        if ($customerId) {
            $receiptsQuery->where('customer_id', $customerId);
        }

        $receipts = $receiptsQuery->orderBy('created_at', 'desc')->get();

        // Selected receipt details if receipt_id query parameter is present
        $selectedReceipt = null;
        $openReceivables = [];

        if ($request->query('receipt_id')) {
            $selectedReceipt = CustomerReceipt::query()->with('customer')->find($request->query('receipt_id'));

            if ($selectedReceipt) {
                $openReceivables = ReceivableEntry::query()
                    ->where('customer_id', $selectedReceipt->customer_id)
                    ->where('currency', $selectedReceipt->currency)
                    ->where('unapplied_minor', '>', 0)
                    ->orderBy('entry_date', 'asc')
                    ->get();
            }
        }

        $existingAllocations = ReceivableAllocation::query()
            ->with(['customerReceipt', 'receivableEntry', 'customer'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $customers = Customer::query()->where('status', 'active')->orderBy('code')->get();

        return Inertia::render('ReceivableAllocations/Index', [
            'receipts' => $receipts,
            'selectedReceipt' => $selectedReceipt,
            'openReceivables' => $openReceivables,
            'existingAllocations' => $existingAllocations,
            'customers' => $customers,
            'filters' => [
                'customer_id' => $customerId,
                'receipt_id' => $request->query('receipt_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_id' => ['required', 'string', 'uuid', 'exists:customer_receipt,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.receivable_entry_id' => ['required', 'string', 'uuid', 'exists:receivable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->allocateReceipt(
            $validated['receipt_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', 'Receipt allocated successfully.');
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->service->reverseAllocation($id, (int) $request->user()->id);

        return back()->with('success', 'Allocation reversed successfully.');
    }
}
