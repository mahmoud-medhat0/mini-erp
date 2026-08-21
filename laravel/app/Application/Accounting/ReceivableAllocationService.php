<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\CustomerReceipt;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReceivableAllocationService
{
    public function __construct(
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{receivable_entry_id: string, amount_minor: int}>  $lines
     * @return list<ReceivableAllocation>
     */
    public function allocateReceipt(string $receiptId, array $lines, int $actorId, ?string $idempotencyKey = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => ['Allocation lines cannot be empty.'],
            ]);
        }

        foreach ($lines as $line) {
            if (! isset($line['receivable_entry_id']) || ! is_string($line['receivable_entry_id']) || $line['receivable_entry_id'] === '') {
                throw ValidationException::withMessages([
                    'receivable_entry_id' => ['Every allocation line must reference a receivable entry.'],
                ]);
            }
        }

        $targetIds = array_column($lines, 'receivable_entry_id');
        if (count($targetIds) !== count(array_unique($targetIds))) {
            throw ValidationException::withMessages([
                'lines' => ['Duplicate target receivable entry IDs in single allocation command.'],
            ]);
        }

        $idempotencyKey ??= 'receivable_allocation:receipt:'.$receiptId.':allocate:'.md5(json_encode($lines));

        $result = $this->idempotencyStore->run(
            operation: 'receivable_allocation.allocate',
            rawKey: $idempotencyKey,
            callback: function () use ($receiptId, $lines, $targetIds, $actorId): array {
                return DB::transaction(function () use ($receiptId, $lines, $targetIds, $actorId): array {
                    // 1. Lock Customer Receipt Row
                    /** @var CustomerReceipt $receipt */
                    $receipt = CustomerReceipt::query()
                        ->where('id', $receiptId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($receipt->status !== 'posted') {
                        throw ValidationException::withMessages([
                            'status' => ["Only posted receipts can be allocated. Current status: [{$receipt->status}]."],
                        ]);
                    }

                    // Calculate total requested allocation
                    $totalRequested = 0;
                    foreach ($lines as $line) {
                        $amount = $line['amount_minor'] ?? 0;
                        if (! is_int($amount) || $amount <= 0) {
                            throw ValidationException::withMessages([
                                'amount_minor' => ['Allocation amount must be a positive integer.'],
                            ]);
                        }
                        $totalRequested += $amount;
                    }

                    if ($totalRequested > $receipt->unapplied_minor) {
                        throw ValidationException::withMessages([
                            'amount_minor' => ["Allocation total [{$totalRequested}] exceeds receipt unapplied amount [{$receipt->unapplied_minor}]."],
                        ]);
                    }

                    // 2. Lock target ReceivableEntry rows in deterministic ascending ID order
                    sort($targetIds);

                    /** @var Collection<int, ReceivableEntry> $targetEntries */
                    $targetEntries = ReceivableEntry::query()
                        ->whereIn('id', $targetIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($targetEntries->count() !== count($targetIds)) {
                        throw ValidationException::withMessages([
                            'receivable_entry_id' => ['One or more target receivable entries do not exist.'],
                        ]);
                    }

                    $createdAllocations = [];
                    $now = now();

                    foreach ($lines as $line) {
                        $targetId = $line['receivable_entry_id'];
                        $lineAmount = $line['amount_minor'];
                        $target = $targetEntries->get($targetId);

                        if (! $target) {
                            throw ValidationException::withMessages([
                                'receivable_entry_id' => ["Target receivable entry [{$targetId}] does not exist."],
                            ]);
                        }

                        if ((string) $target->customer_id !== (string) $receipt->customer_id) {
                            throw ValidationException::withMessages([
                                'customer_id' => ["Target entry [{$targetId}] customer does not match receipt customer."],
                            ]);
                        }

                        if ($target->currency !== $receipt->currency) {
                            throw ValidationException::withMessages([
                                'currency' => ["Target entry [{$targetId}] currency [{$target->currency}] does not match receipt currency [{$receipt->currency}]."],
                            ]);
                        }

                        $allocatableAmount = $target->debit_minor - $target->credit_minor;
                        if ($allocatableAmount <= 0) {
                            throw ValidationException::withMessages([
                                'receivable_entry_id' => ["Target entry [{$targetId}] is not a positive AR item."],
                            ]);
                        }

                        // Lock active allocations after the target row so concurrent allocators/reversals serialize.
                        $activeAllocatedSum = (int) ReceivableAllocation::query()
                            ->where('receivable_entry_id', $targetId)
                            ->where('status', 'active')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->pluck('amount_minor')
                            ->sum();

                        $remainingAllocatable = $allocatableAmount - $activeAllocatedSum;

                        if ($lineAmount > $remainingAllocatable) {
                            throw ValidationException::withMessages([
                                'amount_minor' => ["Allocation amount [{$lineAmount}] exceeds target remaining allocatable amount [{$remainingAllocatable}]."],
                            ]);
                        }

                        // Insert ReceivableAllocation
                        $allocation = ReceivableAllocation::query()->create([
                            'customer_id' => $receipt->customer_id,
                            'customer_receipt_id' => $receipt->id,
                            'receivable_entry_id' => $targetId,
                            'currency' => $receipt->currency,
                            'amount_minor' => $lineAmount,
                            'status' => 'active',
                            'allocated_at' => $now,
                            'created_by' => $actorId,
                        ]);

                        $this->auditLogger->record(
                            actorId: $actorId,
                            action: 'create',
                            entityType: 'receivable_allocation',
                            entityId: $allocation->id,
                            before: null,
                            after: $allocation->fresh()->toArray(),
                        );

                        $createdAllocations[] = $allocation;
                    }

                    // 3. Update Receipt Balances
                    $beforeReceipt = $receipt->toArray();
                    $receipt->update([
                        'allocated_minor' => $receipt->allocated_minor + $totalRequested,
                        'unapplied_minor' => $receipt->unapplied_minor - $totalRequested,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'allocate',
                        entityType: 'customer_receipt',
                        entityId: $receipt->id,
                        before: $beforeReceipt,
                        after: $receipt->fresh()->toArray(),
                    );

                    return $createdAllocations;
                });
            }
        );

        if (is_array($result->value)) {
            return array_map(function ($item) {
                return $item instanceof ReceivableAllocation ? $item : ReceivableAllocation::query()->findOrFail($item['id']);
            }, $result->value);
        }

        return $result->value;
    }

    public function reverseReceiptAllocation(string $allocationId, string $reason, int $actorId, ?string $idempotencyKey = null): ReceivableAllocation
    {
        $idempotencyKey ??= "receivable_allocation:{$allocationId}:reverse";

        $result = $this->idempotencyStore->run(
            operation: 'receivable_allocation.reverse',
            rawKey: $idempotencyKey,
            callback: function () use ($allocationId, $reason, $actorId): ReceivableAllocation {
                return DB::transaction(function () use ($allocationId, $reason, $actorId): ReceivableAllocation {
                    /** @var ReceivableAllocation $allocation */
                    $allocation = ReceivableAllocation::query()
                        ->where('id', $allocationId)
                        ->firstOrFail();

                    if ($allocation->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => ["Allocation [{$allocationId}] is already reversed."],
                        ]);
                    }

                    if ($allocation->status !== 'active') {
                        throw new InvalidArgumentException("Allocation [{$allocationId}] cannot be reversed from status [{$allocation->status}].");
                    }

                    // 1. Lock Parent Receipt Row
                    /** @var CustomerReceipt $receipt */
                    $receipt = CustomerReceipt::query()
                        ->where('id', $allocation->customer_receipt_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // 2. Lock Target Receivable Entry Row
                    ReceivableEntry::query()
                        ->where('id', $allocation->receivable_entry_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // 3. Lock Allocation Row after the parent and target rows to match allocation lock order.
                    $allocation = ReceivableAllocation::query()
                        ->where('id', $allocationId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($allocation->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => ["Allocation [{$allocationId}] is already reversed."],
                        ]);
                    }

                    if ($allocation->status !== 'active') {
                        throw new InvalidArgumentException("Allocation [{$allocationId}] cannot be reversed from status [{$allocation->status}].");
                    }

                    // 4. Update Allocation Status
                    $beforeAlloc = $allocation->toArray();
                    $allocation->update([
                        'status' => 'reversed',
                        'reversed_at' => now(),
                        'reversed_reason' => $reason,
                        'reversed_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'reverse',
                        entityType: 'receivable_allocation',
                        entityId: $allocation->id,
                        before: $beforeAlloc,
                        after: $allocation->fresh()->toArray(),
                    );

                    // 5. Restore Receipt Balances
                    $beforeReceipt = $receipt->toArray();
                    $receipt->update([
                        'allocated_minor' => $receipt->allocated_minor - $allocation->amount_minor,
                        'unapplied_minor' => $receipt->unapplied_minor + $allocation->amount_minor,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'reverse_allocation',
                        entityType: 'customer_receipt',
                        entityId: $receipt->id,
                        before: $beforeReceipt,
                        after: $receipt->fresh()->toArray(),
                    );

                    return $allocation->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return ReceivableAllocation::query()->findOrFail($allocationId);
        }

        /** @var ReceivableAllocation */
        return $result->value;
    }
}
