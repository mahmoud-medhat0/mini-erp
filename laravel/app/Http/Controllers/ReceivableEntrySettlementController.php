<?php

namespace App\Http\Controllers;

use App\Application\Accounting\ReceivableEntrySettlementService;
use App\Models\Customer;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableEntrySettlementController extends Controller
{
    public function __construct(
        private readonly ReceivableEntrySettlementService $service,
    ) {}

    public function index(Request $request): Response
    {
        $customerId = $request->query('customer_id');
        $sourceEntryId = $request->query('source_entry_id');

        // Open credit receivable entries (credit_minor > debit_minor)
        $creditEntriesQuery = ReceivableEntry::query()
            ->with('customer')
            ->whereRaw('credit_minor > debit_minor');

        if ($customerId) {
            $creditEntriesQuery->where('customer_id', $customerId);
        }

        $creditEntries = $creditEntriesQuery->orderBy('entry_date', 'desc')->get()->map(function (ReceivableEntry $entry) {
            $capacity = (int) $entry->credit_minor - (int) $entry->debit_minor;
            $settledSum = (int) ReceivableEntrySettlement::query()
                ->where('source_receivable_entry_id', $entry->id)
                ->where('status', 'active')
                ->sum('amount_minor');
            $remaining = $capacity - $settledSum;

            return array_merge($entry->toArray(), [
                'remaining_minor' => $remaining,
            ]);
        })->filter(fn ($entry) => $entry['remaining_minor'] > 0)->values();

        $selectedSourceEntry = null;
        $openTargetDebits = [];

        if ($sourceEntryId) {
            $rawSource = ReceivableEntry::query()->with('customer')->find($sourceEntryId);
            if ($rawSource) {
                $capacity = (int) $rawSource->credit_minor - (int) $rawSource->debit_minor;
                $settledSum = (int) ReceivableEntrySettlement::query()
                    ->where('source_receivable_entry_id', $rawSource->id)
                    ->where('status', 'active')
                    ->sum('amount_minor');
                $remaining = $capacity - $settledSum;

                $selectedSourceEntry = array_merge($rawSource->toArray(), [
                    'remaining_minor' => $remaining,
                ]);

                // Eligible target debit entries for same customer & currency
                $debitEntries = ReceivableEntry::query()
                    ->where('customer_id', $rawSource->customer_id)
                    ->where('currency', $rawSource->currency)
                    ->where('id', '!=', $rawSource->id)
                    ->whereRaw('debit_minor > credit_minor')
                    ->orderBy('entry_date', 'asc')
                    ->get();

                foreach ($debitEntries as $debitEntry) {
                    $debitCap = (int) $debitEntry->debit_minor - (int) $debitEntry->credit_minor;
                    $allocSum = (int) ReceivableAllocation::query()
                        ->where('receivable_entry_id', $debitEntry->id)
                        ->where('status', 'active')
                        ->sum('amount_minor');
                    $settleSum = (int) ReceivableEntrySettlement::query()
                        ->where('target_receivable_entry_id', $debitEntry->id)
                        ->where('status', 'active')
                        ->sum('amount_minor');
                    $remDebit = $debitCap - $allocSum - $settleSum;

                    if ($remDebit > 0) {
                        $openTargetDebits[] = array_merge($debitEntry->toArray(), [
                            'remaining_minor' => $remDebit,
                        ]);
                    }
                }
            }
        }

        $existingSettlements = ReceivableEntrySettlement::query()
            ->with(['customer', 'sourceReceivableEntry', 'targetReceivableEntry', 'creator', 'reverser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $customers = Customer::query()->where('status', 'active')->orderBy('name')->get();

        return Inertia::render('Sales/ReceivableSettlements', [
            'creditEntries' => $creditEntries,
            'selectedSourceEntry' => $selectedSourceEntry,
            'openTargetDebits' => $openTargetDebits,
            'existingSettlements' => $existingSettlements,
            'customers' => $customers,
            'filters' => [
                'customer_id' => $customerId,
                'source_entry_id' => $sourceEntryId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_receivable_entry_id' => ['required', 'string', 'uuid', 'exists:receivable_entry,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.target_receivable_entry_id' => ['required', 'string', 'uuid', 'exists:receivable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->settleCredit(
            $validated['source_receivable_entry_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', 'Credit settled successfully against target invoice(s).');
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->reverseSettlement($id, $validated['reason'], (int) $request->user()->id);

        return back()->with('success', 'Receivable settlement reversed successfully.');
    }
}
