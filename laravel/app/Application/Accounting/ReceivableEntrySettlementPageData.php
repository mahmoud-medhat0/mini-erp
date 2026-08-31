<?php

namespace App\Application\Accounting;

use App\Models\Customer;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;

class ReceivableEntrySettlementPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $customerId = $filters['customer_id'] ?? null;
        $sourceEntryId = $filters['source_entry_id'] ?? null;

        $creditEntriesQuery = ReceivableEntry::query()
            ->with('customer')
            ->whereRaw('credit_minor > debit_minor');

        if ($customerId) {
            $creditEntriesQuery->where('customer_id', $customerId);
        }

        $creditEntries = $creditEntriesQuery->orderBy('entry_date', 'desc')->get()
            ->map(fn (ReceivableEntry $entry): array => array_merge($entry->toArray(), [
                'remaining_minor' => $this->sourceCreditRemaining($entry),
            ]))
            ->filter(fn (array $entry): bool => $entry['remaining_minor'] > 0)
            ->values();

        $selectedSourceEntry = null;
        $openTargetDebits = [];

        if ($sourceEntryId) {
            $rawSource = ReceivableEntry::query()->with('customer')->find($sourceEntryId);

            if ($rawSource) {
                $selectedSourceEntry = array_merge($rawSource->toArray(), [
                    'remaining_minor' => $this->sourceCreditRemaining($rawSource),
                ]);

                $openTargetDebits = $this->openTargetDebits($rawSource);
            }
        }

        return [
            'creditEntries' => $creditEntries,
            'selectedSourceEntry' => $selectedSourceEntry,
            'openTargetDebits' => $openTargetDebits,
            'existingSettlements' => ReceivableEntrySettlement::query()
                ->with(['customer', 'sourceReceivableEntry', 'targetReceivableEntry', 'creator', 'reverser'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => [
                'customer_id' => $customerId,
                'source_entry_id' => $sourceEntryId,
            ],
        ];
    }

    private function sourceCreditRemaining(ReceivableEntry $entry): int
    {
        $capacity = (int) $entry->credit_minor - (int) $entry->debit_minor;
        $settledSum = (int) ReceivableEntrySettlement::query()
            ->where('source_receivable_entry_id', $entry->id)
            ->where('status', 'active')
            ->sum('amount_minor');

        return $capacity - $settledSum;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openTargetDebits(ReceivableEntry $source): array
    {
        $openTargets = [];
        $debitEntries = ReceivableEntry::query()
            ->where('customer_id', $source->customer_id)
            ->where('currency', $source->currency)
            ->where('id', '!=', $source->id)
            ->whereRaw('debit_minor > credit_minor')
            ->orderBy('entry_date', 'asc')
            ->get();

        foreach ($debitEntries as $debitEntry) {
            $remaining = $this->targetDebitRemaining($debitEntry);

            if ($remaining > 0) {
                $openTargets[] = array_merge($debitEntry->toArray(), [
                    'remaining_minor' => $remaining,
                ]);
            }
        }

        return $openTargets;
    }

    private function targetDebitRemaining(ReceivableEntry $entry): int
    {
        $capacity = (int) $entry->debit_minor - (int) $entry->credit_minor;
        $allocatedSum = (int) ReceivableAllocation::query()
            ->where('receivable_entry_id', $entry->id)
            ->where('status', 'active')
            ->sum('amount_minor');
        $settledSum = (int) ReceivableEntrySettlement::query()
            ->where('target_receivable_entry_id', $entry->id)
            ->where('status', 'active')
            ->sum('amount_minor');

        return $capacity - $allocatedSum - $settledSum;
    }
}
