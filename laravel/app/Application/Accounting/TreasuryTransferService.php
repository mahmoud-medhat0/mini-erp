<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TreasuryTransfer;
use App\Support\Concurrency\OptimisticLock;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreasuryTransferService
{
    public const ALLOWED_STATUSES = ['draft', 'posted', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $sequenceAllocator,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function create(array $data, int|string|null $actorId = null): TreasuryTransfer
    {
        $this->validateData($data);

        return DB::transaction(function () use ($data, $actorId): TreasuryTransfer {
            $source = $this->resolveEndpoint($data, 'source');
            $destination = $this->resolveEndpoint($data, 'destination');
            $this->assertCompatibleEndpoints($source, $destination, $data);

            $transfer = TreasuryTransfer::query()->create([
                'transfer_date' => $data['transfer_date'],
                'source_type' => $source['type'],
                'source_cash_account_id' => $source['cash_account_id'],
                'source_bank_account_id' => $source['bank_account_id'],
                'destination_type' => $destination['type'],
                'destination_cash_account_id' => $destination['cash_account_id'],
                'destination_bank_account_id' => $destination['bank_account_id'],
                'source_branch_id' => $source['branch_id'],
                'destination_branch_id' => $destination['branch_id'],
                'currency' => $data['currency'],
                'amount_minor' => (int) $data['amount_minor'],
                'fx_rate_e6' => (int) ($data['fx_rate_e6'] ?? 1000000),
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'financial_period_id' => $data['financial_period_id'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 0,
            ]);

            $this->auditLogger->record($actorId, 'create', 'treasury_transfer', (string) $transfer->id, after: $transfer->fresh()->toArray());

            return $transfer->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, int $expectedVersion, int|string|null $actorId = null): TreasuryTransfer
    {
        $this->validateData($data);

        return DB::transaction(function () use ($id, $data, $expectedVersion, $actorId): TreasuryTransfer {
            /** @var TreasuryTransfer $transfer */
            $transfer = TreasuryTransfer::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft treasury transfers can be updated.')]]);
            }

            $before = $transfer->toArray();
            $source = $this->resolveEndpoint($data, 'source');
            $destination = $this->resolveEndpoint($data, 'destination');
            $this->assertCompatibleEndpoints($source, $destination, $data);

            $this->optimisticLock->update('treasury_transfer', ['id' => $id], $expectedVersion, [
                'transfer_date' => $data['transfer_date'],
                'source_type' => $source['type'],
                'source_cash_account_id' => $source['cash_account_id'],
                'source_bank_account_id' => $source['bank_account_id'],
                'destination_type' => $destination['type'],
                'destination_cash_account_id' => $destination['cash_account_id'],
                'destination_bank_account_id' => $destination['bank_account_id'],
                'source_branch_id' => $source['branch_id'],
                'destination_branch_id' => $destination['branch_id'],
                'currency' => $data['currency'],
                'amount_minor' => (int) $data['amount_minor'],
                'fx_rate_e6' => (int) ($data['fx_rate_e6'] ?? 1000000),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'financial_period_id' => $data['financial_period_id'],
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]);

            $updated = TreasuryTransfer::query()->findOrFail($id);
            $this->auditLogger->record($actorId, 'update', 'treasury_transfer', $id, before: $before, after: $updated->toArray());

            return $updated->fresh($this->relations());
        });
    }

    public function post(string $id, int|string $actorId): TreasuryTransfer
    {
        return DB::transaction(function () use ($id, $actorId): TreasuryTransfer {
            /** @var TreasuryTransfer $transfer */
            $transfer = TreasuryTransfer::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($transfer->status === 'posted') {
                return $transfer->fresh($this->relations());
            }

            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft treasury transfers can be posted.')]]);
            }

            $source = $this->resolveEndpoint($transfer->toArray(), 'source', lock: true);
            $destination = $this->resolveEndpoint($transfer->toArray(), 'destination', lock: true);
            $this->assertCompatibleEndpoints($source, $destination, $transfer->toArray());

            $number = $transfer->number ?: $this->nextNumber();
            $journal = $this->createJournal($transfer, $number, $source['gl_account_id'], $destination['gl_account_id'], (int) $actorId);
            $postedJournal = $this->postingEngine->post($journal, (int) $actorId, allowControlAccounts: true);

            $before = $transfer->toArray();
            $transfer->update([
                'number' => $number,
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'posted_by' => $actorId,
                'posted_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'post', 'treasury_transfer', $id, before: $before, after: $transfer->fresh()->toArray());

            return $transfer->fresh($this->relations());
        });
    }

    public function cancel(string $id, int|string|null $actorId = null): TreasuryTransfer
    {
        return DB::transaction(function () use ($id, $actorId): TreasuryTransfer {
            /** @var TreasuryTransfer $transfer */
            $transfer = TreasuryTransfer::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft treasury transfers can be cancelled.')]]);
            }

            $before = $transfer->toArray();
            $transfer->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'cancel', 'treasury_transfer', $id, before: $before, after: $transfer->fresh()->toArray());

            return $transfer->fresh($this->relations());
        });
    }

    private function createJournal(TreasuryTransfer $transfer, string $number, string $sourceGlAccountId, string $destinationGlAccountId, int $actorId): JournalEntry
    {
        $journal = JournalEntry::query()->create([
            'entry_date' => $transfer->transfer_date,
            'financial_period_id' => $transfer->financial_period_id,
            'branch_id' => null,
            'source_type' => 'treasury_transfer',
            'source_id' => $transfer->id,
            'description' => $transfer->description ?: "Treasury Transfer {$number}",
            'reference' => $transfer->reference ?: $number,
            'currency' => $transfer->currency,
            'fx_rate_e6' => $transfer->fx_rate_e6,
            'status' => 'approved',
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'submitted_by' => $actorId,
            'submitted_at' => now(),
            'approved_by' => $actorId,
            'approved_at' => now(),
            'lock_version' => 0,
        ]);

        $journal->lines()->create([
            'line_no' => 1,
            'account_id' => $destinationGlAccountId,
            'branch_id' => $transfer->destination_branch_id,
            'memo' => "Treasury transfer {$number} destination",
            'debit_minor' => $transfer->amount_minor,
            'credit_minor' => 0,
            'currency' => $transfer->currency,
            'fx_rate_e6' => $transfer->fx_rate_e6,
            'debit_txn_minor' => $transfer->amount_minor,
            'credit_txn_minor' => 0,
        ]);

        $journal->lines()->create([
            'line_no' => 2,
            'account_id' => $sourceGlAccountId,
            'branch_id' => $transfer->source_branch_id,
            'memo' => "Treasury transfer {$number} source",
            'debit_minor' => 0,
            'credit_minor' => $transfer->amount_minor,
            'currency' => $transfer->currency,
            'fx_rate_e6' => $transfer->fx_rate_e6,
            'debit_txn_minor' => 0,
            'credit_txn_minor' => $transfer->amount_minor,
        ]);

        return $journal;
    }

    private function validateData(array $data): void
    {
        foreach (['transfer_date', 'source_type', 'destination_type', 'currency', 'amount_minor', 'fiscal_year_id', 'financial_period_id'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                throw ValidationException::withMessages([$field => [__('Field :field is required.', ['field' => $field])]]);
            }
        }

        if ((int) $data['amount_minor'] <= 0) {
            throw ValidationException::withMessages(['amount_minor' => [__('Transfer amount must be greater than zero.')]]);
        }

        if ((int) ($data['fx_rate_e6'] ?? 1000000) <= 0) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be greater than zero.')]]);
        }

        if (! Currency::query()->where('code', $data['currency'])->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Currency :currency does not exist.', ['currency' => $data['currency']])]]);
        }

        if (! FiscalYear::query()->where('id', $data['fiscal_year_id'])->exists()) {
            throw ValidationException::withMessages(['fiscal_year_id' => [__('Fiscal year does not exist.')]]);
        }

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()->find($data['financial_period_id']);
        if (! $period || (string) $period->fiscal_year_id !== (string) $data['fiscal_year_id']) {
            throw ValidationException::withMessages(['financial_period_id' => [__('Financial period is invalid for the selected fiscal year.')]]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['financial_period_id' => [__('Financial period is closed.')]]);
        }

        JournalDraftService::assertDateInPeriod($period, (string) $data['transfer_date']);
    }

    private function resolveEndpoint(array $data, string $side, bool $lock = false): array
    {
        $type = $data["{$side}_type"] ?? null;
        if (! in_array($type, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(["{$side}_type" => [__('Endpoint type must be cash or bank.')]]);
        }

        $cashKey = "{$side}_cash_account_id";
        $bankKey = "{$side}_bank_account_id";
        $cashId = $data[$cashKey] ?? null;
        $bankId = $data[$bankKey] ?? null;

        if ($type === 'cash' && empty($cashId)) {
            throw ValidationException::withMessages([$cashKey => [__('Cash account is required for a cash endpoint.')]]);
        }

        if ($type === 'bank' && empty($bankId)) {
            throw ValidationException::withMessages([$bankKey => [__('Bank account is required for a bank endpoint.')]]);
        }

        if (($type === 'cash' && ! empty($bankId)) || ($type === 'bank' && ! empty($cashId))) {
            throw ValidationException::withMessages(["{$side}_type" => [__('Endpoint account type and selected account must match.')]]);
        }

        if ($type === 'cash') {
            $query = CashAccount::query()->where('id', $cashId);
            /** @var CashAccount|null $account */
            $account = $lock ? $query->lockForUpdate()->first() : $query->first();
        } else {
            $query = BankAccount::query()->where('id', $bankId);
            /** @var BankAccount|null $account */
            $account = $lock ? $query->lockForUpdate()->first() : $query->first();
        }

        if (! $account || ! $account->is_active) {
            throw ValidationException::withMessages(["{$side}_type" => [__('Selected endpoint account is missing or inactive.')]]);
        }

        /** @var Account|null $glAccount */
        $glAccount = Account::query()->where('id', $account->gl_account_id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $glAccount || ! $glAccount->is_active || $glAccount->currency !== $account->currency) {
            throw ValidationException::withMessages(["{$side}_type" => [__('Linked GL account is missing, inactive, or currency-mismatched.')]]);
        }

        return [
            'type' => $type,
            'cash_account_id' => $type === 'cash' ? $account->id : null,
            'bank_account_id' => $type === 'bank' ? $account->id : null,
            'branch_id' => $account->branch_id,
            'currency' => $account->currency,
            'gl_account_id' => $account->gl_account_id,
        ];
    }

    private function assertCompatibleEndpoints(array $source, array $destination, array $data): void
    {
        if ($source['type'] === $destination['type'] && $source["{$source['type']}_account_id"] === $destination["{$destination['type']}_account_id"]) {
            throw ValidationException::withMessages(['destination_type' => [__('Source and destination accounts must be different.')]]);
        }

        if ($source['currency'] !== $destination['currency'] || $source['currency'] !== $data['currency']) {
            throw ValidationException::withMessages(['currency' => [__('Source, destination, and transfer currency must match.')]]);
        }
    }

    private function nextNumber(): string
    {
        $seq = $this->sequenceAllocator->nextValue('treasury.transfer');

        return 'TRF-'.date('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function relations(): array
    {
        return [
            'sourceCashAccount.branch',
            'sourceBankAccount.branch',
            'destinationCashAccount.branch',
            'destinationBankAccount.branch',
            'sourceBranch',
            'destinationBranch',
            'journalEntry',
        ];
    }
}
