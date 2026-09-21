<?php

namespace App\Application\Payroll;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\PayrollEmployeeLoan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 28 - Payroll employee loans/advances (PHASE_25_GAP_CLOSURE_DECISION_PACK.md
 * §7). Disbursement posts a real two-line journal entry (Dr employee_loan_receivable
 * / Cr the chosen cash or bank account) through the same PostingEngine every other
 * module uses. Recurring repayment happens automatically inside the existing
 * payroll run: PayrollRunService injects a LOAN_REPAYMENT deduction component
 * credited to the same employee_loan_receivable account, so the loan balance
 * decreases correctly the moment payroll posts - no separate GL code is needed
 * for repayment.
 */
class PayrollEmployeeLoanService
{
    public const LOAN_TYPES = ['loan', 'advance'];

    public const DISBURSEMENT_METHODS = ['cash', 'bank'];

    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly PeriodGuard $periodGuard,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function disburse(array $data, ?int $actorId = null): PayrollEmployeeLoan
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Disbursing a loan requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($data, $actorId): PayrollEmployeeLoan {
            $payload = $this->validatePayload($data);

            $period = $this->financialPeriodForDate($payload['disbursement_date']);
            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock($period->id, $payload['disbursement_date']);

            $receivableAccount = $this->mappingService->getAccount('employee_loan_receivable', null);
            $this->assertAccountCurrency($receivableAccount, $payload['currency'], 'Employee loan receivable account');

            $creditAccount = $payload['credit_account'];
            $this->assertAccountCurrency($creditAccount, $payload['currency'], 'Disbursement settlement account');

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $payload['disbursement_date'],
                'financial_period_id' => $period->id,
                'branch_id' => null,
                'source_type' => 'payroll_employee_loan',
                'description' => "Employee loan disbursement - {$payload['employee']->code}",
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
                'account_id' => $receivableAccount->id,
                'memo' => "Employee loan disbursement - {$payload['employee']->code}",
                'debit_minor' => $payload['principal_minor'],
                'credit_minor' => 0,
                'debit_txn_minor' => $payload['principal_minor'],
                'credit_txn_minor' => 0,
                'currency' => $payload['currency'],
                'fx_rate_e6' => 1_000_000,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $creditAccount->id,
                'memo' => "Employee loan disbursement - {$payload['employee']->code}",
                'debit_minor' => 0,
                'credit_minor' => $payload['principal_minor'],
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $payload['principal_minor'],
                'currency' => $payload['currency'],
                'fx_rate_e6' => 1_000_000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var PayrollEmployeeLoan $loan */
            $loan = PayrollEmployeeLoan::query()->create([
                'employee_id' => $payload['employee']->id,
                'loan_type' => $payload['loan_type'],
                'currency' => $payload['currency'],
                'principal_minor' => $payload['principal_minor'],
                'remaining_balance_minor' => $payload['principal_minor'],
                'installment_amount_minor' => $payload['installment_amount_minor'],
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

            $this->auditLogger->record($actorId, 'payroll_employee_loan.disburse', 'payroll_employee_loan', $loan->id, after: $loan->fresh($this->relations())->toArray());

            return $loan->fresh($this->relations());
        });
    }

