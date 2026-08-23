<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\FinancialPeriod;
use App\Models\LedgerEntry;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankReconciliationService
{
    public function __construct(
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    public function createDraft(array $data, int $actorId): BankReconciliation
    {
        return DB::transaction(function () use ($data, $actorId): BankReconciliation {
            $bankAccountId = $data['bank_account_id'] ?? null;
            $financialPeriodId = $data['financial_period_id'] ?? null;
            $dateFrom = $data['date_from'] ?? null;
            $dateTo = $data['date_to'] ?? null;
            $statementOpening = (int) ($data['statement_opening_balance_minor'] ?? 0);
            $statementClosing = (int) ($data['statement_closing_balance_minor'] ?? 0);
            $statementRef = $data['statement_reference'] ?? null;

            if (! $bankAccountId || ! $financialPeriodId || ! $dateFrom || ! $dateTo) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Bank account, financial period, date from, and date to are required.'],
                ]);
            }

            if ($dateFrom > $dateTo) {
                throw ValidationException::withMessages([
                    'date_from' => ['Date from must be prior to or equal to date to.'],
                ]);
            }

            /** @var BankAccount $bankAccount */
            $bankAccount = BankAccount::query()->where('id', $bankAccountId)->lockForUpdate()->firstOrFail();
            if (! $bankAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Selected bank account is inactive.'],
                ]);
            }

            /** @var FinancialPeriod $period */
            $period = FinancialPeriod::query()->where('id', $financialPeriodId)->lockForUpdate()->firstOrFail();
            if ($period->is_closed) {
                throw ValidationException::withMessages([
                    'financial_period_id' => ['Financial period is closed.'],
                ]);
            }

            $startDate = substr((string) $period->start_date, 0, 10);
            $endDate = substr((string) $period->end_date, 0, 10);

            if ($dateFrom < $startDate || $dateTo > $endDate) {
                throw ValidationException::withMessages([
                    'date_from' => ["Reconciliation date range [{$dateFrom} - {$dateTo}] must fall within period range [{$startDate} - {$endDate}]."],
                ]);
            }

            $glAccountId = $bankAccount->gl_account_id;
            $currency = $bankAccount->currency;

            // System Opening Balance
            $systemOpening = (int) DB::table('ledger_entry')
                ->where('account_id', $glAccountId)
                ->where('currency', $currency)
                ->where('entry_date', '<', $dateFrom)
                ->sum(DB::raw('debit_minor - credit_minor'));

            // System Movement
            $systemMovement = (int) DB::table('ledger_entry')
                ->where('account_id', $glAccountId)
                ->where('currency', $currency)
                ->whereBetween('entry_date', [$dateFrom, $dateTo])
                ->sum(DB::raw('debit_minor - credit_minor'));

            $systemClosing = $systemOpening + $systemMovement;
            $statementMovement = $statementClosing - $statementOpening;
            $matchedSystemMovement = 0;
            $difference = $statementMovement - $matchedSystemMovement;

            $reconciliation = BankReconciliation::query()->create([
                'bank_account_id' => $bankAccount->id,
                'financial_period_id' => $period->id,
                'statement_reference' => $statementRef,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
                'statement_opening_balance_minor' => $statementOpening,
                'statement_closing_balance_minor' => $statementClosing,
                'system_opening_balance_minor' => $systemOpening,
                'system_movement_minor' => $systemMovement,
                'system_closing_balance_minor' => $systemClosing,
                'statement_movement_minor' => $statementMovement,
                'matched_system_movement_minor' => $matchedSystemMovement,
                'difference_minor' => $difference,
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'create',
                entityType: 'bank_reconciliation',
                entityId: $reconciliation->id,
                before: null,
                after: $reconciliation->toArray(),
            );

            return $reconciliation->fresh();
        });
    }

    public function addLine(string $reconciliationId, array $data, int $actorId): BankReconciliationLine
    {
        return DB::transaction(function () use ($reconciliationId, $data, $actorId): BankReconciliationLine {
            /** @var BankReconciliation $recon */
            $recon = BankReconciliation::query()->where('id', $reconciliationId)->lockForUpdate()->firstOrFail();

            if ($recon->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'status' => ['Cannot modify lines on a reconciled bank reconciliation.'],
                ]);
            }

            $statementDate = $data['statement_date'] ?? null;
            $debit = (int) ($data['debit_minor'] ?? 0);
            $credit = (int) ($data['credit_minor'] ?? 0);
            $reference = $data['reference'] ?? null;
            $description = $data['description'] ?? null;

            $dateFrom = (string) $recon->date_from;
            $dateTo = (string) $recon->date_to;

            if (! $statementDate || $statementDate < $dateFrom || $statementDate > $dateTo) {
                throw ValidationException::withMessages([
                    'statement_date' => ["Statement line date [{$statementDate}] must be within reconciliation date range [{$dateFrom} - {$dateTo}]."],
                ]);
            }

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw ValidationException::withMessages([
                    'debit_minor' => ['Exactly one of debit_minor or credit_minor must be greater than zero.'],
                ]);
            }

            $maxLineNo = DB::table('bank_reconciliation_line')
                ->where('bank_reconciliation_id', $reconciliationId)
                ->max('line_no') ?? 0;
            $lineNo = $maxLineNo + 1;

            $line = BankReconciliationLine::query()->create([
                'bank_reconciliation_id' => $recon->id,
                'line_no' => $lineNo,
                'statement_date' => $statementDate,
                'reference' => $reference,
                'description' => $description,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'status' => 'unmatched',
            ]);

            $this->recomputeSummaryAndSave($recon);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'add_line',
                entityType: 'bank_reconciliation_line',
                entityId: $line->id,
                before: null,
                after: $line->toArray(),
            );

            return $line->fresh();
        });
    }

    public function updateLine(string $lineId, array $data, int $actorId): BankReconciliationLine
    {
        return DB::transaction(function () use ($lineId, $data, $actorId): BankReconciliationLine {
            /** @var BankReconciliationLine $initialLine */
            $initialLine = BankReconciliationLine::query()
                ->where('id', $lineId)
                ->firstOrFail();

            /** @var BankReconciliation $recon */
            $recon = BankReconciliation::query()
                ->where('id', $initialLine->bank_reconciliation_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var BankReconciliationLine $line */
            $line = BankReconciliationLine::query()
                ->where('id', $lineId)
                ->where('bank_reconciliation_id', $recon->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recon->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'status' => ['Cannot modify lines on a reconciled bank reconciliation.'],
                ]);
            }

            if ($line->status === 'matched') {
                throw ValidationException::withMessages([
                    'status' => ['Unmatch statement line before modifying line details.'],
                ]);
            }

            $statementDate = $data['statement_date'] ?? (string) $line->statement_date;
            $debit = isset($data['debit_minor']) ? (int) $data['debit_minor'] : (int) $line->debit_minor;
            $credit = isset($data['credit_minor']) ? (int) $data['credit_minor'] : (int) $line->credit_minor;
            $reference = array_key_exists('reference', $data) ? $data['reference'] : $line->reference;
            $description = array_key_exists('description', $data) ? $data['description'] : $line->description;

            $dateFrom = (string) $recon->date_from;
            $dateTo = (string) $recon->date_to;

            if ($statementDate < $dateFrom || $statementDate > $dateTo) {
                throw ValidationException::withMessages([
                    'statement_date' => ["Statement line date [{$statementDate}] must be within reconciliation date range [{$dateFrom} - {$dateTo}]."],
                ]);
            }

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw ValidationException::withMessages([
                    'debit_minor' => ['Exactly one of debit_minor or credit_minor must be greater than zero.'],
                ]);
            }

            $before = $line->toArray();
            $line->update([
                'statement_date' => $statementDate,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'reference' => $reference,
                'description' => $description,
            ]);

            $this->recomputeSummaryAndSave($recon);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'update_line',
                entityType: 'bank_reconciliation_line',
                entityId: $line->id,
                before: $before,
                after: $line->fresh()->toArray(),
            );

            return $line->fresh();
        });
    }

    public function deleteLine(string $lineId, int $actorId): void
    {
        DB::transaction(function () use ($lineId, $actorId): void {
            /** @var BankReconciliationLine $initialLine */
            $initialLine = BankReconciliationLine::query()
                ->where('id', $lineId)
                ->firstOrFail();

            /** @var BankReconciliation $recon */
            $recon = BankReconciliation::query()
                ->where('id', $initialLine->bank_reconciliation_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var BankReconciliationLine $line */
            $line = BankReconciliationLine::query()
                ->where('id', $lineId)
                ->where('bank_reconciliation_id', $recon->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recon->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'status' => ['Cannot delete lines from a reconciled bank reconciliation.'],
                ]);
            }

            if ($line->status === 'matched') {
                throw ValidationException::withMessages([
                    'status' => ['Unmatch statement line before deleting.'],
                ]);
            }

            $before = $line->toArray();
            $line->delete();

            $this->recomputeSummaryAndSave($recon);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'delete_line',
                entityType: 'bank_reconciliation_line',
                entityId: $lineId,
                before: $before,
                after: null,
            );
        });
    }

    public function candidateLedgerEntries(string $reconciliationId): array
    {
        /** @var BankReconciliation $recon */
        $recon = BankReconciliation::query()->with('bankAccount')->where('id', $reconciliationId)->firstOrFail();
        $bankAccount = $recon->bankAccount;

        if (! $bankAccount || ! $bankAccount->gl_account_id) {
            return [];
        }

        $matchedLedgerEntryIds = BankReconciliationLine::query()
            ->whereNotNull('matched_ledger_entry_id')
            ->pluck('matched_ledger_entry_id')
            ->all();

        $entries = LedgerEntry::query()
            ->with(['journalEntry', 'journalLine'])
            ->where('account_id', $bankAccount->gl_account_id)
            ->where('currency', $recon->currency)
            ->whereBetween('entry_date', [(string) $recon->date_from, (string) $recon->date_to])
            ->whereNotIn('id', $matchedLedgerEntryIds)
            ->orderBy('entry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return $entries->map(function (LedgerEntry $entry) {
            $debit = (int) $entry->debit_minor;
            $credit = (int) $entry->credit_minor;

            return [
                'ledger_entry_id' => $entry->id,
                'journal_entry_id' => $entry->journal_entry_id,
                'journal_number' => $entry->journalEntry?->number,
                'source_type' => $entry->journalEntry?->source_type,
                'source_id' => $entry->journalEntry?->source_id,
                'entry_date' => (string) $entry->entry_date,
                'reference' => $entry->journalEntry?->reference,
                'description' => $entry->journalLine?->memo ?: $entry->journalEntry?->description,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'signed_movement_minor' => $debit - $credit,
            ];
        })->all();
    }

    public function matchLine(
        string $lineId,
        string $ledgerEntryId,
        int $actorId,
        ?string $idempotencyKey = null,
    ): BankReconciliationLine {
        $idempotencyKey ??= "bank_recon_line:{$lineId}:match:{$ledgerEntryId}";

        $result = $this->idempotencyStore->run(
            operation: 'bank_reconciliation.match_line',
            rawKey: $idempotencyKey,
            callback: function () use ($lineId, $ledgerEntryId, $actorId): BankReconciliationLine {
                return DB::transaction(function () use ($lineId, $ledgerEntryId, $actorId): BankReconciliationLine {
                    /** @var BankReconciliationLine $initialLine */
                    $initialLine = BankReconciliationLine::query()
                        ->where('id', $lineId)
                        ->firstOrFail();

                    /** @var BankReconciliation $recon */
                    $recon = BankReconciliation::query()
                        ->where('id', $initialLine->bank_reconciliation_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    /** @var BankReconciliationLine $line */
                    $line = BankReconciliationLine::query()
                        ->where('id', $lineId)
                        ->where('bank_reconciliation_id', $recon->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($recon->status === 'reconciled') {
                        throw ValidationException::withMessages([
                            'status' => ['Cannot match line on a reconciled bank reconciliation.'],
                        ]);
                    }

                    // Idempotent check: if already matched to target ledger entry, return
                    if ($line->status === 'matched' && $line->matched_ledger_entry_id === $ledgerEntryId) {
                        return $line;
                    }

                    if ($line->status === 'matched') {
                        throw ValidationException::withMessages([
                            'line_id' => ['Statement line is already matched to another ledger entry. Unmatch first.'],
                        ]);
                    }

                    /** @var LedgerEntry $ledgerEntry */
                    $ledgerEntry = LedgerEntry::query()->where('id', $ledgerEntryId)->lockForUpdate()->firstOrFail();

                    /** @var BankAccount $bankAccount */
                    $bankAccount = BankAccount::query()->where('id', $recon->bank_account_id)->firstOrFail();

                    // Check if ledger entry is already matched globally
                    $alreadyMatched = BankReconciliationLine::query()
                        ->where('matched_ledger_entry_id', $ledgerEntryId)
                        ->where('status', 'matched')
                        ->exists();

                    if ($alreadyMatched) {
                        throw ValidationException::withMessages([
                            'ledger_entry_id' => ['Ledger entry is already matched to another statement line.'],
                        ]);
                    }

                    // GL Account check
                    if ($ledgerEntry->account_id !== $bankAccount->gl_account_id) {
                        throw ValidationException::withMessages([
                            'ledger_entry_id' => ['Ledger entry GL account does not match bank account GL account.'],
                        ]);
                    }

                    // Currency check
                    if ($ledgerEntry->currency !== $recon->currency) {
                        throw ValidationException::withMessages([
                            'ledger_entry_id' => ["Ledger entry currency [{$ledgerEntry->currency}] does not match reconciliation currency [{$recon->currency}]."],
                        ]);
                    }

                    $ledgerDate = substr((string) $ledgerEntry->entry_date, 0, 10);
                    $dateFrom = substr((string) $recon->date_from, 0, 10);
                    $dateTo = substr((string) $recon->date_to, 0, 10);

                    if ($ledgerDate < $dateFrom || $ledgerDate > $dateTo) {
                        throw ValidationException::withMessages([
                            'ledger_entry_id' => ["Ledger entry date [{$ledgerDate}] must be within reconciliation date range [{$dateFrom} - {$dateTo}]."],
                        ]);
                    }

                    // Signed Amount check
                    $lineSigned = (int) $line->debit_minor - (int) $line->credit_minor;
                    $ledgerSigned = (int) $ledgerEntry->debit_minor - (int) $ledgerEntry->credit_minor;

                    if ($lineSigned !== $ledgerSigned) {
                        throw ValidationException::withMessages([
                            'ledger_entry_id' => ["Statement line signed movement [{$lineSigned}] does not match ledger entry signed movement [{$ledgerSigned}]."],
                        ]);
                    }

                    $before = $line->toArray();
                    $line->update([
                        'matched_ledger_entry_id' => $ledgerEntry->id,
                        'matched_at' => now(),
                        'matched_by' => $actorId,
                        'status' => 'matched',
                    ]);

                    if ($recon->status === 'draft') {
                        $recon->update(['status' => 'in_progress']);
                    }

                    $this->recomputeSummaryAndSave($recon);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'match_line',
                        entityType: 'bank_reconciliation_line',
                        entityId: $line->id,
                        before: $before,
                        after: $line->fresh()->toArray(),
                    );

                    return $line->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return BankReconciliationLine::query()->findOrFail($lineId);
        }

        /** @var BankReconciliationLine */
        return $result->value;
    }

    public function unmatchLine(
        string $lineId,
        int $actorId,
        ?string $idempotencyKey = null,
    ): BankReconciliationLine {
        $idempotencyKey ??= "bank_recon_line:{$lineId}:unmatch";

        $result = $this->idempotencyStore->run(
            operation: 'bank_reconciliation.unmatch_line',
            rawKey: $idempotencyKey,
            callback: function () use ($lineId, $actorId): BankReconciliationLine {
                return DB::transaction(function () use ($lineId, $actorId): BankReconciliationLine {
                    /** @var BankReconciliationLine $initialLine */
                    $initialLine = BankReconciliationLine::query()
                        ->where('id', $lineId)
                        ->firstOrFail();

                    /** @var BankReconciliation $recon */
                    $recon = BankReconciliation::query()
                        ->where('id', $initialLine->bank_reconciliation_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    /** @var BankReconciliationLine $line */
                    $line = BankReconciliationLine::query()
                        ->where('id', $lineId)
                        ->where('bank_reconciliation_id', $recon->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($recon->status === 'reconciled') {
                        throw ValidationException::withMessages([
                            'status' => ['Cannot unmatch line on a reconciled bank reconciliation.'],
                        ]);
                    }

                    if ($line->status === 'unmatched') {
                        return $line;
                    }

                    $before = $line->toArray();
                    $line->update([
                        'matched_ledger_entry_id' => null,
                        'matched_at' => null,
                        'matched_by' => null,
                        'status' => 'unmatched',
                    ]);

                    $this->recomputeSummaryAndSave($recon);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'unmatch_line',
                        entityType: 'bank_reconciliation_line',
                        entityId: $line->id,
                        before: $before,
                        after: $line->fresh()->toArray(),
                    );

                    return $line->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return BankReconciliationLine::query()->findOrFail($lineId);
        }

        /** @var BankReconciliationLine */
        return $result->value;
    }

    public function summary(string $reconciliationId): array
    {
        /** @var BankReconciliation $recon */
        $recon = BankReconciliation::query()->where('id', $reconciliationId)->firstOrFail();

        return $this->computeSummaryArray($recon);
    }

    public function finalize(
        string $reconciliationId,
        int $actorId,
        ?string $idempotencyKey = null,
    ): BankReconciliation {
        $idempotencyKey ??= "bank_reconciliation:{$reconciliationId}:finalize";

        $result = $this->idempotencyStore->run(
            operation: 'bank_reconciliation.finalize',
            rawKey: $idempotencyKey,
            callback: function () use ($reconciliationId, $actorId): BankReconciliation {
                return DB::transaction(function () use ($reconciliationId, $actorId): BankReconciliation {
                    /** @var BankReconciliation $recon */
                    $recon = BankReconciliation::query()->where('id', $reconciliationId)->lockForUpdate()->firstOrFail();

                    $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $recon->financial_period_id, (string) $recon->date_to);

                    if ($recon->status === 'reconciled') {
                        return $recon;
                    }

                    // Lock all lines in deterministic line_no order
                    $lines = BankReconciliationLine::query()
                        ->where('bank_reconciliation_id', $recon->id)
                        ->orderBy('line_no', 'asc')
                        ->lockForUpdate()
                        ->get();

                    // Lock matched ledger entries in deterministic ID order
                    $matchedLedgerEntryIds = $lines->pluck('matched_ledger_entry_id')->filter()->sort()->all();
                    if (! empty($matchedLedgerEntryIds)) {
                        LedgerEntry::query()
                            ->whereIn('id', $matchedLedgerEntryIds)
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();
                    }

                    // 1. Statement self-check: statement_opening + statement_movement = statement_closing
                    $statementOpening = (int) $recon->statement_opening_balance_minor;
                    $statementClosing = (int) $recon->statement_closing_balance_minor;
                    $statementMovement = $statementClosing - $statementOpening;

                    if (($statementOpening + $statementMovement) !== $statementClosing) {
                        throw ValidationException::withMessages([
                            'statement_closing_balance_minor' => ['Statement self-check failed: statement opening + movement != closing balance.'],
                        ]);
                    }

                    // 2. Unmatched statement lines check
                    $unmatchedLineCount = $lines->where('status', '!=', 'matched')->count();
                    if ($unmatchedLineCount > 0) {
                        throw ValidationException::withMessages([
                            'lines' => ["Reconciliation contains [{$unmatchedLineCount}] unmatched statement line(s). All statement lines must be matched before finalization."],
                        ]);
                    }

                    // 3. Unmatched system bank ledger entries check
                    /** @var BankAccount $bankAccount */
                    $bankAccount = BankAccount::query()->where('id', $recon->bank_account_id)->firstOrFail();
                    $dateFrom = (string) $recon->date_from;
                    $dateTo = (string) $recon->date_to;

                    $systemLedgerEntries = LedgerEntry::query()
                        ->where('account_id', $bankAccount->gl_account_id)
                        ->where('currency', $recon->currency)
                        ->whereBetween('entry_date', [$dateFrom, $dateTo])
                        ->pluck('id')
                        ->all();

                    $reconMatchedLedgerIds = $lines->pluck('matched_ledger_entry_id')->all();
                    $unmatchedSystemEntries = array_diff($systemLedgerEntries, $reconMatchedLedgerIds);

                    if (! empty($unmatchedSystemEntries)) {
                        $unmatchedCount = count($unmatchedSystemEntries);
                        throw ValidationException::withMessages([
                            'system_entries' => ["Date range [{$dateFrom} - {$dateTo}] contains [{$unmatchedCount}] unmatched bank ledger entry(ies). All bank ledger entries in the reconciliation period must be matched or accounted for before finalization."],
                        ]);
                    }

                    // 4. Difference check
                    $summary = $this->computeSummaryArray($recon);
                    if ($summary['difference_minor'] !== 0) {
                        throw ValidationException::withMessages([
                            'difference_minor' => ["Reconciliation difference is [{$summary['difference_minor']}]. Difference must be zero to finalize."],
                        ]);
                    }

                    $before = $recon->toArray();
                    $recon->update([
                        'system_opening_balance_minor' => $summary['system_opening_balance_minor'],
                        'system_movement_minor' => $summary['system_movement_minor'],
                        'system_closing_balance_minor' => $summary['system_closing_balance_minor'],
                        'statement_movement_minor' => $summary['statement_movement_minor'],
                        'matched_system_movement_minor' => $summary['matched_system_movement_minor'],
                        'difference_minor' => 0,
                        'status' => 'reconciled',
                        'reconciled_at' => now(),
                        'reconciled_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'finalize',
                        entityType: 'bank_reconciliation',
                        entityId: $recon->id,
                        before: $before,
                        after: $recon->fresh()->toArray(),
                    );

                    return $recon->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return BankReconciliation::query()->findOrFail($reconciliationId);
        }

        /** @var BankReconciliation */
        return $result->value;
    }

    private function computeSummaryArray(BankReconciliation $recon): array
    {
        /** @var BankAccount $bankAccount */
        $bankAccount = BankAccount::query()->where('id', $recon->bank_account_id)->first();
        $glAccountId = $bankAccount?->gl_account_id;
        $dateFrom = (string) $recon->date_from;
        $dateTo = (string) $recon->date_to;

        $statementOpening = (int) $recon->statement_opening_balance_minor;
        $statementClosing = (int) $recon->statement_closing_balance_minor;
        $statementMovement = $statementClosing - $statementOpening;

        $systemOpening = $glAccountId ? (int) DB::table('ledger_entry')
            ->where('account_id', $glAccountId)
            ->where('currency', $recon->currency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('debit_minor - credit_minor')) : 0;

        $systemMovement = $glAccountId ? (int) DB::table('ledger_entry')
            ->where('account_id', $glAccountId)
            ->where('currency', $recon->currency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->sum(DB::raw('debit_minor - credit_minor')) : 0;

        $systemClosing = $systemOpening + $systemMovement;

        // Sum of matched system ledger movements for this reconciliation's matched lines
        $matchedLines = BankReconciliationLine::query()
            ->where('bank_reconciliation_id', $recon->id)
            ->where('status', 'matched')
            ->get();

        $matchedSystemMovement = 0;
        foreach ($matchedLines as $line) {
            $matchedSystemMovement += ((int) $line->debit_minor - (int) $line->credit_minor);
        }

        $difference = $statementMovement - $matchedSystemMovement;

        return [
            'statement_opening_balance_minor' => $statementOpening,
            'statement_closing_balance_minor' => $statementClosing,
            'statement_movement_minor' => $statementMovement,
            'system_opening_balance_minor' => $systemOpening,
            'system_movement_minor' => $systemMovement,
            'system_closing_balance_minor' => $systemClosing,
            'matched_system_movement_minor' => $matchedSystemMovement,
            'difference_minor' => $difference,
        ];
    }

    private function recomputeSummaryAndSave(BankReconciliation $recon): void
    {
        $summary = $this->computeSummaryArray($recon);
        $recon->update([
            'system_opening_balance_minor' => $summary['system_opening_balance_minor'],
            'system_movement_minor' => $summary['system_movement_minor'],
            'system_closing_balance_minor' => $summary['system_closing_balance_minor'],
            'statement_movement_minor' => $summary['statement_movement_minor'],
            'matched_system_movement_minor' => $summary['matched_system_movement_minor'],
            'difference_minor' => $summary['difference_minor'],
        ]);
    }
}
