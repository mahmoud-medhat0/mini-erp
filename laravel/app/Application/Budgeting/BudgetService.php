<?php

namespace App\Application\Budgeting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public const ALLOWED_STATUSES = [
        'draft',
        'submitted',
        'approved',
        'active',
        'archived',
        'cancelled',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($data, $actorId): Budget {
            $code = strtoupper(trim((string) $data['code']));
            $this->assertUniqueCode($code);

            $fiscalYearId = (string) $data['fiscal_year_id'];
            $this->assertFiscalYearExists($fiscalYearId);

            $versionCode = strtoupper(trim((string) $data['version_code']));
            $this->assertUniqueVersionCode($fiscalYearId, $versionCode);

            $defaultCurrency = strtoupper(trim((string) ($data['default_currency'] ?? '')));
            if ($defaultCurrency === '') {
                throw ValidationException::withMessages([
                    'default_currency' => [__('Default currency is required.')],
                ]);
            }
            $this->assertCurrencyExists($defaultCurrency);

            $status = (string) ($data['status'] ?? 'draft');
            if ($status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('New budget must start in draft status.')],
                ]);
            }

            $rawLines = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : [];
            $validatedLines = $this->validateAndNormalizeLines($fiscalYearId, $rawLines, $defaultCurrency);

            /** @var Budget $budget */
            $budget = Budget::query()->create([
                'fiscal_year_id' => $fiscalYearId,
                'code' => $code,
                'version_code' => $versionCode,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'default_currency' => $defaultCurrency,
                'lock_version' => 1,
                'created_by' => $this->actorId($actorId),
                'updated_by' => $this->actorId($actorId),
            ]);

            foreach ($validatedLines as $lineData) {
                BudgetLine::query()->create([
                    'budget_id' => $budget->id,
                    'financial_period_id' => $lineData['financial_period_id'],
                    'account_id' => $lineData['account_id'],
                    'project_id' => $lineData['project_id'],
                    'cost_center_id' => $lineData['cost_center_id'],
                    'currency' => $lineData['currency'],
                    'amount_minor' => $lineData['amount_minor'],
                    'notes' => $lineData['notes'],
                    'created_by' => $this->actorId($actorId),
                    'updated_by' => $this->actorId($actorId),
                ]);
            }

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.create',
                'budget',
                (string) $budget->id,
                after: $budget->fresh(['fiscalYear', 'lines'])->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines.financialPeriod', 'lines.account', 'lines.project', 'lines.costCenter', 'lines.currencyRef']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($id, $data, $actorId): Budget {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($budget->status !== 'draft') {
                throw ValidationException::withMessages([
                    'budget' => [__('Only draft budgets can be modified.')],
                ]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $budget->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if (isset($data['code'])) {
                $code = strtoupper(trim((string) $data['code']));
                if ($code !== $budget->code) {
                    $this->assertUniqueCode($code, (string) $budget->id);
                }
            }

            $fiscalYearId = (string) ($data['fiscal_year_id'] ?? $budget->fiscal_year_id);
            if ($fiscalYearId !== $budget->fiscal_year_id) {
                throw ValidationException::withMessages([
                    'fiscal_year_id' => [__('Budget fiscal year cannot be changed after creation.')],
                ]);
            }

            if (isset($data['version_code']) || $fiscalYearId !== $budget->fiscal_year_id) {
                $versionCode = strtoupper(trim((string) ($data['version_code'] ?? $budget->version_code)));
                if ($versionCode !== $budget->version_code || $fiscalYearId !== $budget->fiscal_year_id) {
                    $this->assertUniqueVersionCode($fiscalYearId, $versionCode, (string) $budget->id);
                }
            }

            if (isset($data['default_currency'])) {
                $defaultCurrency = strtoupper(trim((string) $data['default_currency']));
                $this->assertCurrencyExists($defaultCurrency);
            } else {
                $defaultCurrency = $budget->default_currency;
            }

            $before = $budget->fresh(['fiscalYear', 'lines'])->toArray();

            $updates = [];
            foreach (['code', 'version_code', 'name', 'description', 'default_currency'] as $field) {
                if (array_key_exists($field, $data)) {
                    if (in_array($field, ['code', 'version_code', 'default_currency'], true)) {
                        $updates[$field] = strtoupper(trim((string) $data[$field]));
                    } else {
                        $updates[$field] = $data[$field];
                    }
                }
            }

            if (array_key_exists('fiscal_year_id', $data)) {
                $updates['fiscal_year_id'] = $fiscalYearId;
            }

            $updates['updated_by'] = $this->actorId($actorId);
            $updates['lock_version'] = $budget->lock_version + 1;
            $budget->update($updates);

            if (array_key_exists('lines', $data) && is_array($data['lines'])) {
                $validatedLines = $this->validateAndNormalizeLines($fiscalYearId, $data['lines'], $defaultCurrency);

                $budget->lines()->delete();

                foreach ($validatedLines as $lineData) {
                    BudgetLine::query()->create([
                        'budget_id' => $budget->id,
                        'financial_period_id' => $lineData['financial_period_id'],
                        'account_id' => $lineData['account_id'],
                        'project_id' => $lineData['project_id'],
                        'cost_center_id' => $lineData['cost_center_id'],
                        'currency' => $lineData['currency'],
                        'amount_minor' => $lineData['amount_minor'],
                        'notes' => $lineData['notes'],
                        'created_by' => $this->actorId($actorId),
                        'updated_by' => $this->actorId($actorId),
                    ]);
                }
            }

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.update',
                'budget',
                (string) $budget->id,
                before: $before,
                after: $budget->fresh(['fiscalYear', 'lines'])->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines.financialPeriod', 'lines.account', 'lines.project', 'lines.costCenter', 'lines.currencyRef']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function replaceLines(string $id, array $lines, ?int $lockVersion = null, int|string|null $actorId = null): Budget
    {
        return $this->update($id, [
            'lines' => $lines,
            'lock_version' => $lockVersion,
        ], $actorId);
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($budget->status !== 'draft') {
                throw ValidationException::withMessages([
                    'budget' => [__('Only draft budgets can be deleted.')],
                ]);
            }

            $before = $budget->fresh(['lines'])->toArray();

            $budget->lines()->delete();
            $budget->delete();

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.delete',
                'budget',
                $id,
                before: $before
            );
        });
    }

    public function submit(string $id, ?int $lockVersion = null, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($id, $lockVersion, $actorId): Budget {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($lockVersion !== null && (int) $lockVersion !== (int) $budget->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if ($budget->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('Budget cannot be submitted from [:status] status.', ['status' => $budget->status])],
                ]);
            }

            $this->assertBudgetHasPositiveLineTotal($budget);

            $before = $budget->toArray();

            $budget->update([
                'status' => 'submitted',
                'submitted_by' => $this->actorId($actorId),
                'submitted_at' => now(),
                'updated_by' => $this->actorId($actorId),
                'lock_version' => $budget->lock_version + 1,
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.submit',
                'budget',
                (string) $budget->id,
                before: $before,
                after: $budget->fresh()->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines']);
        });
    }

    public function approve(string $id, ?int $lockVersion = null, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($id, $lockVersion, $actorId): Budget {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($lockVersion !== null && (int) $lockVersion !== (int) $budget->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if ($budget->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => [__('Budget cannot be approved from [:status] status.', ['status' => $budget->status])],
                ]);
            }

            $this->assertBudgetHasPositiveLineTotal($budget);

            $before = $budget->toArray();

            $budget->update([
                'status' => 'approved',
                'approved_by' => $this->actorId($actorId),
                'approved_at' => now(),
                'updated_by' => $this->actorId($actorId),
                'lock_version' => $budget->lock_version + 1,
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.approve',
                'budget',
                (string) $budget->id,
                before: $before,
                after: $budget->fresh()->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines']);
        });
    }

    public function activate(string $id, ?int $lockVersion = null, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($id, $lockVersion, $actorId): Budget {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($lockVersion !== null && (int) $lockVersion !== (int) $budget->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if ($budget->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => [__('Budget cannot be activated from [:status] status.', ['status' => $budget->status])],
                ]);
            }

            FiscalYear::query()->where('id', $budget->fiscal_year_id)->lockForUpdate()->firstOrFail();
            $this->assertBudgetHasPositiveLineTotal($budget);

            // Only one active budget is allowed per fiscal year. Archive any other active budget for the same fiscal year.
            $otherActiveBudgets = Budget::query()
                ->where('fiscal_year_id', $budget->fiscal_year_id)
                ->where('status', 'active')
                ->where('id', '!=', $budget->id)
                ->lockForUpdate()
                ->get();

            foreach ($otherActiveBudgets as $otherBudget) {
                $beforeOther = $otherBudget->toArray();
                $otherBudget->update([
                    'status' => 'archived',
                    'archived_by' => $this->actorId($actorId),
                    'archived_at' => now(),
                    'updated_by' => $this->actorId($actorId),
                    'lock_version' => $otherBudget->lock_version + 1,
                ]);

                $this->auditLogger->record(
                    $this->actorId($actorId),
                    'budget.archive',
                    'budget',
                    (string) $otherBudget->id,
                    before: $beforeOther,
                    after: $otherBudget->fresh()->toArray()
                );
            }

            $before = $budget->toArray();

            $budget->update([
                'status' => 'active',
                'activated_by' => $this->actorId($actorId),
                'activated_at' => now(),
                'updated_by' => $this->actorId($actorId),
                'lock_version' => $budget->lock_version + 1,
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.activate',
                'budget',
                (string) $budget->id,
                before: $before,
                after: $budget->fresh()->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines']);
        });
    }

    public function archive(string $id, ?int $lockVersion = null, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($id, $lockVersion, $actorId): Budget {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($lockVersion !== null && (int) $lockVersion !== (int) $budget->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if (! in_array($budget->status, ['approved', 'active'], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('Budget cannot be archived from [:status] status.', ['status' => $budget->status])],
                ]);
            }

            $before = $budget->toArray();

            $budget->update([
                'status' => 'archived',
                'archived_by' => $this->actorId($actorId),
                'archived_at' => now(),
                'updated_by' => $this->actorId($actorId),
                'lock_version' => $budget->lock_version + 1,
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.archive',
                'budget',
                (string) $budget->id,
                before: $before,
                after: $budget->fresh()->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines']);
        });
    }

    public function cancel(string $id, ?int $lockVersion = null, int|string|null $actorId = null): Budget
    {
        return DB::transaction(function () use ($id, $lockVersion, $actorId): Budget {
            /** @var Budget $budget */
            $budget = Budget::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($lockVersion !== null && (int) $lockVersion !== (int) $budget->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if (! in_array($budget->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('Budget cannot be cancelled from [:status] status.', ['status' => $budget->status])],
                ]);
            }

            $before = $budget->toArray();

            $budget->update([
                'status' => 'cancelled',
                'cancelled_by' => $this->actorId($actorId),
                'cancelled_at' => now(),
                'updated_by' => $this->actorId($actorId),
                'lock_version' => $budget->lock_version + 1,
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'budget.cancel',
                'budget',
                (string) $budget->id,
                before: $before,
                after: $budget->fresh()->toArray()
            );

            return $budget->fresh(['fiscalYear', 'lines']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function validateAndNormalizeLines(string $fiscalYearId, array $lines, string $defaultCurrency): array
    {
        $normalized = [];
        $seenTuples = [];

        $validPeriodIds = FinancialPeriod::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->pluck('id')
            ->flip()
            ->all();

        $validCurrencies = Currency::query()->pluck('code')->flip()->all();

        foreach ($lines as $index => $line) {
            $periodId = (string) ($line['financial_period_id'] ?? '');
            if (! isset($validPeriodIds[$periodId])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.financial_period_id" => [__('The financial period must belong to the budget fiscal year.')],
                ]);
            }

            $accountId = (string) ($line['account_id'] ?? '');
            $account = Account::query()->find($accountId);
            if (! $account || ! $account->is_active) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => [__('Selected account must exist and be active.')],
                ]);
            }

            $projectId = ! empty($line['project_id']) ? (string) $line['project_id'] : null;
            if ($projectId !== null) {
                $project = Project::query()->find($projectId);
                if (! $project || ! $project->is_active) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.project_id" => [__('Selected project must exist and be active.')],
                    ]);
                }
            }

            $costCenterId = ! empty($line['cost_center_id']) ? (string) $line['cost_center_id'] : null;
            if ($costCenterId !== null) {
                $costCenter = CostCenter::query()->find($costCenterId);
                if (! $costCenter || ! $costCenter->is_active) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.cost_center_id" => [__('Selected cost center must exist and be active.')],
                    ]);
                }
            }

            $currency = strtoupper(trim((string) ($line['currency'] ?? $defaultCurrency)));
            if (! isset($validCurrencies[$currency])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.currency" => [__('Invalid currency code [:currency].', ['currency' => $currency])],
                ]);
            }

            $amountMinor = (int) ($line['amount_minor'] ?? 0);
            if ($amountMinor < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount_minor" => [__('Line amount must be greater than or equal to zero.')],
                ]);
            }

            $tupleKey = "{$periodId}|{$accountId}|".($projectId ?? 'null').'|'.($costCenterId ?? 'null')."|{$currency}";
            if (isset($seenTuples[$tupleKey])) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [__('Duplicate budget line detected for the same period, account, project, cost center, and currency.')],
                ]);
            }
            $seenTuples[$tupleKey] = true;

            $normalized[] = [
                'financial_period_id' => $periodId,
                'account_id' => $accountId,
                'project_id' => $projectId,
                'cost_center_id' => $costCenterId,
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'notes' => ! empty($line['notes']) ? (string) $line['notes'] : null,
            ];
        }

        return $normalized;
    }

    private function assertUniqueCode(string $code, ?string $ignoreId = null): void
    {
        $exists = Budget::query()
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => [__('Budget code [:code] already exists.', ['code' => $code])],
            ]);
        }
    }

    private function assertUniqueVersionCode(string $fiscalYearId, string $versionCode, ?string $ignoreId = null): void
    {
        $exists = Budget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('version_code', $versionCode)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'version_code' => [__('A budget with version code [:version] already exists for this fiscal year.', ['version' => $versionCode])],
            ]);
        }
    }

    private function assertFiscalYearExists(string $fiscalYearId): void
    {
        if (! FiscalYear::query()->where('id', $fiscalYearId)->exists()) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => [__('Selected fiscal year does not exist.')],
            ]);
        }
    }

    private function assertCurrencyExists(string $currency): void
    {
        if (! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages([
                'default_currency' => [__('Invalid currency code [:currency].', ['currency' => $currency])],
            ]);
        }
    }

    private function assertBudgetHasPositiveLineTotal(Budget $budget): void
    {
        $linesCount = $budget->lines()->count();
        if ($linesCount === 0) {
            throw ValidationException::withMessages([
                'lines' => [__('Budget lifecycle action requires at least one budget line.')],
            ]);
        }

        $totalAmount = (int) $budget->lines()->sum('amount_minor');
        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'lines' => [__('Budget lifecycle action requires total budget amount across lines to be greater than zero.')],
            ]);
        }
    }

    private function actorId(int|string|null $actorId): ?int
    {
        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
