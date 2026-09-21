<?php

namespace App\Application\Partners;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 30 - Partner equity transactions (PHASE_25_GAP_CLOSURE_DECISION_PACK.md
 * §9). Three transaction types all move between a real cash/bank account and
 * the shared partner_capital_account control account:
 *   - contribution: Dr Cash/Bank / Cr partner_capital_account (partner pays in)
 *   - drawing:      Dr partner_capital_account / Cr Cash/Bank (partner withdraws)
 *   - distribution:  Dr partner_capital_account / Cr Cash/Bank (profit share paid out)
 * Drawing and distribution share the same GL direction - this codebase has no
 * separate retained_earnings mapping key, and collapsing both into a capital
 * reduction against the same control account keeps the model consistent with
 * the control-account + subledger pattern used everywhere else. The
 * partner_transaction subledger row (tagged by partner_id and transaction_type)
 * is what preserves the distinction for reporting.
 */
class PartnerTransactionService
{
    public const TRANSACTION_TYPES = ['contribution', 'drawing', 'distribution'];

    public const SETTLEMENT_METHODS = ['cash', 'bank'];

    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly PeriodGuard $periodGuard,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, ?int $actorId = null): PartnerTransaction
    {
        return DB::transaction(function () use ($data, $actorId): PartnerTransaction {
            $payload = $this->validatePayload($data);

            /** @var PartnerTransaction $transaction */
            $transaction = PartnerTransaction::query()->create([
                'partner_id' => $payload['partner']->id,
                'transaction_type' => $payload['transaction_type'],
                'transaction_date' => $payload['transaction_date'],
                'financial_period_id' => $payload['period']->id,
                'currency' => $payload['currency'],
                'amount_minor' => $payload['amount_minor'],
                'status' => 'draft',
                'reference' => $payload['reference'],
                'notes' => $payload['notes'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'partner_transaction.create', 'partner_transaction', $transaction->id, after: $transaction->fresh($this->relations())->toArray());

            return $transaction->fresh($this->relations());
        });
    }

    public function post(string $id, array $settlement, ?int $actorId = null): PartnerTransaction
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Posting a partner transaction requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($id, $settlement, $actorId): PartnerTransaction {
            /** @var PartnerTransaction $transaction */
            $transaction = PartnerTransaction::query()->with(['partner'])->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($transaction->status === 'posted') {
                return $transaction->fresh($this->relations());
            }
            if ($transaction->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft partner transactions can be posted.')]]);
            }

            $settlementAccount = $this->resolveSettlementAccount($settlement, $transaction->currency);

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock($transaction->financial_period_id, $transaction->transaction_date->format('Y-m-d'));

            $capitalAccount = $this->mappingService->getAccount('partner_capital_account', null);
            $this->assertAccountCurrency($capitalAccount, $transaction->currency, 'Partner capital account');

            $number = $transaction->number ?? $this->numberSequenceAllocator->nextNumber('partners.transaction', 'PTX', $transaction->transaction_date);

            $isInflow = $transaction->transaction_type === 'contribution';

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $transaction->transaction_date,
                'financial_period_id' => $period->id,
                'branch_id' => null,
                'source_type' => 'partner_transaction',
                'source_id' => $transaction->id,
                'description' => "Partner {$transaction->transaction_type} {$number} - {$transaction->partner->code}",
                'currency' => $transaction->currency,
                'fx_rate_e6' => 1_000_000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $isInflow ? $settlementAccount->id : $capitalAccount->id,
                'memo' => "Partner {$transaction->transaction_type} {$number}",
                'debit_minor' => $transaction->amount_minor,
                'credit_minor' => 0,
                'debit_txn_minor' => $transaction->amount_minor,
                'credit_txn_minor' => 0,
                'currency' => $transaction->currency,
                'fx_rate_e6' => 1_000_000,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $isInflow ? $capitalAccount->id : $settlementAccount->id,
                'memo' => "Partner {$transaction->transaction_type} {$number}",
                'debit_minor' => 0,
                'credit_minor' => $transaction->amount_minor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $transaction->amount_minor,
                'currency' => $transaction->currency,
                'fx_rate_e6' => 1_000_000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            $before = $transaction->toArray();
            $transaction->update([
                'number' => $number,
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'posted_by' => $actorId,
                'posted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $transaction->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'partner_transaction.post', 'partner_transaction', $transaction->id, before: $before, after: $transaction->fresh($this->relations())->toArray());

            return $transaction->fresh($this->relations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): PartnerTransaction
    {
        return DB::transaction(function () use ($id, $actorId): PartnerTransaction {
            /** @var PartnerTransaction $transaction */
            $transaction = PartnerTransaction::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($transaction->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft partner transactions can be cancelled.')]]);
            }

            $before = $transaction->toArray();
            $transaction->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
                'lock_version' => $transaction->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'partner_transaction.cancel', 'partner_transaction', $transaction->id, before: $before, after: $transaction->fresh()->toArray());

            return $transaction->fresh($this->relations());
        });
    }

    private function validatePayload(array $data): array
    {
        $partnerId = (string) ($data['partner_id'] ?? '');
        /** @var Partner|null $partner */
        $partner = Partner::query()->whereKey($partnerId)->where('status', 'active')->first();
        if (! $partner) {
            throw ValidationException::withMessages(['partner_id' => [__('Selected partner is inactive or missing.')]]);
        }

        $transactionType = (string) ($data['transaction_type'] ?? '');
        if (! in_array($transactionType, self::TRANSACTION_TYPES, true)) {
            throw ValidationException::withMessages(['transaction_type' => [__('Invalid partner transaction type.')]]);
        }

        $transactionDate = (string) ($data['transaction_date'] ?? '');
        if ($transactionDate === '') {
            throw ValidationException::withMessages(['transaction_date' => [__('Transaction date is required.')]]);
        }

        $currency = (string) ($data['currency'] ?? '');
        if ($currency === '') {
            throw ValidationException::withMessages(['currency' => [__('Currency is required.')]]);
        }

        $amountMinor = (int) ($data['amount_minor'] ?? 0);
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => [__('Amount must be greater than zero.')]]);
        }

        $period = $this->financialPeriodForDate($transactionDate);

        return [
            'partner' => $partner,
            'transaction_type' => $transactionType,
            'transaction_date' => $transactionDate,
            'period' => $period,
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function resolveSettlementAccount(array $settlement, string $currency): Account
    {
        $method = (string) ($settlement['settlement_method'] ?? '');
        if (! in_array($method, self::SETTLEMENT_METHODS, true)) {
            throw ValidationException::withMessages(['settlement_method' => [__('Invalid settlement method.')]]);
        }

        if ($method === 'cash') {
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()->with('glAccount')->whereKey((string) ($settlement['cash_account_id'] ?? ''))->where('is_active', true)->first();
            if (! $cashAccount || ! $cashAccount->glAccount) {
                throw ValidationException::withMessages(['cash_account_id' => [__('Selected cash account is inactive or missing.')]]);
            }
            $account = $cashAccount->glAccount;
        } else {
            /** @var BankAccount|null $bankAccount */
            $bankAccount = BankAccount::query()->with('glAccount')->whereKey((string) ($settlement['bank_account_id'] ?? ''))->where('is_active', true)->first();
            if (! $bankAccount || ! $bankAccount->glAccount) {
                throw ValidationException::withMessages(['bank_account_id' => [__('Selected bank account is inactive or missing.')]]);
            }
            $account = $bankAccount->glAccount;
        }

        $this->assertAccountCurrency($account, $currency, 'Settlement account');

        return $account;
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency does not match the transaction currency.', ['label' => $label])]]);
        }
    }

    private function financialPeriodForDate(string $date): FinancialPeriod
    {
        $normalized = Carbon::parse($date)->toDateString();

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $normalized)
            ->where('end_date', '>=', $normalized)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages(['transaction_date' => [__('No financial period covers date :date.', ['date' => $normalized])]]);
        }

        return $period;
    }

    private function relations(): array
    {
        return ['partner', 'financialPeriod'];
    }
}
