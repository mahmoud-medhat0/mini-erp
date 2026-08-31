<?php

namespace App\Application\Accounting;

use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use App\Models\Supplier;

class PayableEntrySettlementPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $sourceEntryId = $filters['source_entry_id'] ?? null;

        $debitEntriesQuery = PayableEntry::query()
            ->with('supplier')
            ->whereRaw('debit_minor > credit_minor');

        if ($supplierId) {
            $debitEntriesQuery->where('supplier_id', $supplierId);
        }

        $debitEntries = $debitEntriesQuery->orderBy('entry_date', 'desc')->get()
            ->map(fn (PayableEntry $entry): array => array_merge($entry->toArray(), [
                'remaining_minor' => $this->sourceDebitRemaining($entry),
            ]))
            ->filter(fn (array $entry): bool => $entry['remaining_minor'] > 0)
            ->values();

        $selectedSourceEntry = null;
        $openTargetCredits = [];

        if ($sourceEntryId) {
            $rawSource = PayableEntry::query()->with('supplier')->find($sourceEntryId);

            if ($rawSource) {
                $selectedSourceEntry = array_merge($rawSource->toArray(), [
                    'remaining_minor' => $this->sourceDebitRemaining($rawSource),
                ]);

                $openTargetCredits = $this->openTargetCredits($rawSource);
            }
        }

        return [
            'debitEntries' => $debitEntries,
            'selectedSourceEntry' => $selectedSourceEntry,
            'openTargetCredits' => $openTargetCredits,
            'existingSettlements' => PayableEntrySettlement::query()
                ->with(['supplier', 'sourcePayableEntry', 'targetPayableEntry', 'creator', 'reverser'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => [
                'supplier_id' => $supplierId,
                'source_entry_id' => $sourceEntryId,
            ],
        ];
    }

    private function sourceDebitRemaining(PayableEntry $entry): int
    {
        $capacity = (int) $entry->debit_minor - (int) $entry->credit_minor;
        $settledSum = (int) PayableEntrySettlement::query()
            ->where('source_payable_entry_id', $entry->id)
            ->where('status', 'active')
            ->sum('amount_minor');

        return $capacity - $settledSum;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openTargetCredits(PayableEntry $source): array
    {
        $openTargets = [];
        $creditEntries = PayableEntry::query()
            ->where('supplier_id', $source->supplier_id)
            ->where('currency', $source->currency)
            ->where('id', '!=', $source->id)
            ->whereRaw('credit_minor > debit_minor')
            ->orderBy('entry_date', 'asc')
            ->get();

        foreach ($creditEntries as $creditEntry) {
            $remaining = $this->targetCreditRemaining($creditEntry);

            if ($remaining > 0) {
                $openTargets[] = array_merge($creditEntry->toArray(), [
                    'remaining_minor' => $remaining,
                ]);
            }
        }

        return $openTargets;
    }

    private function targetCreditRemaining(PayableEntry $entry): int
    {
        $capacity = (int) $entry->credit_minor - (int) $entry->debit_minor;
        $allocatedSum = (int) PayableAllocation::query()
            ->where('payable_entry_id', $entry->id)
            ->where('status', 'active')
            ->sum('amount_minor');
        $settledSum = (int) PayableEntrySettlement::query()
            ->where('target_payable_entry_id', $entry->id)
            ->where('status', 'active')
            ->sum('amount_minor');

        return $capacity - $allocatedSum - $settledSum;
    }
}
