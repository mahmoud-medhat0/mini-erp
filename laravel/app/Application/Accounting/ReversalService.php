<?php

namespace App\Application\Accounting;

use App\Domain\Accounting\AccountingKernel;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReversalService
{
    public function __construct(
        private readonly PostingEngine $postingEngine,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Reverse a posted journal entry.
     */
    public function reverse(JournalEntry $entry, string $reversalPeriodId, int $userId): JournalEntry
    {
        $idempotencyKey = AccountingKernel::postingIdempotencyKey('manual_journal', (string) $entry->id, 'reverse');

        $result = $this->idempotencyStore->run(
            operation: 'accounting.reverse',
            rawKey: $idempotencyKey,
            callback: function () use ($entry, $reversalPeriodId, $userId): JournalEntry {
                $reversalEntry = DB::transaction(function () use ($entry, $reversalPeriodId, $userId): JournalEntry {
                    // 1. Lock reversal period
                    $period = FinancialPeriod::query()
                        ->where('id', $reversalPeriodId)
                        ->lockForUpdate()
                        ->first();

                    if (! $period || ! $period->isOpen()) {
                        throw new InvalidArgumentException(__('Target reversal period is closed or locked.'));
                    }

                    // 2. Lock original journal entry
                    $original = JournalEntry::query()
                        ->where('id', $entry->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($original->status !== 'posted') {
                        throw new InvalidArgumentException(__('Only posted journal entries can be reversed.'));
                    }

                    if ($original->reversal_entry_id) {
                        throw new InvalidArgumentException(__('Journal entry has already been reversed.'));
                    }

                    $today = now()->toDateString();
                    $reversalDate = ($today >= $period->start_date && $today <= $period->end_date)
                        ? $today
                        : $period->start_date;

                    // 3. Create draft reversal journal entry
                    $reversal = JournalEntry::create([
                        'id' => (string) Str::uuid(),
                        'entry_date' => $reversalDate,
                        'financial_period_id' => $period->id,
                        'branch_id' => $original->branch_id,
                        'source_type' => 'reversal',
                        'source_id' => (string) $original->id,
                        'description' => __('Reversal of Journal Entry :number', ['number' => $original->number ?? $original->id]),
                        'reference' => $original->number,
                        'currency' => $original->currency,
                        'fx_rate_e6' => $original->fx_rate_e6,
                        'status' => 'approved',
                        'reverses_entry_id' => $original->id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'approved_by' => $userId,
                        'approved_at' => now(),
                    ]);

                    // 4. Swap debit and credit lines
                    $originalLines = $original->lines()->orderBy('line_no')->get();
                    foreach ($originalLines as $line) {
                        JournalLine::create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $reversal->id,
                            'line_no' => $line->line_no,
                            'account_id' => $line->account_id,
                            'branch_id' => $line->branch_id ?? $original->branch_id,
                            'memo' => __('Reversal line :memo', ['memo' => $line->memo ?? '']),
                            'debit_minor' => $line->credit_minor,
                            'credit_minor' => $line->debit_minor,
                            'currency' => $line->currency,
                            'fx_rate_e6' => $line->fx_rate_e6,
                            'debit_txn_minor' => $line->credit_txn_minor,
                            'credit_txn_minor' => $line->debit_txn_minor,
                        ]);
                    }

                    // 5. Post the reversal entry
                    $postedReversal = $this->postingEngine->post($reversal, $userId, allowControlAccounts: true);

                    // 6. Link original journal entry
                    $original->update([
                        'status' => 'reversed',
                        'reversal_entry_id' => $postedReversal->id,
                        'reversed_by' => $userId,
                        'reversed_at' => now(),
                    ]);

                    return $postedReversal;
                });

                // Audit logging
                $this->auditLogger->record($userId, 'journal_entry.reverse', 'journal_entry', (string) $entry->id, after: [
                    'reversal_entry_id' => $reversalEntry->id,
                    'reversal_number' => $reversalEntry->number,
                ]);

                return $reversalEntry;
            },
            actorId: $userId
        );

        return $result->value instanceof JournalEntry ? $result->value : $entry->fresh();
    }
}
