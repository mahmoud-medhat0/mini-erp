<?php

namespace App\Application\Accounting;

use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\IncomingCheque;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ReceivableEntry;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IncomingChequeService
{
    public function __construct(
        private readonly PostingEngine $postingEngine,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createDraft(array $data, int $actorId): IncomingCheque
    {
        $customerId = $data['customer_id'] ?? null;
        $chequeNumber = trim((string) ($data['cheque_number'] ?? ''));
        $drawerBankName = trim((string) ($data['drawer_bank_name'] ?? $data['bank_name'] ?? ''));
        $dueDate = $data['due_date'] ?? null;
        $currency = CurrencyInput::required($data['currency'] ?? null);
        $amountMinor = $data['amount_minor'] ?? 0;

        Customer::query()->findOrFail($customerId);

        if ($chequeNumber === '') {
            throw ValidationException::withMessages([
                'cheque_number' => [__('Physical cheque number is required.')],
            ]);
        }

        if ($drawerBankName === '') {
            throw ValidationException::withMessages([
                'bank_name' => [__('Drawer bank name is required.')],
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

        /** @var IncomingCheque $cheque */
        $cheque = IncomingCheque::query()->create([
            'customer_id' => $customerId,
            'cheque_number' => $chequeNumber,
            'drawer_bank_name' => $drawerBankName,
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
            entityType: 'incoming_cheque',
            entityId: $cheque->id,
            before: null,
            after: $cheque->toArray(),
        );

        return $cheque;
    }

    public function receive(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $receivedDate,
        int $actorId,
        ?string $idempotencyKey = null,
    ): IncomingCheque {
        $idempotencyKey ??= "incoming_cheque:{$chequeId}:receive";

        $result = $this->idempotencyStore->run(
            operation: 'incoming_cheque.receive',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $receivedDate, $actorId): IncomingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $receivedDate, $actorId): IncomingCheque {
                    /** @var IncomingCheque $cheque */
                    $cheque = IncomingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'received') {
                        return $cheque;
                    }

                    if ($cheque->status !== 'draft') {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot receive cheque from status [:status]. Only draft cheques can be received.', ['status' => $cheque->status])],
                        ]);
                    }

                    // Period validation
                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $receivedDate);

                    // Accounts resolution
                    $chequesUnderCollAcc = $this->resolveMappedAccount('cheques_under_collection', 'asset', 'debit', $cheque->currency);
                    $arControlAcc = $this->resolveMappedAccount('ar_control', 'asset', 'debit', $cheque->currency);

                    // Number allocation
                    $number = $cheque->number;
                    if (! $number) {
                        $number = $this->numberSequenceAllocator->nextNumber('incoming_cheque', 'ICHQ', $receivedDate);
                    }

                    // Create Journal Entry (Dr Cheques Under Collection, Cr AR Control)
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $receivedDate,
                        'source_type' => 'incoming_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Customer Cheque Received [{$cheque->cheque_number}]",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $chequesUnderCollAcc->id,
                        'memo' => "Dr Cheques Under Collection [{$cheque->cheque_number}]",
                        'debit_minor' => $cheque->amount_minor,
                        'credit_minor' => 0,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => $cheque->amount_minor,
                        'credit_txn_minor' => 0,
                    ]);

                    $arControlLine = JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 2,
                        'account_id' => $arControlAcc->id,
                        'memo' => "Cr AR Control [{$cheque->cheque_number}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId, true);

                    // Create ReceivableEntry Credit
                    $receivableEntry = ReceivableEntry::query()->create([
                        'customer_id' => $cheque->customer_id,
                        'source_type' => 'incoming_cheque',
                        'source_id' => $cheque->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $arControlLine->id,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $receivedDate,
                        'description' => "Customer Cheque Received [{$cheque->cheque_number}]",
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
                        'number' => $number,
                        'status' => 'received',
                        'received_fiscal_year_id' => $fiscalYearId,
                        'received_financial_period_id' => $financialPeriodId,
                        'received_date' => $receivedDate,
                        'receive_journal_entry_id' => $postedJournal->id,
                        'receivable_entry_id' => $receivableEntry->id,
                        'received_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'receive',
                        entityType: 'incoming_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return IncomingCheque::query()->findOrFail($chequeId);
        }

        /** @var IncomingCheque */
        return $result->value;
    }

    public function deposit(string $chequeId, string $bankAccountId, string $depositedDate, int $actorId): IncomingCheque
    {
        return DB::transaction(function () use ($chequeId, $bankAccountId, $depositedDate, $actorId): IncomingCheque {
            /** @var IncomingCheque $cheque */
            $cheque = IncomingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

            if (! in_array($cheque->status, ['received', 'deposited'], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('Cannot deposit cheque from status [:status]. Only received cheques can be deposited.', ['status' => $cheque->status])],
                ]);
            }

            $bankAccount = BankAccount::query()->where('id', $bankAccountId)->lockForUpdate()->firstOrFail();
            if (! $bankAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => [__('Selected deposit bank account is inactive.')],
                ]);
            }

            if ($bankAccount->currency !== $cheque->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Deposit bank account currency [:bank_currency] does not match cheque currency [:cheque_currency].', [
                        'bank_currency' => $bankAccount->currency,
                        'cheque_currency' => $cheque->currency,
                    ])],
                ]);
            }

            $before = $cheque->toArray();
            $cheque->update([
                'status' => 'deposited',
                'deposit_bank_account_id' => $bankAccount->id,
                'deposited_date' => $depositedDate,
                'deposited_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'deposit',
                entityType: 'incoming_cheque',
                entityId: $cheque->id,
                before: $before,
                after: $cheque->fresh()->toArray(),
            );

            return $cheque->fresh();
        });
    }

    public function clear(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $clearedDate,
        ?string $bankAccountId,
        int $actorId,
        ?string $idempotencyKey = null,
    ): IncomingCheque {
        $idempotencyKey ??= "incoming_cheque:{$chequeId}:clear";

        $result = $this->idempotencyStore->run(
            operation: 'incoming_cheque.clear',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $clearedDate, $bankAccountId, $actorId): IncomingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $clearedDate, $bankAccountId, $actorId): IncomingCheque {
                    /** @var IncomingCheque $cheque */
                    $cheque = IncomingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'cleared') {
                        return $cheque;
                    }

                    if (! in_array($cheque->status, ['received', 'deposited'], true)) {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot clear cheque from status [:status]. Only received or deposited cheques can be cleared.', ['status' => $cheque->status])],
                        ]);
                    }

                    $targetBankId = $bankAccountId ?? $cheque->deposit_bank_account_id;
                    if (! $targetBankId) {
                        throw ValidationException::withMessages([
                            'bank_account_id' => [__('Bank account must be specified to clear incoming cheque.')],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $clearedDate);

                    $bankAccount = BankAccount::query()->where('id', $targetBankId)->lockForUpdate()->firstOrFail();
                    if (! $bankAccount->is_active) {
                        throw ValidationException::withMessages([
                            'bank_account_id' => [__('Selected bank account is inactive.')],
                        ]);
                    }

                    if ($bankAccount->currency !== $cheque->currency) {
                        throw ValidationException::withMessages([
                            'currency' => [__('Bank account currency [:bank_currency] does not match cheque currency [:cheque_currency].', [
                                'bank_currency' => $bankAccount->currency,
                                'cheque_currency' => $cheque->currency,
                            ])],
                        ]);
                    }

                    $chequesUnderCollAcc = $this->resolveMappedAccount('cheques_under_collection', 'asset', 'debit', $cheque->currency);

                    /** @var Account $bankGlAcc */
                    $bankGlAcc = Account::query()->where('id', $bankAccount->gl_account_id)->lockForUpdate()->firstOrFail();
                    if (! $bankGlAcc->is_active || $bankGlAcc->currency !== $cheque->currency) {
                        throw ValidationException::withMessages([
                            'bank_account_id' => [__('Bank account GL account is inactive or currency mismatch.')],
                        ]);
                    }

                    // Create Journal Entry (Dr Bank GL Account, Cr Cheques Under Collection)
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $clearedDate,
                        'source_type' => 'incoming_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Customer Cheque Cleared [{$cheque->cheque_number}]",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $bankGlAcc->id,
                        'memo' => "Dr Bank GL Account [{$bankGlAcc->code}]",
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
                        'account_id' => $chequesUnderCollAcc->id,
                        'memo' => "Cr Cheques Under Collection [{$cheque->cheque_number}]",
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
                        'deposit_bank_account_id' => $bankAccount->id,
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
                        entityType: 'incoming_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return IncomingCheque::query()->findOrFail($chequeId);
        }

        /** @var IncomingCheque */
        return $result->value;
    }

    public function bounceBeforeClear(
        string $chequeId,
        string $fiscalYearId,
        string $financialPeriodId,
        string $bouncedDate,
        string $reason,
        int $actorId,
        ?string $idempotencyKey = null,
    ): IncomingCheque {
        $idempotencyKey ??= "incoming_cheque:{$chequeId}:bounce";

        $result = $this->idempotencyStore->run(
            operation: 'incoming_cheque.bounce',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $bouncedDate, $reason, $actorId): IncomingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $bouncedDate, $reason, $actorId): IncomingCheque {
                    /** @var IncomingCheque $cheque */
                    $cheque = IncomingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'bounced') {
                        return $cheque;
                    }

                    if ($cheque->status === 'cleared') {
                        throw new InvalidArgumentException(__('OWNER DECISION REQUIRED: Post-clear bounce workflow is not implemented in pre-clear cheque lifecycle.'));
                    }

                    if (! in_array($cheque->status, ['received', 'deposited'], true)) {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot bounce cheque from status [:status]. Only received or deposited pre-clear cheques can be bounced.', ['status' => $cheque->status])],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $bouncedDate);

                    $arControlAcc = $this->resolveMappedAccount('ar_control', 'asset', 'debit', $cheque->currency);
                    $chequesUnderCollAcc = $this->resolveMappedAccount('cheques_under_collection', 'asset', 'debit', $cheque->currency);

                    // Journal Entry: Dr AR Control, Cr Cheques Under Collection
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $bouncedDate,
                        'source_type' => 'incoming_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Customer Cheque Bounced [{$cheque->cheque_number}]: {$reason}",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $arControlAcc->id,
                        'memo' => "Dr AR Control [{$cheque->cheque_number}]",
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
                        'account_id' => $chequesUnderCollAcc->id,
                        'memo' => "Cr Cheques Under Collection [{$cheque->cheque_number}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId, true);

                    // Create ReceivableEntry Debit (restores customer AR balance)
                    $bounceReceivableEntry = ReceivableEntry::query()->create([
                        'customer_id' => $cheque->customer_id,
                        'source_type' => 'incoming_cheque_bounce',
                        'source_id' => $cheque->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $journal->lines()->where('line_no', 1)->value('id'),
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $bouncedDate,
                        'description' => "Customer Cheque Bounced [{$cheque->cheque_number}]",
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
                        'status' => 'bounced',
                        'bounced_fiscal_year_id' => $fiscalYearId,
                        'bounced_financial_period_id' => $financialPeriodId,
                        'bounced_date' => $bouncedDate,
                        'bounce_journal_entry_id' => $postedJournal->id,
                        'bounce_receivable_entry_id' => $bounceReceivableEntry->id,
                        'bounced_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'bounce',
                        entityType: 'incoming_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return IncomingCheque::query()->findOrFail($chequeId);
        }

        /** @var IncomingCheque */
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
    ): IncomingCheque {
        $idempotencyKey ??= "incoming_cheque:{$chequeId}:return";

        $result = $this->idempotencyStore->run(
            operation: 'incoming_cheque.return',
            rawKey: $idempotencyKey,
            callback: function () use ($chequeId, $fiscalYearId, $financialPeriodId, $returnedDate, $reason, $actorId): IncomingCheque {
                return DB::transaction(function () use ($chequeId, $fiscalYearId, $financialPeriodId, $returnedDate, $reason, $actorId): IncomingCheque {
                    /** @var IncomingCheque $cheque */
                    $cheque = IncomingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

                    if ($cheque->status === 'returned') {
                        return $cheque;
                    }

                    if ($cheque->status === 'cleared') {
                        throw new InvalidArgumentException(__('OWNER DECISION REQUIRED: Post-clear return workflow is not implemented in pre-clear cheque lifecycle.'));
                    }

                    if (! in_array($cheque->status, ['received', 'deposited'], true)) {
                        throw ValidationException::withMessages([
                            'status' => [__('Cannot return cheque from status [:status]. Only received or deposited pre-clear cheques can be returned.', ['status' => $cheque->status])],
                        ]);
                    }

                    $period = $this->validateAndLockPeriod($fiscalYearId, $financialPeriodId, $returnedDate);

                    $arControlAcc = $this->resolveMappedAccount('ar_control', 'asset', 'debit', $cheque->currency);
                    $chequesUnderCollAcc = $this->resolveMappedAccount('cheques_under_collection', 'asset', 'debit', $cheque->currency);

                    // Journal Entry: Dr AR Control, Cr Cheques Under Collection
                    $journal = JournalEntry::query()->create([
                        'number' => null,
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $returnedDate,
                        'source_type' => 'incoming_cheque',
                        'source_id' => $cheque->id,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'description' => "Customer Cheque Returned [{$cheque->cheque_number}]: {$reason}",
                        'status' => 'draft',
                        'created_by' => $actorId,
                    ]);

                    JournalLine::query()->create([
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $arControlAcc->id,
                        'memo' => "Dr AR Control [{$cheque->cheque_number}]",
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
                        'account_id' => $chequesUnderCollAcc->id,
                        'memo' => "Cr Cheques Under Collection [{$cheque->cheque_number}]",
                        'debit_minor' => 0,
                        'credit_minor' => $cheque->amount_minor,
                        'currency' => $cheque->currency,
                        'fx_rate_e6' => $cheque->fx_rate_e6,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cheque->amount_minor,
                    ]);

                    $postedJournal = $this->postingEngine->post($journal, $actorId, true);

                    // Create ReceivableEntry Debit (restores customer AR balance)
                    $returnReceivableEntry = ReceivableEntry::query()->create([
                        'customer_id' => $cheque->customer_id,
                        'source_type' => 'incoming_cheque_return',
                        'source_id' => $cheque->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $journal->lines()->where('line_no', 1)->value('id'),
                        'financial_period_id' => $financialPeriodId,
                        'entry_date' => $returnedDate,
                        'description' => "Customer Cheque Returned [{$cheque->cheque_number}]",
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
                        'status' => 'returned',
                        'returned_fiscal_year_id' => $fiscalYearId,
                        'returned_financial_period_id' => $financialPeriodId,
                        'returned_date' => $returnedDate,
                        'return_journal_entry_id' => $postedJournal->id,
                        'return_receivable_entry_id' => $returnReceivableEntry->id,
                        'returned_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'return',
                        entityType: 'incoming_cheque',
                        entityId: $cheque->id,
                        before: $before,
                        after: $cheque->fresh()->toArray(),
                    );

                    return $cheque->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return IncomingCheque::query()->findOrFail($chequeId);
        }

        /** @var IncomingCheque */
        return $result->value;
    }

    public function cancelDraft(string $chequeId, string $reason, int $actorId): IncomingCheque
    {
        return DB::transaction(function () use ($chequeId, $actorId): IncomingCheque {
            /** @var IncomingCheque $cheque */
            $cheque = IncomingCheque::query()->where('id', $chequeId)->lockForUpdate()->firstOrFail();

            if ($cheque->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('Cannot cancel cheque from status [:status]. Only draft cheques can be cancelled.', ['status' => $cheque->status])],
                ]);
            }

            $before = $cheque->toArray();
            $cheque->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'cancel',
                entityType: 'incoming_cheque',
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
                'received_date' => [__('Event date [:date] is outside period range [:start - :end].', [
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
