<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\SupplierPayment;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PayableAllocationService
{
    public function __construct(
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{payable_entry_id: string, amount_minor: int}>  $lines
     * @return list<PayableAllocation>
     */
    public function allocatePayment(string $paymentId, array $lines, int $actorId, ?string $idempotencyKey = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => [__('Allocation lines cannot be empty.')],
            ]);
        }

        foreach ($lines as $line) {
            if (! isset($line['payable_entry_id']) || ! is_string($line['payable_entry_id']) || $line['payable_entry_id'] === '') {
                throw ValidationException::withMessages([
                    'payable_entry_id' => [__('Every allocation line must reference a payable entry.')],
                ]);
            }
        }

        $targetIds = array_column($lines, 'payable_entry_id');
        if (count($targetIds) !== count(array_unique($targetIds))) {
            throw ValidationException::withMessages([
                'lines' => [__('Duplicate target payable entry IDs in single allocation command.')],
            ]);
        }

        $idempotencyKey ??= 'payable_allocation:payment:'.$paymentId.':allocate:'.md5(json_encode($lines));

        $result = $this->idempotencyStore->run(
            operation: 'payable_allocation.allocate',
            rawKey: $idempotencyKey,
            callback: function () use ($paymentId, $lines, $targetIds, $actorId): array {
                return DB::transaction(function () use ($paymentId, $lines, $targetIds, $actorId): array {
                    // 1. Lock Supplier Payment Row
                    /** @var SupplierPayment $payment */
                    $payment = SupplierPayment::query()
                        ->where('id', $paymentId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($payment->status !== 'posted') {
                        throw ValidationException::withMessages([
                            'status' => [__('Only posted payments can be allocated. Current status: [:status].', [
                                'status' => $payment->status,
                            ])],
                        ]);
                    }

                    // Calculate total requested allocation
                    $totalRequested = 0;
                    foreach ($lines as $line) {
                        $amount = $line['amount_minor'] ?? 0;
                        if (! is_int($amount) || $amount <= 0) {
                            throw ValidationException::withMessages([
                                'amount_minor' => [__('Allocation amount must be a positive integer.')],
                            ]);
                        }
                        $totalRequested += $amount;
                    }

                    if ($totalRequested > $payment->unapplied_minor) {
                        throw ValidationException::withMessages([
                            'amount_minor' => [__('Allocation total [:total] exceeds payment unapplied amount [:unapplied].', [
                                'total' => $totalRequested,
                                'unapplied' => $payment->unapplied_minor,
                            ])],
                        ]);
                    }

                    // 2. Lock target PayableEntry rows in deterministic ascending ID order
                    sort($targetIds);

                    /** @var Collection<int, PayableEntry> $targetEntries */
                    $targetEntries = PayableEntry::query()
                        ->whereIn('id', $targetIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($targetEntries->count() !== count($targetIds)) {
                        throw ValidationException::withMessages([
                            'payable_entry_id' => [__('One or more target payable entries do not exist.')],
                        ]);
                    }

                    $createdAllocations = [];
                    $now = now();

                    foreach ($lines as $line) {
                        $targetId = $line['payable_entry_id'];
                        $lineAmount = $line['amount_minor'];
                        $target = $targetEntries->get($targetId);

                        if (! $target) {
                            throw ValidationException::withMessages([
                                'payable_entry_id' => [__('Target payable entry [:entry] does not exist.', [
                                    'entry' => $targetId,
                                ])],
                            ]);
                        }

                        if ((string) $target->supplier_id !== (string) $payment->supplier_id) {
                            throw ValidationException::withMessages([
                                'supplier_id' => [__('Target entry [:entry] supplier does not match payment supplier.', [
                                    'entry' => $targetId,
                                ])],
                            ]);
                        }

                        if ($target->currency !== $payment->currency) {
                            throw ValidationException::withMessages([
                                'currency' => [__('Target entry [:entry] currency [:entry_currency] does not match payment currency [:payment_currency].', [
                                    'entry' => $targetId,
                                    'entry_currency' => $target->currency,
                                    'payment_currency' => $payment->currency,
                                ])],
                            ]);
                        }

                        $allocatableAmount = $target->credit_minor - $target->debit_minor;
                        if ($allocatableAmount <= 0) {
                            throw ValidationException::withMessages([
                                'payable_entry_id' => [__('Target entry [:entry] is not a positive AP item.', [
                                    'entry' => $targetId,
                                ])],
                            ]);
                        }

                        // Lock active allocations after the target row so concurrent allocators/reversals serialize.
                        $activeAllocatedSum = (int) PayableAllocation::query()
                            ->where('payable_entry_id', $targetId)
                            ->where('status', 'active')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->pluck('amount_minor')
                            ->sum();

                        $remainingAllocatable = $allocatableAmount - $activeAllocatedSum;

                        if ($lineAmount > $remainingAllocatable) {
                            throw ValidationException::withMessages([
                                'amount_minor' => [__('Allocation amount [:amount] exceeds target remaining allocatable amount [:remaining].', [
                                    'amount' => $lineAmount,
                                    'remaining' => $remainingAllocatable,
                                ])],
                            ]);
                        }

                        // Insert PayableAllocation
                        $allocation = PayableAllocation::query()->create([
                            'supplier_id' => $payment->supplier_id,
                            'supplier_payment_id' => $payment->id,
                            'payable_entry_id' => $targetId,
                            'currency' => $payment->currency,
                            'amount_minor' => $lineAmount,
                            'status' => 'active',
                            'allocated_at' => $now,
                            'created_by' => $actorId,
                        ]);

                        $this->auditLogger->record(
                            actorId: $actorId,
                            action: 'create',
                            entityType: 'payable_allocation',
                            entityId: $allocation->id,
                            before: null,
                            after: $allocation->fresh()->toArray(),
                        );

                        $createdAllocations[] = $allocation;
                    }

                    // 3. Update Payment Balances
                    $beforePayment = $payment->toArray();
                    $payment->update([
                        'allocated_minor' => $payment->allocated_minor + $totalRequested,
                        'unapplied_minor' => $payment->unapplied_minor - $totalRequested,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'allocate',
                        entityType: 'supplier_payment',
                        entityId: $payment->id,
                        before: $beforePayment,
                        after: $payment->fresh()->toArray(),
                    );

                    return $createdAllocations;
                });
            }
        );

        if (is_array($result->value)) {
            return array_map(function ($item) {
                return $item instanceof PayableAllocation ? $item : PayableAllocation::query()->findOrFail($item['id']);
            }, $result->value);
        }

        return $result->value;
    }

    public function reversePaymentAllocation(string $allocationId, string $reason, int $actorId, ?string $idempotencyKey = null): PayableAllocation
    {
        $idempotencyKey ??= "payable_allocation:{$allocationId}:reverse";

        $result = $this->idempotencyStore->run(
            operation: 'payable_allocation.reverse',
            rawKey: $idempotencyKey,
            callback: function () use ($allocationId, $reason, $actorId): PayableAllocation {
                return DB::transaction(function () use ($allocationId, $reason, $actorId): PayableAllocation {
                    /** @var PayableAllocation $allocation */
                    $allocation = PayableAllocation::query()
                        ->where('id', $allocationId)
                        ->firstOrFail();

                    if ($allocation->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => [__('Allocation :id is already reversed.', ['id' => $allocationId])],
                        ]);
                    }

                    if ($allocation->status !== 'active') {
                        throw new InvalidArgumentException(__('Allocation :id cannot be reversed from status :status.', [
                            'id' => $allocationId,
                            'status' => $allocation->status,
                        ]));
                    }

                    // 1. Lock Parent Payment Row
                    /** @var SupplierPayment $payment */
                    $payment = SupplierPayment::query()
                        ->where('id', $allocation->supplier_payment_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // 2. Lock Target Payable Entry Row
                    PayableEntry::query()
                        ->where('id', $allocation->payable_entry_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // 3. Lock Allocation Row after the parent and target rows to match allocation lock order.
                    $allocation = PayableAllocation::query()
                        ->where('id', $allocationId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($allocation->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => [__('Allocation :id is already reversed.', ['id' => $allocationId])],
                        ]);
                    }

                    if ($allocation->status !== 'active') {
                        throw new InvalidArgumentException(__('Allocation :id cannot be reversed from status :status.', [
                            'id' => $allocationId,
                            'status' => $allocation->status,
                        ]));
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
                        entityType: 'payable_allocation',
                        entityId: $allocation->id,
                        before: $beforeAlloc,
                        after: $allocation->fresh()->toArray(),
                    );

                    // 5. Restore Payment Balances
                    $beforePayment = $payment->toArray();
                    $payment->update([
                        'allocated_minor' => $payment->allocated_minor - $allocation->amount_minor,
                        'unapplied_minor' => $payment->unapplied_minor + $allocation->amount_minor,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'reverse_allocation',
                        entityType: 'supplier_payment',
                        entityId: $payment->id,
                        before: $beforePayment,
                        after: $payment->fresh()->toArray(),
                    );

                    return $allocation->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return PayableAllocation::query()->findOrFail($allocationId);
        }

        /** @var PayableAllocation */
        return $result->value;
    }
}
