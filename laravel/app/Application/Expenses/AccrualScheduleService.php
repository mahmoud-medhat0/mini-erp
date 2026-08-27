<?php

namespace App\Application\Expenses;

use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\AccrualEntry;
use App\Models\AccrualSchedule;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccrualScheduleService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): AccrualSchedule
    {
        return DB::transaction(function () use ($data, $actorId): AccrualSchedule {
            $payload = $this->validatedPayload($data);
            $period = $this->periodForDate($payload['schedule_date']);

            /** @var AccrualSchedule $schedule */
            $schedule = AccrualSchedule::query()->create([
                ...$payload,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'accrued_minor' => 0,
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->rebuildEntries($schedule);
            $schedule->load($this->defaultRelations());

            $this->auditLogger->record($actorId, 'accrual_schedule.create', 'accrual_schedule', $schedule->id, after: $schedule->toArray());

            return $schedule;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): AccrualSchedule
    {
        return DB::transaction(function () use ($id, $data, $actorId): AccrualSchedule {
            /** @var AccrualSchedule $schedule */
            $schedule = AccrualSchedule::query()->with('entries')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft accrual schedules can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $schedule->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $payload = $this->validatedPayload([
                'schedule_date' => $data['schedule_date'] ?? $schedule->schedule_date?->format('Y-m-d'),
                'start_date' => $data['start_date'] ?? $schedule->start_date?->format('Y-m-d'),
                'months' => $data['months'] ?? $schedule->months,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $schedule->branch_id,
                'expense_category_id' => array_key_exists('expense_category_id', $data) ? $data['expense_category_id'] : $schedule->expense_category_id,
                'expense_account_id' => $data['expense_account_id'] ?? $schedule->expense_account_id,
                'accrued_liability_account_id' => $data['accrued_liability_account_id'] ?? $schedule->accrued_liability_account_id,
                'currency' => $data['currency'] ?? $schedule->currency,
                'fx_rate_e6' => $data['fx_rate_e6'] ?? $schedule->fx_rate_e6,
                'total_minor' => $data['total_minor'] ?? $schedule->total_minor,
                'reference' => array_key_exists('reference', $data) ? $data['reference'] : $schedule->reference,
                'description' => array_key_exists('description', $data) ? $data['description'] : $schedule->description,
            ]);
            $period = $this->periodForDate($payload['schedule_date']);
            $before = $schedule->toArray();

            $schedule->update([
                ...$payload,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'updated_by' => $actorId,
                'lock_version' => $schedule->lock_version + 1,
            ]);

            $schedule->entries()->delete();
            $this->rebuildEntries($schedule->fresh());

            $schedule = $schedule->fresh($this->defaultRelations());
            $this->auditLogger->record($actorId, 'accrual_schedule.update', 'accrual_schedule', $schedule->id, before: $before, after: $schedule->toArray());

            return $schedule;
        });
    }

    public function submit(string $id, ?int $actorId = null): AccrualSchedule
    {
        return $this->transition($id, 'draft', 'submitted', 'submitted_by', 'submitted_at', 'accrual_schedule.submit', $actorId);
    }

    public function approve(string $id, ?int $actorId = null): AccrualSchedule
    {
        return DB::transaction(function () use ($id, $actorId): AccrualSchedule {
            /** @var AccrualSchedule $schedule */
            $schedule = AccrualSchedule::query()->with('entries')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->status === 'approved') {
                return $schedule->fresh($this->defaultRelations());
            }

            if ($schedule->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Accrual schedule must be submitted before approval.')]]);
            }

            if ($schedule->entries->isEmpty()) {
                throw ValidationException::withMessages(['entries' => [__('Accrual schedule requires entry rows.')]]);
            }

            $number = $schedule->number;
            if (! $number) {
                $year = Carbon::parse($schedule->schedule_date)->format('Y');
                $sequence = $this->numberAllocator->nextValue('expenses.accrual');
                $number = 'ACCR-'.$year.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            }

            $before = $schedule->toArray();
            $schedule->update([
                'number' => $number,
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $schedule->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'accrual_schedule.approve', 'accrual_schedule', $schedule->id, before: $before, after: $schedule->fresh()->toArray());

            return $schedule->fresh($this->defaultRelations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): AccrualSchedule
    {
        return DB::transaction(function () use ($id, $actorId): AccrualSchedule {
            /** @var AccrualSchedule $schedule */
            $schedule = AccrualSchedule::query()->with('entries')->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($schedule->status, ['draft', 'submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only unposted accrual schedules can be cancelled.')]]);
            }

            if ($schedule->entries()->where('status', 'posted')->exists()) {
                throw ValidationException::withMessages(['entries' => [__('Accrual schedules with posted entries cannot be cancelled.')]]);
            }

            $before = $schedule->toArray();
            $schedule->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $schedule->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'accrual_schedule.cancel', 'accrual_schedule', $schedule->id, before: $before, after: $schedule->fresh()->toArray());

            return $schedule->fresh($this->defaultRelations());
        });
    }

    public function postEntry(string $scheduleId, string $entryId, ?int $actorId = null): AccrualSchedule
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Posting accrual entries requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($scheduleId, $entryId, $actorId): AccrualSchedule {
            /** @var AccrualSchedule $schedule */
            $schedule = AccrualSchedule::query()
                ->with(['expenseAccount', 'accruedLiabilityAccount'])
                ->whereKey($scheduleId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($schedule->status, ['approved', 'active'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only approved or active accrual schedules can be posted.')]]);
            }

            /** @var AccrualEntry $entry */
            $entry = AccrualEntry::query()
                ->where('accrual_schedule_id', $schedule->id)
                ->whereKey($entryId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status === 'posted') {
                return $schedule->fresh($this->defaultRelations());
            }

            if ($entry->status !== 'pending') {
                throw ValidationException::withMessages(['entry' => [__('Only pending accrual entries can be posted.')]]);
            }

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $entry->financial_period_id, (string) $entry->accrual_date->format('Y-m-d'));
            $this->assertAccountCurrency($schedule->expenseAccount, $schedule->currency, 'Expense account');
            $this->assertAccountCurrency($schedule->accruedLiabilityAccount, $schedule->currency, 'Accrued liability account');

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $entry->accrual_date,
                'financial_period_id' => $period->id,
                'branch_id' => $schedule->branch_id,
                'source_type' => 'accrual_entry',
                'source_id' => $entry->id,
                'description' => "Accrual entry {$schedule->number}",
                'reference' => $schedule->reference,
                'currency' => $schedule->currency,
                'fx_rate_e6' => $schedule->fx_rate_e6,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $schedule->expense_account_id,
                'branch_id' => $schedule->branch_id,
                'memo' => "Accrued expense {$schedule->number}",
                'debit_minor' => $entry->amount_minor,
                'credit_minor' => 0,
                'debit_txn_minor' => $entry->amount_minor,
                'credit_txn_minor' => 0,
                'currency' => $schedule->currency,
                'fx_rate_e6' => $schedule->fx_rate_e6,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $schedule->accrued_liability_account_id,
                'branch_id' => $schedule->branch_id,
                'memo' => "Accrued liability {$schedule->number}",
                'debit_minor' => 0,
                'credit_minor' => $entry->amount_minor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $entry->amount_minor,
                'currency' => $schedule->currency,
                'fx_rate_e6' => $schedule->fx_rate_e6,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);
            $entry->update([
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'posted_by' => $actorId,
                'posted_at' => Carbon::now(),
            ]);

            $accruedMinor = (int) $schedule->entries()->where('status', 'posted')->sum('amount_minor');
            $before = $schedule->toArray();
            $schedule->update([
                'accrued_minor' => $accruedMinor,
                'status' => $accruedMinor >= (int) $schedule->total_minor ? 'completed' : 'active',
                'updated_by' => $actorId,
                'lock_version' => $schedule->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'accrual_entry.post', 'accrual_entry', $entry->id, before: $before, after: $schedule->fresh()->toArray());

            return $schedule->fresh($this->defaultRelations());
        });
    }

    private function transition(string $id, string $from, string $to, string $actorColumn, string $timestampColumn, string $auditAction, ?int $actorId): AccrualSchedule
    {
        return DB::transaction(function () use ($id, $from, $to, $actorColumn, $timestampColumn, $auditAction, $actorId): AccrualSchedule {
            /** @var AccrualSchedule $schedule */
            $schedule = AccrualSchedule::query()->with('entries')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->status === $to) {
                return $schedule->fresh($this->defaultRelations());
            }

            if ($schedule->status !== $from) {
                throw ValidationException::withMessages(['status' => [__('Accrual schedule must be :from before it can move to :to.', [
                    'from' => $from,
                    'to' => $to,
                ])]]);
            }

            if ($schedule->entries->isEmpty()) {
                throw ValidationException::withMessages(['entries' => [__('Accrual schedule requires entry rows.')]]);
            }

            $before = $schedule->toArray();
            $schedule->update([
                'status' => $to,
                $actorColumn => $actorId,
                $timestampColumn => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $schedule->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, $auditAction, 'accrual_schedule', $schedule->id, before: $before, after: $schedule->fresh()->toArray());

            return $schedule->fresh($this->defaultRelations());
        });
    }

    private function validatedPayload(array $data): array
    {
        $scheduleDate = (string) ($data['schedule_date'] ?? '');
        $startDate = (string) ($data['start_date'] ?? '');
        $months = (int) ($data['months'] ?? 0);
        $totalMinor = (int) ($data['total_minor'] ?? 0);
        $currency = CurrencyInput::required($data['currency'] ?? null);
        $fxRateE6 = (int) ($data['fx_rate_e6'] ?? 1000000);
        $branchId = $this->normalizeNullableUuid($data['branch_id'] ?? null);
        $categoryId = $this->normalizeNullableUuid($data['expense_category_id'] ?? null);

        if ($scheduleDate === '') {
            throw ValidationException::withMessages(['schedule_date' => [__('Schedule date is required.')]]);
        }

        if ($startDate === '') {
            throw ValidationException::withMessages(['start_date' => [__('Start date is required.')]]);
        }

        if ($months < 1 || $months > 120) {
            throw ValidationException::withMessages(['months' => [__('Months must be between 1 and 120.')]]);
        }

        if ($totalMinor < 1) {
            throw ValidationException::withMessages(['total_minor' => [__('Total amount must be greater than zero.')]]);
        }

        if ($fxRateE6 !== 1000000) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
        }

        if (! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected currency is missing from the currency registry.')]]);
        }

        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is inactive or missing.')]]);
        }

        if ($categoryId !== null && ! ExpenseCategory::query()->whereKey($categoryId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['expense_category_id' => [__('Selected expense category is inactive or missing.')]]);
        }

        /** @var Account $expenseAccount */
        $expenseAccount = Account::query()->findOrFail($data['expense_account_id'] ?? null);
        $this->assertDebitExpense($expenseAccount, 'expense_account_id');

        /** @var Account $liabilityAccount */
        $liabilityAccount = Account::query()->findOrFail($data['accrued_liability_account_id'] ?? null);
        $this->assertCreditLiability($liabilityAccount, 'accrued_liability_account_id');
        $this->assertAccountCurrency($expenseAccount, $currency, 'Expense account');
        $this->assertAccountCurrency($liabilityAccount, $currency, 'Accrued liability account');

        return [
            'schedule_date' => Carbon::parse($scheduleDate)->toDateString(),
            'start_date' => Carbon::parse($startDate)->toDateString(),
            'months' => $months,
            'branch_id' => $branchId,
            'expense_category_id' => $categoryId,
            'expense_account_id' => $expenseAccount->id,
            'accrued_liability_account_id' => $liabilityAccount->id,
            'currency' => $currency,
            'fx_rate_e6' => $fxRateE6,
            'total_minor' => $totalMinor,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    private function rebuildEntries(AccrualSchedule $schedule): void
    {
        $amounts = $this->splitAmount((int) $schedule->total_minor, (int) $schedule->months);
        $firstDate = Carbon::parse($schedule->start_date)->startOfMonth();

        foreach ($amounts as $index => $amountMinor) {
            $accrualDate = $firstDate->copy()->addMonthsNoOverflow($index)->endOfMonth()->toDateString();
            $period = $this->periodForDate($accrualDate);

            $schedule->entries()->create([
                'financial_period_id' => $period->id,
                'accrual_date' => $accrualDate,
                'amount_minor' => $amountMinor,
                'status' => 'pending',
            ]);
        }
    }

    private function splitAmount(int $totalMinor, int $months): array
    {
        $base = intdiv($totalMinor, $months);
        $remainder = $totalMinor % $months;
        $amounts = [];

        for ($i = 0; $i < $months; $i++) {
            $amounts[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $amounts;
    }

    private function periodForDate(string $date): FinancialPeriod
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

    private function assertDebitExpense(Account $account, string $field): void
    {
        if (! $account->is_active || $account->type !== 'expense' || $account->nature !== 'debit' || $account->is_control) {
            throw ValidationException::withMessages([$field => [__('Expense account must be an active non-control debit expense account.')]]);
        }
    }

    private function assertCreditLiability(Account $account, string $field): void
    {
        if (! $account->is_active || $account->type !== 'liability' || $account->nature !== 'credit') {
            throw ValidationException::withMessages([$field => [__('Accrued liability account must be an active credit liability account.')]]);
        }
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== null && $account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency must match the schedule currency.', ['label' => $label])]]);
        }
    }

    private function normalizeNullableUuid(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }

    private function defaultRelations(): array
    {
        return ['branch', 'category', 'expenseAccount', 'accruedLiabilityAccount', 'entries.period', 'entries.journalEntry'];
    }
}