    /**
     * Marks the remaining balance as settled outside the payroll deduction
     * cycle (e.g. the employee paid it back directly). This does not post a
     * new journal entry - any cash actually collected must be recorded
     * separately (a manual journal entry crediting employee_loan_receivable),
     * matching the bounded scope of this slice.
     */
    public function settleRemaining(string $id, string $reason, ?int $actorId = null): PayrollEmployeeLoan
    {
        return DB::transaction(function () use ($id, $reason, $actorId): PayrollEmployeeLoan {
            /** @var PayrollEmployeeLoan $loan */
            $loan = PayrollEmployeeLoan::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'active') {
                throw ValidationException::withMessages(['status' => [__('Only active loans can be settled.')]]);
            }

            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => [__('A reason is required to settle a loan outside payroll.')]]);
            }

            $before = $loan->toArray();
            $loan->update([
                'remaining_balance_minor' => 0,
                'status' => 'completed',
                'notes' => trim(($loan->notes ? $loan->notes."\n" : '')."Settled outside payroll: {$reason}"),
                'updated_by' => $actorId,
                'lock_version' => $loan->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_employee_loan.settle', 'payroll_employee_loan', $loan->id, before: $before, after: $loan->fresh()->toArray());

            return $loan->fresh($this->relations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): PayrollEmployeeLoan
    {
        return DB::transaction(function () use ($id, $actorId): PayrollEmployeeLoan {
            /** @var PayrollEmployeeLoan $loan */
            $loan = PayrollEmployeeLoan::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'active' || $loan->remaining_balance_minor !== $loan->principal_minor) {
                throw ValidationException::withMessages(['status' => [__('Only active loans with no repayments yet can be cancelled. Use settlement for loans already in progress.')]]);
            }

            $before = $loan->toArray();
            $loan->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
                'lock_version' => $loan->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_employee_loan.cancel', 'payroll_employee_loan', $loan->id, before: $before, after: $loan->fresh()->toArray());

            return $loan->fresh($this->relations());
        });
    }

    private function validatePayload(array $data): array
    {
        $employeeId = (string) ($data['employee_id'] ?? '');
        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($employeeId)->where('status', 'active')->first();
        if (! $employee) {
            throw ValidationException::withMessages(['employee_id' => [__('Selected employee is inactive or missing.')]]);
        }

        $loanType = (string) ($data['loan_type'] ?? 'loan');
        if (! in_array($loanType, self::LOAN_TYPES, true)) {
            throw ValidationException::withMessages(['loan_type' => [__('Invalid loan type.')]]);
        }

        $currency = (string) ($data['currency'] ?? $employee->currency);

        $principalMinor = (int) ($data['principal_minor'] ?? 0);
        if ($principalMinor <= 0) {
            throw ValidationException::withMessages(['principal_minor' => [__('Loan principal must be greater than zero.')]]);
        }

        $installmentAmountMinor = (int) ($data['installment_amount_minor'] ?? 0);
        if ($installmentAmountMinor <= 0) {
            throw ValidationException::withMessages(['installment_amount_minor' => [__('Installment amount must be greater than zero.')]]);
        }
        if ($installmentAmountMinor > $principalMinor) {
            throw ValidationException::withMessages(['installment_amount_minor' => [__('Installment amount cannot exceed the loan principal.')]]);
        }

        $disbursementDate = (string) ($data['disbursement_date'] ?? '');
        if ($disbursementDate === '') {
            throw ValidationException::withMessages(['disbursement_date' => [__('Disbursement date is required.')]]);
        }

        $method = (string) ($data['disbursement_method'] ?? '');
        if (! in_array($method, self::DISBURSEMENT_METHODS, true)) {
            throw ValidationException::withMessages(['disbursement_method' => [__('Invalid disbursement method.')]]);
        }

        $cashAccountId = null;
        $bankAccountId = null;
        $creditAccount = null;

        if ($method === 'cash') {
            $cashAccountId = (string) ($data['cash_account_id'] ?? '');
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()->with('glAccount')->whereKey($cashAccountId)->where('is_active', true)->first();
            if (! $cashAccount || ! $cashAccount->glAccount) {
                throw ValidationException::withMessages(['cash_account_id' => [__('Selected cash account is inactive or missing.')]]);
            }
            $creditAccount = $cashAccount->glAccount;
        } else {
            $bankAccountId = (string) ($data['bank_account_id'] ?? '');
            /** @var BankAccount|null $bankAccount */
            $bankAccount = BankAccount::query()->with('glAccount')->whereKey($bankAccountId)->where('is_active', true)->first();
            if (! $bankAccount || ! $bankAccount->glAccount) {
                throw ValidationException::withMessages(['bank_account_id' => [__('Selected bank account is inactive or missing.')]]);
            }
            $creditAccount = $bankAccount->glAccount;
        }

        return [
            'employee' => $employee,
            'loan_type' => $loanType,
            'currency' => $currency,
            'principal_minor' => $principalMinor,
            'installment_amount_minor' => $installmentAmountMinor,
            'disbursement_date' => $disbursementDate,
            'disbursement_method' => $method,
            'cash_account_id' => $cashAccountId ?: null,
            'bank_account_id' => $bankAccountId ?: null,
            'credit_account' => $creditAccount,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency does not match the loan currency.', ['label' => $label])]]);
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
            throw ValidationException::withMessages(['disbursement_date' => [__('No financial period covers date :date.', ['date' => $normalized])]]);
        }

        return $period;
    }

    private function relations(): array
    {
        return ['employee', 'cashAccount', 'bankAccount', 'installments'];
    }
}
