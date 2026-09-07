<?php

namespace App\Application\Accounting;

use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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
            'existingSettlements' => [],
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => [
                'supplier_id' => $supplierId,
                'source_entry_id' => $sourceEntryId,
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $supplierId = (string) ($filters['supplier_id'] ?? '');
        $query = PayableEntrySettlement::query()
            ->with(['supplier', 'sourcePayableEntry', 'targetPayableEntry', 'creator', 'reverser'])
            ->leftJoin('supplier', 'supplier.id', '=', 'payable_entry_settlement.supplier_id')
            ->select('payable_entry_settlement.*')
            ->when($supplierId !== '', fn (Builder $builder) => $builder
                ->where('payable_entry_settlement.supplier_id', $supplierId));

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(COALESCE(CAST(payable_entry_settlement.source_payable_entry_id AS TEXT), '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(payable_entry_settlement.target_payable_entry_id AS TEXT), '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(payable_entry_settlement.status, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(payable_entry_settlement.currency, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(supplier.code, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(supplier.name AS TEXT), '')) LIKE ?", [$like]);
                });
            })
            ->orderColumn('settled_at', 'payable_entry_settlement.settled_at $1')
            ->orderColumn('supplier_name', 'supplier.code $1')
            ->orderColumn('source_payable_entry_id', 'payable_entry_settlement.source_payable_entry_id $1')
            ->orderColumn('target_payable_entry_id', 'payable_entry_settlement.target_payable_entry_id $1')
            ->orderColumn('amount_minor', 'payable_entry_settlement.amount_minor $1')
            ->orderColumn('status', 'payable_entry_settlement.status $1')
            ->addColumn('supplier_name', fn (PayableEntrySettlement $row) => $row->supplier?->name ?? '')
            ->addColumn('actions', fn (): null => null)
            ->toJson();
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
