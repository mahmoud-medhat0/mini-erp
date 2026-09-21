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
use App\Models\PartnerLoan;
use App\Models\PartnerLoanRepayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 30 - Partner loans (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §9). Mirror
 * image of employee loans: here the company RECEIVES cash from the partner
 * and owes it back, so disbursement credits partner_loan_payable instead of
 * debiting a receivable. Repayments are recorded one at a time (no automatic
 * payroll-style deduction schedule exists for partners), each posting its own
 * two-line journal entry Dr partner_loan_payable / Cr Cash or Bank.
 */
class PartnerLoanService
{
    public const SETTLEMENT_METHODS = ['cash', 'bank'];

    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly PeriodGuard $periodGuard,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function disburse(array $data, ?int $actorId = null): PartnerLoan
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Disbursing a partner loan requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($data, $actorId): PartnerLoan {
            $payload = $this->validateDisbursementPayload($data);

            $period = $this->financialPeriodForDate($payload['disbursement_date']);
            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock($period->id, $payload['disbursement_date']);

            $payableAccount = $this->mappingService->getAccount('partner_loan_payable', null);
            $this->assertAccountCurrency($payableAccount, $payload['currency'], 'Partner loan payable account');

            $debitAccount = $payload['settlement_account'];
            $this->assertAccountCurrency($debitAccount, $payload['currency'], 'Disbursement settlement account');

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $payload['disbursement_date'],
                'financial_period_id' => $period->id,
                'branch_id' => null,
                'source_type' => 'partner_loan',
                'description' => "Partner loan disbursement - {$payload['partner']->code}",
                'currency' => $payload['currency'],
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
                'account_id' => $debitAccount->id,
                'memo' => "Partner loan disbursement - {$payload['partner']->code}",
                'debit_minor' => $payload['principal_minor'],
                'credit_minor' => 0,
                'debit_txn_minor' => $payload['principal_minor'],
                'credit_txn_minor' => 0,
                'currency' => $payload['currency'],
                'fx_rate_e6' => 1_000_000,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $payableAccount->id,
                'memo' => "Partner loan disbursement - {$payload['partner']->code}",
                'debit_minor' => 0,
                'credit_minor' => $payload['principal_minor'],
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $payload['principal_minor'],
                'currency' => $payload['currency'],
                'fx_rate_e6' => 1_000_000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var PartnerLoan $loan */
            $loan = PartnerLoan::query()->create([
                'partner_id' => $payload['partner']->id,
                'currency' => $payload['currency'],
                'principal_minor' => $payload['principal_minor'],
                'remaining_balance_minor' => $payload['principal_minor'],
                'disbursement_date' => $payload['disbursement_date'],
                'disbursement_method' => $payload['disbursement_method'],
                'cash_account_id' => $payload['cash_account_id'],
                'bank_account_id' => $payload['bank_account_id'],
                'status' => 'active',
                'journal_entry_id' => $postedJournal->id,
                'notes' => $payload['notes'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'partner_loan.disburse', 'partner_loan', $loan->id, after: $loan->fresh($this->relations())->toArray());

            return $loan->fresh($this->relations());
        });
    }

    public function repay(string $id, array $data, ?int $actorId = null): PartnerLoan
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Recording a partner loan repayment requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($id, $data, $actorId): PartnerLoan {
            /** @var PartnerLoan $loan */
            $loan = PartnerLoan::query()->with(['partner'])->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'active') {
                throw ValidationException::withMessages(['status' => [__('Only active loans can be repaid.')]]);
            }

            $payload = $this->validateRepaymentPayload($data, $loan);

            $period = $this->financialPeriodForDate($payload['repayment_date'], 'repayment_date');
            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock($period->id, $payload['repayment_date']);

            $payableAccount = $this->mappingService->getAccount('partner_loan_payable', null);
            $this->assertAccountCurrency($payableAccount, $loan->currency, 'Partner loan payable account');

            $creditAccount = $payload['settlement_account'];
            $this->assertAccountCurrency($creditAccount, $loan->currency, 'Repayment settlement account');

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $payload['repayment_date'],
                'financial_period_id' => $period->id,
                'branch_id' => null,
                'source_type' => 'partner_loan_repayment',
                'description' => "Partner loan repayment - {$loan->partner->code}",
                'currency' => $loan->currency,
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
                'account_id' => $payableAccount->id,
                'memo' => "Partner loan repayment - {$loan->partner->code}",
                'debit_minor' => $payload['amount_minor'],
                'credit_minor' => 0,
                'debit_txn_minor' => $payload['amount_minor'],
                'credit_txn_minor' => 0,
                'currency' => $loan->currency,
                'fx_rate_e6' => 1_000_000,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $creditAccount->id,
                'memo' => "Partner loan repayment - {$loan->partner->code}",
                'debit_minor' => 0,
                'credit_minor' => $payload['amount_minor'],
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $payload['amount_minor'],
                'currency' => $loan->currency,
                'fx_rate_e6' => 1_000_000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            PartnerLoanRepayment::query()->create([
                'partner_loan_id' => $loan->id,
                'repayment_date' => $payload['repayment_date'],
                'amount_minor' => $payload['amount_minor'],
                'repayment_method' => $payload['repayment_method'],
                'cash_account_id' => $payload['cash_account_id'],
                'bank_account_id' => $payload['bank_account_id'],
                'journal_entry_id' => $postedJournal->id,
                'notes' => $payload['notes'],
                'created_by' => $actorId,
            ]);

