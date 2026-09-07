<?php

namespace App\Application\Accounting;

use App\Models\Customer;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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
            'existingSettlements' => [],
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => [
                'customer_id' => $customerId,
                'source_entry_id' => $sourceEntryId,
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $customerId = (string) ($filters['customer_id'] ?? '');
        $query = ReceivableEntrySettlement::query()
            ->with(['customer', 'sourceReceivableEntry', 'targetReceivableEntry', 'creator', 'reverser'])
            ->leftJoin('customer', 'customer.id', '=', 'receivable_entry_settlement.customer_id')
            ->select('receivable_entry_settlement.*')
            ->when($customerId !== '', fn (Builder $builder) => $builder
                ->where('receivable_entry_settlement.customer_id', $customerId));

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(COALESCE(CAST(receivable_entry_settlement.source_receivable_entry_id AS TEXT), '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(receivable_entry_settlement.target_receivable_entry_id AS TEXT), '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(receivable_entry_settlement.status, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(receivable_entry_settlement.currency, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(customer.code, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(customer.name AS TEXT), '')) LIKE ?", [$like]);
                });
            })
            ->orderColumn('settled_at', 'receivable_entry_settlement.settled_at $1')
            ->orderColumn('customer_name', 'customer.code $1')
            ->orderColumn('source_receivable_entry_id', 'receivable_entry_settlement.source_receivable_entry_id $1')
            ->orderColumn('target_receivable_entry_id', 'receivable_entry_settlement.target_receivable_entry_id $1')
            ->orderColumn('amount_minor', 'receivable_entry_settlement.amount_minor $1')
            ->orderColumn('status', 'receivable_entry_settlement.status $1')
            ->addColumn('customer_name', fn (ReceivableEntrySettlement $row) => $row->customer?->name ?? '')
            ->addColumn('actions', fn (): null => null)
            ->toJson();
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
