<?php

namespace App\Application\Accounting;

use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\OutgoingCheque;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OutgoingChequeService
{
    public function __construct(
        private readonly PostingEngine $postingEngine,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createDraft(array $data, int $actorId): OutgoingCheque
    {
        $supplierId = $data['supplier_id'] ?? null;
        $bankAccountId = $data['bank_account_id'] ?? null;
        $chequeNumber = trim((string) ($data['cheque_number'] ?? ''));
        $dueDate = $data['due_date'] ?? null;
        $currency = CurrencyInput::required($data['currency'] ?? null);
        $amountMinor = $data['amount_minor'] ?? 0;

        Supplier::query()->findOrFail($supplierId);
        $bankAccount = BankAccount::query()->findOrFail($bankAccountId);

        if (! $bankAccount->is_active) {
            throw ValidationException::withMessages([
                'bank_account_id' => [__('Selected bank account is inactive.')],
            ]);
        }

        if ($bankAccount->currency !== $currency) {
            throw ValidationException::withMessages([
                'currency' => [__('Bank account currency [:bank_currency] does not match cheque currency [:cheque_currency].', [
                    'bank_currency' => $bankAccount->currency,
                    'cheque_currency' => $currency,
                ])],
            ]);
        }

        if ($chequeNumber === '') {
            throw ValidationException::withMessages([
                'cheque_number' => [__('Physical cheque number is required.')],
            ]);
        }

        if (! is_string($dueDate) || trim($dueDate) === '') {
            throw ValidationException::withMessages([
                'due_date' => [__('Cheque due date is required.')],
            ]);
        }

        if (! is_int($amountMinor) || $amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => [__('Amount must be a positive integer.')],
            ]);
        }

        /** @var OutgoingCheque $cheque */
        $cheque = OutgoingCheque::query()->create([
            'supplier_id' => $supplierId,
            'bank_account_id' => $bankAccountId,
            'cheque_number' => $chequeNumber,
            'payee_name' => $data['payee_name'] ?? null,
            'due_date' => $dueDate,
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'fx_rate_e6' => $data['fx_rate_e6'] ?? 1000000,
            'status' => 'draft',
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => $actorId,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'outgoing_cheque',
            entityId: $cheque->id,
            before: null,
            after: $cheque->toArray(),
        );

        return $cheque;
    }

    public function issue(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $issuedDate,
        int $actorId,
        ?string $idempotencyKey = null,
    ): OutgoingCheque {
        $idempotencyKey ??= "outgoing_cheque:{$chequeId}:issue";

        $result = $this->idempotencyStore->run(
            operation: 'outgoing_cheque.issue',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $issuedDate, $actorId): OutgoingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $issuedDate, $actorId): OutgoingCheque {
                    /** @var OutgoingCheque $cheque */
                    $cheque = OutgoingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'issued') {
                        return $cheque;
                    }

                    if ($cheque->status !== 'draft') {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot issue cheque from status [:status]. Only draft cheques can be issued.', ['status' => $cheque->status])],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $issuedDate);

                    $apControlAcc = $this->resolveMappedAccount('ap_control', 'liability', 'credit', $cheque->currency);
                    $chequesPayableAcc = $this->resolveMappedAccount('cheques_payable', 'liability', 'credit', $cheque->currency);

                    $number = $cheque->number;
                    if (! $number) {
                        $seq = $this->numberSequenceAllocator->nextValue('outgoing_cheque');
                        $number = sprintf('OCHQ-%s-%05d', substr($issuedDate, 0, 4), $seq);
                    }

                    // Journal Entry: Dr AP Control, Cr Cheques Payable
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $issuedDate,
                        'source_type' => 'outgoing_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Supplier Cheque Issued [{$cheque->cheque_number}]",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $apControlAcc->id,
                        'memo' => "Dr AP Control [{$cheque->cheque_number}]",
                        'debit_minor' => $cheque->amount_minor,
                        'credit_minor' => 0,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => $cheque->amount_minor,
                        'credit_txn_minor' => 0,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 2,
                        'account_id' => $chequesPayableAcc->id,
                        'memo' => "Cr Cheques Payable [{$cheque->cheque_number}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId, true);

                    // Create PayableEntry Debit
                    $payableEntry = PayableEntry::query()->create([
                        'supplier_id' => $cheque->supplier_id,
                        'source_type' => 'outgoing_cheque',
                        'source_id' => $cheque->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $journal->lines()->where('line_no', 1)->value('id'),
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $issuedDate,
                        'description' => "Supplier Cheque Issued [{$cheque->cheque_number}]",
                        'currency' => $cheque->currency,
                        'debit_minor' => $cheque->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $cheque->amount_minor,
                        'credit_txn_minor' => 0,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    $before = $cheque->toArray();
                    $cheque->update([
                        'number' => $number,
                        'status' => 'issued',
                        'issued_fiscal_year_id' => $fiscalYearId,
                        'issued_financial_period_id' => $financialPeriodId,
                        'issued_date' => $issuedDate,
                        'issue_journal_entry_id' => $postedJournal->id,
                        'payable_entry_id' => $payableEntry->id,
                        'issued_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'issue',
                        entityType: 'outgoing_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return OutgoingCheque::query()->findOrFail($chequeId);
        }

        /** @var OutgoingCheque */
        return $result->value;
    }

    public function clear(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $clearedDate,
        int $actorId,
        ?string $idempotencyKey = null,
    ): OutgoingCheque {
        $idempotencyKey ??= "outgoing_cheque:{$chequeId}:clear";

        $result = $this->idempotencyStore->run(
            operation: 'outgoing_cheque.clear',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $clearedDate, $actorId): OutgoingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $clearedDate, $actorId): OutgoingCheque {
                    /** @var OutgoingCheque $cheque */
                    $cheque = OutgoingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'cleared') {
                        return $cheque;
                    }

                    if ($cheque->status !== 'issued') {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot clear cheque from status [:status]. Only issued cheques can be cleared.', ['status' => $cheque->status])],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $clearedDate);

                    $bankAccount = BankAccount::query()->where('id', $cheque->bank_account_id)->lockForUpdate()->firstOrFail();
                    if (! $bankAccount->is_active) {
                        throw ValidationException::withMessages([
                            'bank_account_id' => [__('Bank account is inactive.')],
                        ]);
                    }

                    $chequesPayableAcc = $this->resolveMappedAccount('cheques_payable', 'liability', 'credit', $cheque->currency);

                    /** @var Account $bankGlAcc */
                    $bankGlAcc = Account::query()->where('id', $bankAccount->gl_account_id)->lockForUpdate()->firstOrFail();
                    if (! $bankGlAcc->is_active || $bankGlAcc->currency !== $cheque->currency) {
                        throw ValidationException::withMessages([
                            'bank_account_id' => [__('Bank account GL account is inactive or currency mismatch.')],
                        ]);
                    }

                    // Journal Entry: Dr Cheques Payable, Cr Bank GL Account
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $clearedDate,
                        'source_type' => 'outgoing_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Supplier Cheque Cleared [{$cheque->cheque_number}]",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $chequesPayableAcc->id,
                        'memo' => "Dr Cheques Payable [{$cheque->cheque_number}]",
                        'debit_minor' => $cheque->amount_minor,
                        'credit_minor' => 0,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => $cheque->amount_minor,
                        'credit_txn_minor' => 0,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 2,
                        'account_id' => $bankGlAcc->id,
                        'memo' => "Cr Bank GL Account [{$bankGlAcc->code}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId);

                    $before = $cheque->toArray();
                    $cheque->update([
                        'status' => 'cleared',
                        'cleared_fiscal_year_id' => $fiscalYearId,
                        'cleared_financial_period_id' => $financialPeriodId,
                        'cleared_date' => $clearedDate,
                        'clear_journal_entry_id' => $postedJournal->id,
                        'cleared_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'clear',
                        entityType: 'outgoing_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return OutgoingCheque::query()->findOrFail($chequeId);
        }

        /** @var OutgoingCheque */
        return $result->value;
    }

    public function returnBeforeClear(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $returnedDate,
        string $reason,
        int $actorId,
        ?string $idempotencyKey = null,
    ): OutgoingCheque {
        $idempotencyKey ??= "outgoing_cheque:{$chequeId}:return";

        $result = $this->idempotencyStore->run(
            operation: 'outgoing_cheque.return',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $returnedDate, $reason, $actorId): OutgoingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $returnedDate, $reason, $actorId): OutgoingCheque {
                    /** @var OutgoingCheque $cheque */
                    $cheque = OutgoingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'returned') {
                        return $cheque;
                    }

                    if ($cheque->status === 'cleared') {
                        throw new InvalidArgumentException(__('OWNER DECISION REQUIRED: Post-clear return workflow is not implemented in pre-clear cheque lifecycle.'));
                    }

                    if ($cheque->status !== 'issued') {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot return cheque from status [:status]. Only issued pre-clear cheques can be returned.', ['status' => $cheque->status])],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $returnedDate);

                    $chequesPayableAcc = $this->resolveMappedAccount('cheques_payable', 'liability', 'credit', $cheque->currency);
                    $apControlAcc = $this->resolveMappedAccount('ap_control', 'liability', 'credit', $cheque->currency);

                    // Journal Entry: Dr Cheques Payable, Cr AP Control
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $returnedDate,
                        'source_type' => 'outgoing_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Supplier Cheque Returned [{$cheque->cheque_number}]: {$reason}",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $chequesPayableAcc->id,
                        'memo' => "Dr Cheques Payable [{$cheque->cheque_number}]",
                        'debit_minor' => $cheque->amount_minor,
                        'credit_minor' => 0,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => $cheque->amount_minor,
                        'credit_txn_minor' => 0,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 2,
                        'account_id' => $apControlAcc->id,
                        'memo' => "Cr AP Control [{$cheque->cheque_number}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId, true);

                    // Create PayableEntry Credit (restores supplier AP balance)
                    $returnPayableEntry = PayableEntry::query()->create([
                        'supplier_id' => $cheque->supplier_id,
                        'source_type' => 'outgoing_cheque_return',
                        'source_id' => $cheque->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $journal->lines()->where('line_no', 2)->value('id'),
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $returnedDate,
                        'description' => "Supplier Cheque Returned [{$cheque->cheque_number}]",
                        'currency' => $cheque->currency,
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    $before = $cheque->toArray();
                    $cheque->update([
                        'status' => 'returned',
                        'returned_fiscal_year_id' => $fiscalYearId,
                        'returned_financial_period_id' => $financialPeriodId,
                        'returned_date' => $returnedDate,
                        'return_journal_entry_id' => $postedJournal->id,
                        'return_payable_entry_id' => $returnPayableEntry->id,
                        'returned_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'return',
                        entityType: 'outgoing_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return OutgoingCheque::query()->findOrFail($chequeId);
        }

        /** @var OutgoingCheque */
        return $result->value;
    }

    public function cancelBeforeClear(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $cancelledDate,
        string $reason,
        int $actorId,
        ?string $idempotencyKey = null,
    ): OutgoingCheque {
        $idempotencyKey ??= "outgoing_cheque:{$chequeId}:cancel";

        $result = $this->idempotencyStore->run(
            operation: 'outgoing_cheque.cancel',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $cancelledDate, $reason, $actorId): OutgoingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $cancelledDate, $reason, $actorId): OutgoingCheque {
                    /** @var OutgoingCheque $cheque */
                    $cheque = OutgoingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'cancelled') {
                        return $cheque;
                    }

                    if ($cheque->status === 'cleared') {
                        throw new InvalidArgumentException(__('OWNER DECISION REQUIRED: Post-clear cancel workflow is not implemented in pre-clear cheque lifecycle.'));
                    }

                    if ($cheque->status !== 'issued') {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot cancel cheque from status [:status]. Only issued pre-clear cheques can be cancelled.', ['status' => $cheque->status])],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $cancelledDate);

                    $chequesPayableAcc = $this->resolveMappedAccount('cheques_payable', 'liability', 'credit', $cheque->currency);
                    $apControlAcc = $this->resolveMappedAccount('ap_control', 'liability', 'credit', $cheque->currency);

                    // Journal Entry: Dr Cheques Payable, Cr AP Control
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $cancelledDate,
                        'source_type' => 'outgoing_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Supplier Cheque Cancelled [{$cheque->cheque_number}]: {$reason}",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $chequesPayableAcc->id,
                        'memo' => "Dr Cheques Payable [{$cheque->cheque_number}]",
                        'debit_minor' => $cheque->amount_minor,
                        'credit_minor' => 0,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => $cheque->amount_minor,
                        'credit_txn_minor' => 0,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 2,
                        'account_id' => $apControlAcc->id,
                        'memo' => "Cr AP Control [{$cheque->cheque_number}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId, true);

                    // Create PayableEntry Credit (restores supplier AP balance)
                    $cancelPayableEntry = PayableEntry::query()->create([
                        'supplier_id' => $cheque->supplier_id,
                        'source_type' => 'outgoing_cheque_cancel',
                        'source_id' => $cheque->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $journal->lines()->where('line_no', 2)->value('id'),
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $cancelledDate,
                        'description' => "Supplier Cheque Cancelled [{$cheque->cheque_number}]",
                        'currency' => $cheque->currency,
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    $before = $cheque->toArray();
                    $cheque->update([
                        'status' => 'cancelled',
                        'cancelled_fiscal_year_id' => $fiscalYearId,
                        'cancelled_financial_period_id' => $financialPeriodId,
                        'cancelled_date' => $cancelledDate,
                        'cancel_journal_entry_id' => $postedJournal->id,
                        'cancel_payable_entry_id' => $cancelPayableEntry->id,
                        'cancelled_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'cancel',
                        entityType: 'outgoing_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return OutgoingCheque::query()->findOrFail($chequeId);
        }

        /** @var OutgoingCheque */
        return $result->value;
    }

    public function cancelDraft(string $chequeId, string $reason, int $actorId): OutgoingCheque
    {
        return DB::transaction(function () use ($chequeId, $actorId): OutgoingCheque {
            /** @var OutgoingCheque $cheque */
            $cheque = OutgoingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

            if ($cheque->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('Cannot cancel cheque from status [:status]. Only draft cheques can be cancelled.', ['status' => $cheque->status])],
                ]);
            }

            $before = $cheque->toArray();
            $cheque->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'cancel',
                entityType: 'outgoing_cheque',
                entityId: $cheque->id,
                before: $before,
                after: $cheque->fresh()->toArray(),
            );

            return $cheque->fresh();
        });
    }

    private function validateAndLockPeriod(string $fiscalYearId, string $financialPeriodId, string $eventDate): FinancialPeriod
    {
        FiscalYear::query()->where('id', $fiscalYearId)->firstOrFail();

        /** @var FinancialPeriod $period */
        $period = FinancialPeriod::query()
            ->where('id', $financialPeriodId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'financial_period_id' => [__('Financial period is not open. Current status: [:status].', ['status' => $period->status])],
            ]);
        }

        $startDate = substr((string) $period->start_date, 0, 10);
        $endDate = substr((string) $period->end_date, 0, 10);

        if ($eventDate < $startDate || $eventDate > $endDate) {
            throw ValidationException::withMessages([
                'issued_date' => [__('Event date [:date] is outside period range [:start - :end].', [
                    'date' => $eventDate,
                    'start' => $startDate,
                    'end' => $endDate,
                ])],
            ]);
        }

        return $period;
    }

    private function resolveMappedAccount(string $mappingKey, string $expectedType, string $expectedNature, string $currency): Account
    {
        $accountId = $this->mappingService->getAccountId($mappingKey);
        if (! $accountId) {
            throw ValidationException::withMessages([
                'mapping' => [__('Accounting mapping key [:key] is not configured.', ['key' => $mappingKey])],
            ]);
        }

        /** @var Account $account */
        $account = Account::query()->where('id', $accountId)->lockForUpdate()->firstOrFail();

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'mapping' => [__('Mapped account [:account] for [:key] is inactive.', [
                    'account' => $account->code,
                    'key' => $mappingKey,
                ])],
            ]);
        }

        if ($account->currency !== $currency) {
            throw ValidationException::withMessages([
                'currency' => [__('Mapped account [:account] currency [:account_currency] does not match cheque currency [:cheque_currency].', [
                    'account' => $account->code,
                    'account_currency' => $account->currency,
                    'cheque_currency' => $currency,
                ])],
            ]);
        }

        if ($account->type !== $expectedType) {
            throw ValidationException::withMessages([
                'mapping' => [__('Mapped account [:account] type [:account_type] does not match expected [:expected_type].', [
                    'account' => $account->code,
                    'account_type' => $account->type,
                    'expected_type' => $expectedType,
                ])],
            ]);
        }

        if ($account->nature !== $expectedNature) {
            throw ValidationException::withMessages([
                'mapping' => [__('Mapped account [:account] nature [:account_nature] does not match expected [:expected_nature].', [
                    'account' => $account->code,
                    'account_nature' => $account->nature,
                    'expected_nature' => $expectedNature,
                ])],
            ]);
        }

        return $account;
    }
}
