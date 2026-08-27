<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PayableEntrySettlementService
{
    public function __construct(
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{target_payable_entry_id?: string, payable_entry_id?: string, amount_minor: int}>  $lines
     * @return list<PayableEntrySettlement>
     */
    public function settleDebit(string $sourceDebitEntryId, array $lines, int $actorId, ?string $idempotencyKey = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => [__('Settlement lines cannot be empty.')],
            ]);
        }

        $targetIds = [];
        foreach ($lines as $index => $line) {
            $targetId = $line['target_payable_entry_id'] ?? $line['payable_entry_id'] ?? null;
            if (! is_string($targetId) || $targetId === '') {
                throw ValidationException::withMessages([
                    "lines.{$index}.target_payable_entry_id" => [__('Every settlement line must reference a target payable entry.')],
                ]);
            }
            if ($targetId === $sourceDebitEntryId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.target_payable_entry_id" => [__('Cannot settle a payable entry against itself.')],
                ]);
            }
            $targetIds[] = $targetId;
        }

        if (count($targetIds) !== count(array_unique($targetIds))) {
            throw ValidationException::withMessages([
                'lines' => [__('Duplicate target payable entry IDs in single settlement command.')],
            ]);
        }

        $idempotencyKey ??= 'payable_settlement:source:'.$sourceDebitEntryId.':settle:'.md5(json_encode($lines));

        $result = $this->idempotencyStore->run(
            operation: 'payable_entry_settlement.create',
            rawKey: $idempotencyKey,
            callback: function () use ($sourceDebitEntryId, $lines, $targetIds, $actorId): array {
                return DB::transaction(function () use ($sourceDebitEntryId, $lines, $targetIds, $actorId): array {
                    // 1. Lock all involved entries in deterministic ascending ID order
                    $allIds = array_unique(array_merge([$sourceDebitEntryId], $targetIds));
                    sort($allIds);

                    /** @var Collection<int, PayableEntry> $lockedEntries */
                    $lockedEntries = PayableEntry::query()
                        ->whereIn('id', $allIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    /** @var PayableEntry|null $sourceEntry */
                    $sourceEntry = $lockedEntries->get($sourceDebitEntryId);

                    if (! $sourceEntry) {
                        throw ValidationException::withMessages([
                            'source_payable_entry_id' => [__('Source payable entry [:entry] does not exist.', [
                                'entry' => $sourceDebitEntryId,
                            ])],
                        ]);
                    }

                    $sourceDebitCapacity = (int) $sourceEntry->debit_minor - (int) $sourceEntry->credit_minor;
                    if ($sourceDebitCapacity <= 0) {
                        throw ValidationException::withMessages([
                            'source_payable_entry_id' => [__('Source entry [:entry] is not an open debit AP item.', [
                                'entry' => $sourceDebitEntryId,
                            ])],
                        ]);
                    }

                    // Compute current active source settlements
                    $activeSourceSettledSum = (int) PayableEntrySettlement::query()
                        ->where('source_payable_entry_id', $sourceDebitEntryId)
                        ->where('status', 'active')
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->pluck('amount_minor')
                        ->sum();

                    $remainingSourceDebit = $sourceDebitCapacity - $activeSourceSettledSum;

                    $totalRequested = 0;
                    foreach ($lines as $line) {
                        $amount = $line['amount_minor'] ?? 0;
                        if (! is_int($amount) || $amount <= 0) {
                            throw ValidationException::withMessages([
                                'amount_minor' => [__('Settlement amount must be a positive integer.')],
                            ]);
                        }
                        $totalRequested += $amount;
                    }

                    if ($totalRequested > $remainingSourceDebit) {
                        throw ValidationException::withMessages([
                            'amount_minor' => [__('Total settlement amount [:total] exceeds source entry remaining debit [:remaining].', [
                                'total' => $totalRequested,
                                'remaining' => $remainingSourceDebit,
                            ])],
                        ]);
                    }

                    $createdSettlements = [];
                    $now = now();

                    foreach ($lines as $line) {
                        $targetId = $line['target_payable_entry_id'] ?? $line['payable_entry_id'];
                        $lineAmount = $line['amount_minor'];
                        $target = $lockedEntries->get($targetId);

                        if (! $target) {
                            throw ValidationException::withMessages([
                                'target_payable_entry_id' => [__('Target payable entry [:entry] does not exist.', [
                                    'entry' => $targetId,
                                ])],
                            ]);
                        }

                        if ((string) $target->supplier_id !== (string) $sourceEntry->supplier_id) {
                            throw ValidationException::withMessages([
                                'supplier_id' => [__('Target entry [:entry] supplier does not match source entry supplier.', [
                                    'entry' => $targetId,
                                ])],
                            ]);
                        }

                        if ($target->currency !== $sourceEntry->currency) {
                            throw ValidationException::withMessages([
                                'currency' => [__('Target entry [:entry] currency [:entry_currency] does not match source entry currency [:source_currency].', [
                                    'entry' => $targetId,
                                    'entry_currency' => $target->currency,
                                    'source_currency' => $sourceEntry->currency,
                                ])],
                            ]);
                        }

                        $targetCreditCapacity = (int) $target->credit_minor - (int) $target->debit_minor;
                        if ($targetCreditCapacity <= 0) {
                            throw ValidationException::withMessages([
                                'target_payable_entry_id' => [__('Target entry [:entry] is not a positive credit AP item.', [
                                    'entry' => $targetId,
                                ])],
                            ]);
                        }

                        $activeAllocatedSum = (int) PayableAllocation::query()
                            ->where('payable_entry_id', $targetId)
                            ->where('status', 'active')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->pluck('amount_minor')
                            ->sum();

                        $activeTargetSettledSum = (int) PayableEntrySettlement::query()
                            ->where('target_payable_entry_id', $targetId)
                            ->where('status', 'active')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->pluck('amount_minor')
                            ->sum();

                        $remainingTargetCredit = $targetCreditCapacity - $activeAllocatedSum - $activeTargetSettledSum;

                        if ($lineAmount > $remainingTargetCredit) {
                            throw ValidationException::withMessages([
                                'amount_minor' => [__('Settlement amount [:amount] exceeds target entry remaining credit [:remaining].', [
                                    'amount' => $lineAmount,
                                    'remaining' => $remainingTargetCredit,
                                ])],
                            ]);
                        }

                        $settlement = PayableEntrySettlement::query()->create([
                            'supplier_id' => $sourceEntry->supplier_id,
                            'source_payable_entry_id' => $sourceEntry->id,
                            'target_payable_entry_id' => $targetId,
                            'currency' => $sourceEntry->currency,
                            'amount_minor' => $lineAmount,
                            'status' => 'active',
                            'settled_at' => $now,
                            'reason' => $line['reason'] ?? null,
                            'created_by' => $actorId,
                        ]);

                        $this->auditLogger->record(
                            actorId: $actorId,
                            action: 'create',
                            entityType: 'payable_entry_settlement',
                            entityId: $settlement->id,
                            before: null,
                            after: $settlement->fresh()->toArray(),
                        );

                        $createdSettlements[] = $settlement;
                    }

                    return $createdSettlements;
                });
            }
        );

        if (is_array($result->value)) {
            return array_map(function ($item) {
                return $item instanceof PayableEntrySettlement ? $item : PayableEntrySettlement::query()->findOrFail($item['id']);
            }, $result->value);
        }

        return $result->value;
    }

    public function reverseSettlement(string $settlementId, string $reason, int $actorId, ?string $idempotencyKey = null): PayableEntrySettlement
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => [__('Reversal reason is required.')],
            ]);
        }

        $idempotencyKey ??= "payable_settlement:{$settlementId}:reverse";

        $result = $this->idempotencyStore->run(
            operation: 'payable_entry_settlement.reverse',
            rawKey: $idempotencyKey,
            callback: function () use ($settlementId, $reason, $actorId): PayableEntrySettlement {
                return DB::transaction(function () use ($settlementId, $reason, $actorId): PayableEntrySettlement {
                    /** @var PayableEntrySettlement $settlement */
                    $settlement = PayableEntrySettlement::query()
                        ->where('id', $settlementId)
                        ->firstOrFail();

                    if ($settlement->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => [__('Settlement :id is already reversed.', ['id' => $settlementId])],
                        ]);
                    }

                    if ($settlement->status !== 'active') {
                        throw new InvalidArgumentException(__('Settlement :id cannot be reversed from status :status.', [
                            'id' => $settlementId,
                            'status' => $settlement->status,
                        ]));
                    }

                    // Lock involved entries and settlement in deterministic ascending order
                    $entryIds = array_unique([$settlement->source_payable_entry_id, $settlement->target_payable_entry_id]);
                    sort($entryIds);

                    PayableEntry::query()
                        ->whereIn('id', $entryIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get();

                    $settlement = PayableEntrySettlement::query()
                        ->where('id', $settlementId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($settlement->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => [__('Settlement :id is already reversed.', ['id' => $settlementId])],
                        ]);
                    }

                    $beforeSettlement = $settlement->toArray();
                    $settlement->update([
                        'status' => 'reversed',
                        'reversed_at' => now(),
                        'reversed_reason' => $reason,
                        'reversed_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'reverse',
                        entityType: 'payable_entry_settlement',
                        entityId: $settlement->id,
                        before: $beforeSettlement,
                        after: $settlement->fresh()->toArray(),
                    );

                    return $settlement->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return PayableEntrySettlement::query()->findOrFail($settlementId);
        }

        /** @var PayableEntrySettlement */
        return $result->value;
    }
}
