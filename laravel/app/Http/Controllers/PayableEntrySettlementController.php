<?php

namespace App\Http\Controllers;

use App\Application\Accounting\PayableEntrySettlementService;
use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayableEntrySettlementController extends Controller
{
    public function __construct(
        private readonly PayableEntrySettlementService $service,
    ) {}

    public function index(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $sourceEntryId = $request->query('source_entry_id');

        // Open debit payable entries (debit_minor > credit_minor)
        $debitEntriesQuery = PayableEntry::query()
            ->with('supplier')
            ->whereRaw('debit_minor > credit_minor');

        if ($supplierId) {
            $debitEntriesQuery->where('supplier_id', $supplierId);
        }

        $debitEntries = $debitEntriesQuery->orderBy('entry_date', 'desc')->get()->map(function (PayableEntry $entry) {
            $capacity = (int) $entry->debit_minor - (int) $entry->credit_minor;
            $settledSum = (int) PayableEntrySettlement::query()
                ->where('source_payable_entry_id', $entry->id)
                ->where('status', 'active')
                ->sum('amount_minor');
            $remaining = $capacity - $settledSum;

            return array_merge($entry->toArray(), [
                'remaining_minor' => $remaining,
            ]);
        })->filter(fn ($entry) => $entry['remaining_minor'] > 0)->values();

        $selectedSourceEntry = null;
        $openTargetCredits = [];

        if ($sourceEntryId) {
            $rawSource = PayableEntry::query()->with('supplier')->find($sourceEntryId);
            if ($rawSource) {
                $capacity = (int) $rawSource->debit_minor - (int) $rawSource->credit_minor;
                $settledSum = (int) PayableEntrySettlement::query()
                    ->where('source_payable_entry_id', $rawSource->id)
                    ->where('status', 'active')
                    ->sum('amount_minor');
                $remaining = $capacity - $settledSum;

                $selectedSourceEntry = array_merge($rawSource->toArray(), [
                    'remaining_minor' => $remaining,
                ]);

                // Eligible target credit entries for same supplier & currency
                $creditEntries = PayableEntry::query()
                    ->where('supplier_id', $rawSource->supplier_id)
                    ->where('currency', $rawSource->currency)
                    ->where('id', '!=', $rawSource->id)
                    ->whereRaw('credit_minor > debit_minor')
                    ->orderBy('entry_date', 'asc')
                    ->get();

                foreach ($creditEntries as $creditEntry) {
                    $creditCap = (int) $creditEntry->credit_minor - (int) $creditEntry->debit_minor;
                    $allocSum = (int) PayableAllocation::query()
                        ->where('payable_entry_id', $creditEntry->id)
                        ->where('status', 'active')
                        ->sum('amount_minor');
                    $settleSum = (int) PayableEntrySettlement::query()
                        ->where('target_payable_entry_id', $creditEntry->id)
                        ->where('status', 'active')
                        ->sum('amount_minor');
                    $remCredit = $creditCap - $allocSum - $settleSum;

                    if ($remCredit > 0) {
                        $openTargetCredits[] = array_merge($creditEntry->toArray(), [
                            'remaining_minor' => $remCredit,
                        ]);
                    }
                }
            }
        }

        $existingSettlements = PayableEntrySettlement::query()
            ->with(['supplier', 'sourcePayableEntry', 'targetPayableEntry', 'creator', 'reverser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name')->get();

        return Inertia::render('Purchasing/PayableSettlements', [
            'debitEntries' => $debitEntries,
            'selectedSourceEntry' => $selectedSourceEntry,
            'openTargetCredits' => $openTargetCredits,
            'existingSettlements' => $existingSettlements,
            'suppliers' => $suppliers,
            'filters' => [
                'supplier_id' => $supplierId,
                'source_entry_id' => $sourceEntryId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_payable_entry_id' => ['required', 'string', 'uuid', 'exists:payable_entry,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.target_payable_entry_id' => ['required', 'string', 'uuid', 'exists:payable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->settleDebit(
            $validated['source_payable_entry_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', 'Adjustment debit settled successfully against target bill(s).');
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->reverseSettlement($id, $validated['reason'], (int) $request->user()->id);

        return back()->with('success', 'Payable settlement reversed successfully.');
    }
}
