<?php

namespace App\Application\Payroll;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayrollRunService
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public const RUN_TYPES = ['regular', 'bonus', 'adjustment'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    public function createRun(array $data, ?int $actorId = null): PayrollRun
    {
        return DB::transaction(function () use ($data, $actorId): PayrollRun {
            $period = $this->ensurePayrollPeriod($data, $actorId);
            $branchId = $this->normalizeBranchId($data['branch_id'] ?? null);
            $currency = CurrencyInput::required($data['currency'] ?? null);
            $runType = (string) ($data['run_type'] ?? 'regular');

            $this->assertBranch($branchId);
            $this->assertCurrency($currency);
            $this->assertRunType($runType);

            /** @var PayrollRun $run */
            $run = PayrollRun::query()->create([
                'payroll_period_id' => $period->id,
                'branch_id' => $branchId,
                'financial_period_id' => $period->financial_period_id,
                'payroll_date' => $period->payment_date,
                'run_type' => $runType,
                'currency' => $currency,
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->rebuildLines($run);
            $run = $run->fresh($this->defaultRelations());

            $this->auditLogger->record($actorId, 'payroll_run.create', 'payroll_run', $run->id, after: $run->toArray());

            return $run;
        });
    }

    public function regenerate(string $id, ?int $actorId = null): PayrollRun
    {
        return DB::transaction(function () use ($id, $actorId): PayrollRun {
            /** @var PayrollRun $run */
            $run = PayrollRun::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($run->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft payroll runs can be regenerated.')]]);
            }

            $before = $run->toArray();
            $run->lines()->delete();
            $this->rebuildLines($run);

            $run->update([
                'updated_by' => $actorId,
                'lock_version' => $run->lock_version + 1,
            ]);

            $run = $run->fresh($this->defaultRelations());
            $this->auditLogger->record($actorId, 'payroll_run.regenerate', 'payroll_run', $run->id, before: $before, after: $run->toArray());

            return $run;
        });
    }

    public function submit(string $id, ?int $actorId = null): PayrollRun
    {
        return $this->transition($id, 'draft', 'submitted', 'submitted_by', 'submitted_at', 'payroll_run.submit', $actorId);
    }

    public function approve(string $id, ?int $actorId = null): PayrollRun
    {
        return DB::transaction(function () use ($id, $actorId): PayrollRun {
            /** @var PayrollRun $run */
            $run = PayrollRun::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($run->status === 'approved') {
                return $run->fresh($this->defaultRelations());
            }

            if ($run->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Payroll run must be submitted before approval.')]]);
            }

            $this->assertRunHasPayableLines($run);

            $number = $run->number;
            if (! $number) {
                $number = $this->numberAllocator->nextNumber('payroll.run', 'PAY', $run->payroll_date);
            }

            $before = $run->toArray();
            $run->update([
                'number' => $number,
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $run->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_run.approve', 'payroll_run', $run->id, before: $before, after: $run->fresh()->toArray());

            return $run->fresh($this->defaultRelations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): PayrollRun
    {
        return DB::transaction(function () use ($id, $actorId): PayrollRun {
            /** @var PayrollRun $run */
            $run = PayrollRun::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($run->status, ['draft', 'submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only unposted payroll runs can be cancelled.')]]);
            }

            $before = $run->toArray();
            $run->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $run->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_run.cancel', 'payroll_run', $run->id, before: $before, after: $run->fresh()->toArray());

            return $run->fresh($this->defaultRelations());
        });
    }

    public function post(string $id, ?int $actorId = null): PayrollRun
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Posting payroll requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($id, $actorId): PayrollRun {
            /** @var PayrollRun $run */
            $run = PayrollRun::query()
                ->with(['lines.components', 'period'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($run->status === 'posted') {
                return $run->fresh($this->defaultRelations());
            }

            if ($run->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved payroll runs can be posted.')]]);
            }

            $this->assertRunHasPayableLines($run);
            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $run->financial_period_id, (string) $run->payroll_date->format('Y-m-d'));

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $run->payroll_date,
                'financial_period_id' => $period->id,
                'branch_id' => $run->branch_id,
                'source_type' => 'payroll_run',
                'source_id' => $run->id,
                'description' => "Payroll run {$run->number}",
                'reference' => $run->reference,
                'currency' => $run->currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $journalLines = $this->buildJournalLines($run);
            $lineNo = 1;
            foreach ($journalLines as $line) {
                if ($line['debit_minor'] === 0 && $line['credit_minor'] === 0) {
                    continue;
                }

                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $line['account_id'],
                    'branch_id' => $line['branch_id'],
                    'memo' => $line['memo'],
                    'debit_minor' => $line['debit_minor'],
                    'credit_minor' => $line['credit_minor'],
                    'debit_txn_minor' => $line['debit_minor'],
                    'credit_txn_minor' => $line['credit_minor'],
                    'currency' => $run->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);
            $before = $run->toArray();
            $run->update([
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'posted_by' => $actorId,
                'posted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $run->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_run.post', 'payroll_run', $run->id, before: $before, after: $run->fresh()->toArray());

            return $run->fresh($this->defaultRelations());
        });
    }

    public function ensurePayrollPeriod(array $data, ?int $actorId = null): PayrollPeriod
    {
        $year = (int) ($data['year'] ?? 0);
        $month = (int) ($data['month'] ?? 0);
        $paymentDate = (string) ($data['payment_date'] ?? '');

        if ($year < 2000 || $year > 2100) {
            throw ValidationException::withMessages(['year' => [__('Payroll year must be between 2000 and 2100.')]]);
        }

        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages(['month' => [__('Payroll month must be between 1 and 12.')]]);
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $paymentDate = $paymentDate !== '' ? Carbon::parse($paymentDate)->toDateString() : $end->toDateString();
        $financialPeriod = $this->financialPeriodForDate($paymentDate);

        /** @var PayrollPeriod $period */
        $period = PayrollPeriod::query()->firstOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'id' => (string) Str::uuid(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'payment_date' => $paymentDate,
                'financial_period_id' => $financialPeriod->id,
                'status' => 'open',
                'lock_version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );

        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['payroll_period_id' => [__('Payroll period is locked.')]]);
        }

        if ($period->payment_date?->format('Y-m-d') !== $paymentDate || $period->financial_period_id !== $financialPeriod->id) {
            $period->update([
                'payment_date' => $paymentDate,
                'financial_period_id' => $financialPeriod->id,
                'updated_by' => $actorId,
                'lock_version' => $period->lock_version + 1,
            ]);
        }

        return $period->fresh(['financialPeriod']);
    }

    private function rebuildLines(PayrollRun $run): void
    {
        $run = $run->fresh(['period']);
        $period = $run->period;
        $employees = Employee::query()
            ->with(['componentAssignments.component'])
            ->where('status', 'active')
            ->where('currency', $run->currency)
            ->where('hire_date', '<=', $period->end_date)
            ->where(function ($query) use ($period): void {
                $query->whereNull('termination_date')->orWhere('termination_date', '>=', $period->start_date);
            })
            ->when($run->branch_id !== null, fn ($query) => $query->where('branch_id', $run->branch_id))
            ->orderBy('code')
            ->lockForUpdate()
            ->get();

        $lineNo = 1;
        $grossTotal = 0;
        $deductionsTotal = 0;
        $netTotal = 0;

        foreach ($employees as $employee) {
            $baseSalary = (int) $employee->base_salary_minor;
            $earnings = $baseSalary;
            $deductions = 0;
            $snapshots = [];

            if ($baseSalary > 0) {
                $snapshots[] = [
                    'payroll_component_id' => null,
                    'expense_account_id' => null,
                    'liability_account_id' => null,
                    'code' => 'BASE_SALARY',
                    'name' => ['en' => 'Base Salary', 'ar' => 'الراتب الأساسي'],
                    'type' => 'earning',
                    'amount_minor' => $baseSalary,
                ];
            }

            foreach ($employee->componentAssignments as $assignment) {
                $component = $assignment->component;
                if (! $component || ! $component->is_active || ! $assignment->is_active) {
                    continue;
                }

                if (! $this->assignmentEffective($assignment->effective_from?->format('Y-m-d'), $assignment->effective_to?->format('Y-m-d'), $period->start_date->format('Y-m-d'), $period->end_date->format('Y-m-d'))) {
                    continue;
                }

                $amount = $this->componentAmount($component, $baseSalary, $assignment->amount_minor, $assignment->rate_bps);
                if ($amount <= 0) {
                    continue;
                }

                if ($component->type === 'earning') {
                    $earnings += $amount;
                } else {
                    $deductions += $amount;
                }

                $snapshots[] = [
                    'payroll_component_id' => $component->id,
                    'expense_account_id' => $component->expense_account_id,
                    'liability_account_id' => $component->liability_account_id,
                    'code' => $component->code,
                    'name' => $component->getTranslations('name'),
                    'type' => $component->type,
                    'amount_minor' => $amount,
                ];
            }

            if ($earnings <= 0 && $deductions <= 0) {
                continue;
            }

            if ($deductions > $earnings) {
                throw ValidationException::withMessages(['deductions_minor' => [__('Deductions exceed gross pay for employee :employee.', ['employee' => $employee->code])]]);
            }

            /** @var PayrollRunLine $line */
            $line = $run->lines()->create([
                'employee_id' => $employee->id,
                'line_no' => $lineNo++,
                'branch_id' => $employee->branch_id,
                'currency' => $run->currency,
                'base_salary_minor' => $baseSalary,
                'earnings_minor' => $earnings,
                'deductions_minor' => $deductions,
                'gross_minor' => $earnings,
                'net_minor' => $earnings - $deductions,
            ]);

            foreach ($snapshots as $snapshot) {
                $line->components()->create($snapshot);
            }

            $grossTotal += $earnings;
            $deductionsTotal += $deductions;
            $netTotal += $earnings - $deductions;
        }

        if ($lineNo === 1) {
            throw ValidationException::withMessages(['employees' => [__('No active employees matched this payroll run.')]]);
        }

        $run->update([
            'employee_count' => $lineNo - 1,
            'gross_minor' => $grossTotal,
            'deductions_minor' => $deductionsTotal,
            'net_minor' => $netTotal,
        ]);
    }

    /**
     * @return list<array{account_id: string, branch_id: ?string, memo: string, debit_minor: int, credit_minor: int}>
     */
    private function buildJournalLines(PayrollRun $run): array
    {
        $buckets = [];

        foreach ($run->lines as $line) {
            foreach ($line->components as $component) {
                if ((int) $component->amount_minor <= 0) {
                    continue;
                }

                if ($component->type === 'earning') {
                    $account = $component->expense_account_id
                        ? Account::query()->findOrFail($component->expense_account_id)
                        : $this->mappingService->getAccount('payroll_expense', $line->branch_id);
                    $this->assertAccountCurrency($account, $run->currency, 'Payroll expense account');
                    $this->bucket($buckets, $account->id, $line->branch_id, 'Payroll gross expense', (int) $component->amount_minor, 0);
                } else {
                    $account = $component->liability_account_id
                        ? Account::query()->findOrFail($component->liability_account_id)
                        : $this->mappingService->getAccount('payroll_deductions_payable', $line->branch_id);
                    $this->assertAccountCurrency($account, $run->currency, 'Payroll deductions payable account');
                    $this->bucket($buckets, $account->id, $line->branch_id, 'Payroll deductions payable', 0, (int) $component->amount_minor);
                }
            }

            if ((int) $line->net_minor > 0) {
                $payable = $this->mappingService->getAccount('payroll_payable', $line->branch_id);
                $this->assertAccountCurrency($payable, $run->currency, 'Payroll payable account');
                $this->bucket($buckets, $payable->id, $line->branch_id, 'Payroll net payable', 0, (int) $line->net_minor);
            }
        }

        $lines = array_values($buckets);
        $debit = array_sum(array_column($lines, 'debit_minor'));
        $credit = array_sum(array_column($lines, 'credit_minor'));

        if ($debit <= 0 || $debit !== $credit) {
            throw ValidationException::withMessages(['payroll_run' => [__('Payroll posting is not balanced.')]]);
        }

        return $lines;
    }

    private function bucket(array &$buckets, string $accountId, ?string $branchId, string $memo, int $debitMinor, int $creditMinor): void
    {
        $key = $accountId.'|'.($branchId ?? '');
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'account_id' => $accountId,
                'branch_id' => $branchId,
                'memo' => $memo,
                'debit_minor' => 0,
                'credit_minor' => 0,
            ];
        }

        $buckets[$key]['debit_minor'] += $debitMinor;
        $buckets[$key]['credit_minor'] += $creditMinor;
    }

    private function transition(string $id, string $from, string $to, string $actorColumn, string $timestampColumn, string $auditAction, ?int $actorId): PayrollRun
    {
        return DB::transaction(function () use ($id, $from, $to, $actorColumn, $timestampColumn, $auditAction, $actorId): PayrollRun {
            /** @var PayrollRun $run */
            $run = PayrollRun::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($run->status === $to) {
                return $run->fresh($this->defaultRelations());
            }

            if ($run->status !== $from) {
                throw ValidationException::withMessages(['status' => [__('Payroll run must be :from before it can move to :to.', ['from' => $from, 'to' => $to])]]);
            }

            $this->assertRunHasPayableLines($run);
            $before = $run->toArray();
            $run->update([
                'status' => $to,
                $actorColumn => $actorId,
                $timestampColumn => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $run->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, $auditAction, 'payroll_run', $run->id, before: $before, after: $run->fresh()->toArray());

            return $run->fresh($this->defaultRelations());
        });
    }

    private function assertRunHasPayableLines(PayrollRun $run): void
    {
        if ($run->lines->isEmpty() || (int) $run->gross_minor <= 0 || (int) $run->net_minor < 0) {
            throw ValidationException::withMessages(['payroll_run' => [__('Payroll run has no payable lines.')]]);
        }
    }

    private function componentAmount(PayrollComponent $component, int $baseSalary, ?int $assignmentAmount, ?int $assignmentRateBps): int
    {
        if ($component->calculation_type === 'fixed') {
            return (int) ($assignmentAmount ?? $component->default_amount_minor ?? 0);
        }

        $rateBps = (int) ($assignmentRateBps ?? $component->rate_bps ?? 0);

        return intdiv(($baseSalary * $rateBps) + 5000, 10000);
    }

    private function assignmentEffective(?string $from, ?string $to, string $periodStart, string $periodEnd): bool
    {
        if ($from === null || $from > $periodEnd) {
            return false;
        }

        return $to === null || $to >= $periodStart;
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
            throw ValidationException::withMessages(['financial_period_id' => [__('No financial period covers date :date.', ['date' => $normalized])]]);
        }

        return $period;
    }

    private function normalizeBranchId(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        if ($stringValue === '') {
            return null;
        }

        if (! Str::isUuid($stringValue)) {
            throw ValidationException::withMessages(['branch_id' => [__('Invalid branch reference.')]]);
        }

        return $stringValue;
    }

    private function assertBranch(?string $branchId): void
    {
        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is inactive or missing.')]]);
        }
    }

    private function assertCurrency(string $currency): void
    {
        if (! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected currency is missing from the currency registry.')]]);
        }
    }

    private function assertRunType(string $runType): void
    {
        if (! in_array($runType, self::RUN_TYPES, true)) {
            throw ValidationException::withMessages(['run_type' => [__('Invalid payroll run type.')]]);
        }
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== null && $account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency must match payroll currency.', ['label' => $label])]]);
        }
    }

    private function defaultRelations(): array
    {
        return ['period.financialPeriod', 'branch', 'journalEntry', 'lines.employee.branch', 'lines.components'];
    }
}
