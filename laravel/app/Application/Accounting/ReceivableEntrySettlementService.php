<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReceivableEntrySettlementService
{
    public function __construct(
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{target_receivable_entry_id?: string, receivable_entry_id?: string, amount_minor: int}>  $lines
     * @return list<ReceivableEntrySettlement>
     */
    public function settleCredit(string $sourceCreditEntryId, array $lines, int $actorId, ?string $idempotencyKey = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => ['Settlement lines cannot be empty.'],
            ]);
        }

        $targetIds = [];
        foreach ($lines as $index => $line) {
            $targetId = $line['target_receivable_entry_id'] ?? $line['receivable_entry_id'] ?? null;
            if (! is_string($targetId) || $targetId === '') {
                throw ValidationException::withMessages([
                    "lines.{$index}.target_receivable_entry_id" => ['Every settlement line must reference a target receivable entry.'],
                ]);
            }
            if ($targetId === $sourceCreditEntryId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.target_receivable_entry_id" => ['Cannot settle a receivable entry against itself.'],
                ]);
            }
            $targetIds[] = $targetId;
        }

        if (count($targetIds) !== count(array_unique($targetIds))) {
            throw ValidationException::withMessages([
                'lines' => ['Duplicate target receivable entry IDs in single settlement command.'],
            ]);
        }

        $idempotencyKey ??= 'receivable_settlement:source:'.$sourceCreditEntryId.':settle:'.md5(json_encode($lines));

        $result = $this->idempotencyStore->run(
            operation: 'receivable_entry_settlement.create',
            rawKey: $idempotencyKey,
            callback: function () use ($sourceCreditEntryId, $lines, $targetIds, $actorId): array {
                return DB::transaction(function () use ($sourceCreditEntryId, $lines, $targetIds, $actorId): array {
                    // 1. Lock all involved entries in deterministic ascending ID order
                    $allIds = array_unique(array_merge([$sourceCreditEntryId], $targetIds));
                    sort($allIds);

                    /** @var Collection<int, ReceivableEntry> $lockedEntries */
                    $lockedEntries = ReceivableEntry::query()
                        ->whereIn('id', $allIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    /** @var ReceivableEntry|null $sourceEntry */
                    $sourceEntry = $lockedEntries->get($sourceCreditEntryId);

                    if (! $sourceEntry) {
                        throw ValidationException::withMessages([
                            'source_receivable_entry_id' => ["Source receivable entry [{$sourceCreditEntryId}] does not exist."],
                        ]);
                    }

                    $sourceCreditCapacity = (int) $sourceEntry->credit_minor - (int) $sourceEntry->debit_minor;
                    if ($sourceCreditCapacity <= 0) {
                        throw ValidationException::withMessages([
                            'source_receivable_entry_id' => ["Source entry [{$sourceCreditEntryId}] is not an open credit AR item."],
                        ]);
                    }

                    // Compute current active source settlements
                    $activeSourceSettledSum = (int) ReceivableEntrySettlement::query()
                        ->where('source_receivable_entry_id', $sourceCreditEntryId)
                        ->where('status', 'active')
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->pluck('amount_minor')
                        ->sum();

                    $remainingSourceCredit = $sourceCreditCapacity - $activeSourceSettledSum;

                    $totalRequested = 0;
                    foreach ($lines as $line) {
                        $amount = $line['amount_minor'] ?? 0;
                        if (! is_int($amount) || $amount <= 0) {
                            throw ValidationException::withMessages([
                                'amount_minor' => ['Settlement amount must be a positive integer.'],
                            ]);
                        }
                        $totalRequested += $amount;
                    }

                    if ($totalRequested > $remainingSourceCredit) {
                        throw ValidationException::withMessages([
                            'amount_minor' => ["Total settlement amount [{$totalRequested}] exceeds source entry remaining credit [{$remainingSourceCredit}]."],
                        ]);
                    }

                    $createdSettlements = [];
                    $now = now();

                    foreach ($lines as $line) {
                        $targetId = $line['target_receivable_entry_id'] ?? $line['receivable_entry_id'];
                        $lineAmount = $line['amount_minor'];
                        $target = $lockedEntries->get($targetId);

                        if (! $target) {
                            throw ValidationException::withMessages([
                                'target_receivable_entry_id' => ["Target receivable entry [{$targetId}] does not exist."],
                            ]);
                        }

                        if ((string) $target->customer_id !== (string) $sourceEntry->customer_id) {
                            throw ValidationException::withMessages([
                                'customer_id' => ["Target entry [{$targetId}] customer does not match source entry customer."],
                            ]);
                        }

                        if ($target->currency !== $sourceEntry->currency) {
                            throw ValidationException::withMessages([
                                'currency' => ["Target entry [{$targetId}] currency [{$target->currency}] does not match source entry currency [{$sourceEntry->currency}]."],
                            ]);
                        }

                        $targetDebitCapacity = (int) $target->debit_minor - (int) $target->credit_minor;
                        if ($targetDebitCapacity <= 0) {
                            throw ValidationException::withMessages([
                                'target_receivable_entry_id' => ["Target entry [{$targetId}] is not a positive debit AR item."],
                            ]);
                        }

                        $activeAllocatedSum = (int) ReceivableAllocation::query()
                            ->where('receivable_entry_id', $targetId)
                            ->where('status', 'active')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->pluck('amount_minor')
                            ->sum();

                        $activeTargetSettledSum = (int) ReceivableEntrySettlement::query()
                            ->where('target_receivable_entry_id', $targetId)
                            ->where('status', 'active')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->pluck('amount_minor')
                            ->sum();

                        $remainingTargetDebit = $targetDebitCapacity - $activeAllocatedSum - $activeTargetSettledSum;

                        if ($lineAmount > $remainingTargetDebit) {
                            throw ValidationException::withMessages([
                                'amount_minor' => ["Settlement amount [{$lineAmount}] exceeds target entry remaining debit [{$remainingTargetDebit}]."],
                            ]);
                        }

                        $settlement = ReceivableEntrySettlement::query()->create([
                            'customer_id' => $sourceEntry->customer_id,
                            'source_receivable_entry_id' => $sourceEntry->id,
                            'target_receivable_entry_id' => $targetId,
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
                            entityType: 'receivable_entry_settlement',
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
                return $item instanceof ReceivableEntrySettlement ? $item : ReceivableEntrySettlement::query()->findOrFail($item['id']);
            }, $result->value);
        }

        return $result->value;
    }

    public function reverseSettlement(string $settlementId, string $reason, int $actorId, ?string $idempotencyKey = null): ReceivableEntrySettlement
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Reversal reason is required.'],
            ]);
        }

        $idempotencyKey ??= "receivable_settlement:{$settlementId}:reverse";

        $result = $this->idempotencyStore->run(
            operation: 'receivable_entry_settlement.reverse',
            rawKey: $idempotencyKey,
            callback: function () use ($settlementId, $reason, $actorId): ReceivableEntrySettlement {
                return DB::transaction(function () use ($settlementId, $reason, $actorId): ReceivableEntrySettlement {
                    /** @var ReceivableEntrySettlement $settlement */
                    $settlement = ReceivableEntrySettlement::query()
                        ->where('id', $settlementId)
                        ->firstOrFail();

                    if ($settlement->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => ["Settlement [{$settlementId}] is already reversed."],
                        ]);
                    }

                    if ($settlement->status !== 'active') {
                        throw new InvalidArgumentException("Settlement [{$settlementId}] cannot be reversed from status [{$settlement->status}].");
                    }

                    // Lock involved entries and settlement in deterministic ascending order
                    $entryIds = array_unique([$settlement->source_receivable_entry_id, $settlement->target_receivable_entry_id]);
                    sort($entryIds);

                    ReceivableEntry::query()
                        ->whereIn('id', $entryIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get();

                    $settlement = ReceivableEntrySettlement::query()
                        ->where('id', $settlementId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($settlement->status === 'reversed') {
                        throw ValidationException::withMessages([
                            'status' => ["Settlement [{$settlementId}] is already reversed."],
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
                        entityType: 'receivable_entry_settlement',
                        entityId: $settlement->id,
                        before: $beforeSettlement,
                        after: $settlement->fresh()->toArray(),
                    );

                    return $settlement->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return ReceivableEntrySettlement::query()->findOrFail($settlementId);
        }

        /** @var ReceivableEntrySettlement */
        return $result->value;
    }
}
