<?php

namespace App\Application\Accounting;

use App\Domain\Accounting\AccountingKernel;
use App\Domain\Accounting\DraftEntry;
use App\Domain\Accounting\DraftLine;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PostingEngine
{
    public function __construct(
        private readonly NumberSequenceAllocator $sequenceAllocator,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Post a journal entry atomically, idempotently, and concurrency-safely.
     */
    public function post(JournalEntry $entry, int $userId, bool $allowControlAccounts = false): JournalEntry
    {
        $idempotencyKey = AccountingKernel::postingIdempotencyKey(
            $entry->source_type ?? 'manual_journal',
            (string) $entry->id,
            'post'
        );

        $result = $this->idempotencyStore->run(
            operation: 'accounting.post',
            rawKey: $idempotencyKey,
            callback: function () use ($entry, $userId, $allowControlAccounts): JournalEntry {
                $postedEntry = DB::transaction(function () use ($entry, $userId, $allowControlAccounts): JournalEntry {
                    // 1. Lock FinancialPeriod row (FOR UPDATE)
                    $period = FinancialPeriod::query()
                        ->where('id', $entry->financial_period_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $period || ! $period->isOpen()) {
                        throw new InvalidArgumentException(__('Target financial period is closed or locked.'));
                    }

                    // 2. Lock Source JournalEntry row (FOR UPDATE)
                    $lockedEntry = JournalEntry::query()
                        ->where('id', $entry->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    JournalDraftService::assertDateInPeriod($period, (string) $lockedEntry->entry_date);

                    if ($lockedEntry->status === 'posted') {
                        return $lockedEntry;
                    }

                    if (! in_array($lockedEntry->status, ['draft', 'submitted', 'approved'], true)) {
                        throw new InvalidArgumentException(__('Journal entry cannot be posted in current status: :status', ['status' => $lockedEntry->status]));
                    }

                    // 3. Allocate number if missing
                    $allocatedNumber = $lockedEntry->number;
                    if (empty($allocatedNumber)) {
                        $seq = $this->sequenceAllocator->nextValue('accounting.journal');
                        $allocatedNumber = 'JV-'.date('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
                    }

                    // 4. Lock & Validate Lines and Accounts
                    $lines = $lockedEntry->lines()->with('account')->orderBy('line_no')->get();
                    if ($lines->count() < 2) {
                        throw new InvalidArgumentException(__('Journal entry must contain at least 2 lines.'));
                    }

                    $draftLines = [];
                    foreach ($lines as $line) {
                        $account = $line->account;
                        if (! $account || ! $account->is_active) {
                            throw new InvalidArgumentException(__('Account :code is inactive or missing.', ['code' => $account?->code ?? 'N/A']));
                        }

                        if ($lockedEntry->source_type === 'manual_journal' && ! $allowControlAccounts) {
                            if ($account->is_control || ! $account->allow_manual_posting) {
                                throw new InvalidArgumentException(__('Direct manual posting to control account :code is prohibited.', ['code' => $account->code]));
                            }
                        }

                        $draftLines[] = new DraftLine(
                            accountId: $account->id,
                            debitMinor: (int) $line->debit_minor,
                            creditMinor: (int) $line->credit_minor,
                            memo: $line->memo
                        );
                    }

                    // 5. Validate Accounting Kernel Invariant
                    $kernelEntry = new DraftEntry(
                        sourceType: $lockedEntry->source_type ?? 'manual_journal',
                        sourceId: (string) $lockedEntry->id,
                        date: Carbon::parse($lockedEntry->entry_date),
                        currency: $lockedEntry->currency,
                        fxRate: (int) $lockedEntry->fx_rate_e6,
                        lines: $draftLines,
                        description: $lockedEntry->description
                    );

                    AccountingKernel::assertBalanced($kernelEntry);

                    // 6. Generate Immutable Ledger Entries
                    $now = now();
                    foreach ($lines as $line) {
                        LedgerEntry::create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $lockedEntry->id,
                            'journal_line_id' => $line->id,
                            'account_id' => $line->account_id,
                            'financial_period_id' => $period->id,
                            'entry_date' => $lockedEntry->entry_date,
                            'debit_minor' => $line->debit_minor,
                            'credit_minor' => $line->credit_minor,
                            'currency' => $line->currency,
                            'fx_rate_e6' => $line->fx_rate_e6,
                            'debit_txn_minor' => $line->debit_txn_minor ?? $line->debit_minor,
                            'credit_txn_minor' => $line->credit_txn_minor ?? $line->credit_minor,
                            'posted_at' => $now,
                            'created_at' => $now,
                        ]);
                    }

                    // 7. Update JournalEntry status
                    $lockedEntry->update([
                        'number' => $allocatedNumber,
                        'status' => 'posted',
                        'posted_at' => $now,
                        'posted_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $this->auditLogger->record($userId, 'accounting.journal.posted', 'journal_entry', (string) $lockedEntry->id, after: [
                        'number' => $allocatedNumber,
                        'period_id' => $period->id,
                        'entry_date' => $lockedEntry->entry_date,
                    ]);

                    return $lockedEntry->fresh(['lines.account']);
                });

                return $postedEntry;
            }
        );

        /** @var JournalEntry */
        return $result->value;
    }
}