            $before = $loan->toArray();
            $newBalance = ((int) $loan->remaining_balance_minor) - $payload['amount_minor'];
            $loan->update([
                'remaining_balance_minor' => $newBalance,
                'status' => $newBalance <= 0 ? 'completed' : 'active',
                'updated_by' => $actorId,
                'lock_version' => $loan->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'partner_loan.repay', 'partner_loan', $loan->id, before: $before, after: $loan->fresh($this->relations())->toArray());

            return $loan->fresh($this->relations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): PartnerLoan
    {
        return DB::transaction(function () use ($id, $actorId): PartnerLoan {
            /** @var PartnerLoan $loan */
            $loan = PartnerLoan::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'active' || $loan->remaining_balance_minor !== $loan->principal_minor) {
                throw ValidationException::withMessages(['status' => [__('Only active loans with no repayments yet can be cancelled.')]]);
            }

            $before = $loan->toArray();
            $loan->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
                'lock_version' => $loan->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'partner_loan.cancel', 'partner_loan', $loan->id, before: $before, after: $loan->fresh()->toArray());

            return $loan->fresh($this->relations());
        });
    }

    private function validateDisbursementPayload(array $data): array
    {
        $partnerId = (string) ($data['partner_id'] ?? '');
        /** @var Partner|null $partner */
        $partner = Partner::query()->whereKey($partnerId)->where('status', 'active')->first();
        if (! $partner) {
            throw ValidationException::withMessages(['partner_id' => [__('Selected partner is inactive or missing.')]]);
        }

        $currency = (string) ($data['currency'] ?? '');
        if ($currency === '') {
            throw ValidationException::withMessages(['currency' => [__('Currency is required.')]]);
        }

        $principalMinor = (int) ($data['principal_minor'] ?? 0);
        if ($principalMinor <= 0) {
            throw ValidationException::withMessages(['principal_minor' => [__('Loan principal must be greater than zero.')]]);
        }

        $disbursementDate = (string) ($data['disbursement_date'] ?? '');
        if ($disbursementDate === '') {
            throw ValidationException::withMessages(['disbursement_date' => [__('Disbursement date is required.')]]);
        }

        [$method, $cashAccountId, $bankAccountId, $settlementAccount] = $this->resolveSettlement(
            (string) ($data['disbursement_method'] ?? ''),
            $data['cash_account_id'] ?? null,
            $data['bank_account_id'] ?? null,
        );

        return [
            'partner' => $partner,
            'currency' => $currency,
            'principal_minor' => $principalMinor,
            'disbursement_date' => $disbursementDate,
            'disbursement_method' => $method,
            'cash_account_id' => $cashAccountId,
            'bank_account_id' => $bankAccountId,
            'settlement_account' => $settlementAccount,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function validateRepaymentPayload(array $data, PartnerLoan $loan): array
    {
        $repaymentDate = (string) ($data['repayment_date'] ?? '');
        if ($repaymentDate === '') {
            throw ValidationException::withMessages(['repayment_date' => [__('Repayment date is required.')]]);
        }

        $amountMinor = (int) ($data['amount_minor'] ?? 0);
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => [__('Repayment amount must be greater than zero.')]]);
        }
        if ($amountMinor > (int) $loan->remaining_balance_minor) {
            throw ValidationException::withMessages(['amount_minor' => [__('Repayment amount cannot exceed the remaining loan balance.')]]);
        }

        [$method, $cashAccountId, $bankAccountId, $settlementAccount] = $this->resolveSettlement(
            (string) ($data['repayment_method'] ?? ''),
            $data['cash_account_id'] ?? null,
            $data['bank_account_id'] ?? null,
        );

        return [
            'repayment_date' => $repaymentDate,
            'amount_minor' => $amountMinor,
            'repayment_method' => $method,
            'cash_account_id' => $cashAccountId,
            'bank_account_id' => $bankAccountId,
            'settlement_account' => $settlementAccount,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function resolveSettlement(string $method, mixed $cashAccountId, mixed $bankAccountId): array
    {
        if (! in_array($method, self::SETTLEMENT_METHODS, true)) {
            throw ValidationException::withMessages(['disbursement_method' => [__('Invalid settlement method.')]]);
        }

        if ($method === 'cash') {
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()->with('glAccount')->whereKey((string) $cashAccountId)->where('is_active', true)->first();
            if (! $cashAccount || ! $cashAccount->glAccount) {
                throw ValidationException::withMessages(['cash_account_id' => [__('Selected cash account is inactive or missing.')]]);
            }

            return [$method, $cashAccount->id, null, $cashAccount->glAccount];
        }

        /** @var BankAccount|null $bankAccount */
        $bankAccount = BankAccount::query()->with('glAccount')->whereKey((string) $bankAccountId)->where('is_active', true)->first();
        if (! $bankAccount || ! $bankAccount->glAccount) {
            throw ValidationException::withMessages(['bank_account_id' => [__('Selected bank account is inactive or missing.')]]);
        }

        return [$method, null, $bankAccount->id, $bankAccount->glAccount];
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency does not match the loan currency.', ['label' => $label])]]);
        }
    }

    private function financialPeriodForDate(string $date, string $field = 'disbursement_date'): FinancialPeriod
    {
        $normalized = Carbon::parse($date)->toDateString();

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $normalized)
            ->where('end_date', '>=', $normalized)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([$field => [__('No financial period covers date :date.', ['date' => $normalized])]]);
        }

        return $period;
    }

    private function relations(): array
    {
        return ['partner', 'cashAccount', 'bankAccount', 'repayments'];
    }
}
