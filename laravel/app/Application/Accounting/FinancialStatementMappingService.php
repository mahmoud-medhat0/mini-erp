<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\FinancialStatementLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialStatementMappingService
{
    public function __construct(
        private AuditLogger $auditLogger
    ) {}

    /**
     * @return array{
     *     lines: Collection<int, FinancialStatementLine>,
     *     unmapped_accounts: Collection<int, Account>
     * }
     */
    public function getMappingData(): array
    {
        $lines = FinancialStatementLine::query()
            ->with(['accounts.accountType', 'accounts.group'])
            ->orderBy('statement_type')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $unmappedAccounts = Account::query()
            ->with(['accountType', 'group'])
            ->whereNull('financial_statement_line_id')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return [
            'lines' => $lines,
            'unmapped_accounts' => $unmappedAccounts,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStatementLine(array $data, int $actorId): FinancialStatementLine
    {
        $code = Str::upper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            throw ValidationException::withMessages(['code' => ['Statement line code is required.']]);
        }

        if (FinancialStatementLine::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => ['Statement line code must be unique.']]);
        }

        $statementType = (string) ($data['statement_type'] ?? '');
        if (! in_array($statementType, ['balance_sheet', 'income_statement'], true)) {
            throw ValidationException::withMessages(['statement_type' => ['Statement type must be balance_sheet or income_statement.']]);
        }

        $sectionCode = trim((string) ($data['section_code'] ?? ''));
        if ($sectionCode === '') {
            throw ValidationException::withMessages(['section_code' => ['Section code is required.']]);
        }

        $normalBalance = (string) ($data['normal_balance'] ?? '');
        if (! in_array($normalBalance, ['debit', 'credit'], true)) {
            throw ValidationException::withMessages(['normal_balance' => ['Normal balance must be debit or credit.']]);
        }

        $name = $data['name'] ?? [];
        if (is_string($name)) {
            $name = ['en' => $name, 'ar' => $name];
        }

        $enName = is_array($name) ? trim((string) ($name['en'] ?? $name['ar'] ?? '')) : '';
        if ($enName === '') {
            throw ValidationException::withMessages(['name' => ['Name is required in at least one locale.']]);
        }

        return DB::transaction(function () use ($code, $statementType, $sectionCode, $normalBalance, $name, $data, $actorId): FinancialStatementLine {
            /** @var FinancialStatementLine $line */
            $line = FinancialStatementLine::query()->create([
                'code' => $code,
                'statement_type' => $statementType,
                'section_code' => $sectionCode,
                'name' => $name,
                'normal_balance' => $normalBalance,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_system' => false,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'financial_statement_line.create',
                entityType: 'financial_statement_line',
                entityId: $line->id,
                before: null,
                after: $line->toArray()
            );

            return $line;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStatementLine(string $id, array $data, int $actorId): FinancialStatementLine
    {
        return DB::transaction(function () use ($id, $data, $actorId): FinancialStatementLine {
            /** @var FinancialStatementLine $line */
            $line = FinancialStatementLine::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            $before = $line->toArray();

            if ($line->is_system) {
                if (isset($data['code']) && Str::upper(trim((string) $data['code'])) !== $line->code) {
                    throw ValidationException::withMessages(['code' => ['System statement line code cannot be changed.']]);
                }
                if (isset($data['statement_type']) && (string) $data['statement_type'] !== $line->statement_type) {
                    throw ValidationException::withMessages(['statement_type' => ['System statement line statement type cannot be changed.']]);
                }
            }

            if (isset($data['code'])) {
                $newCode = Str::upper(trim((string) $data['code']));
                if ($newCode !== $line->code && FinancialStatementLine::query()->where('code', $newCode)->where('id', '!=', $id)->exists()) {
                    throw ValidationException::withMessages(['code' => ['Statement line code must be unique.']]);
                }
                $line->code = $newCode;
            }

            if (isset($data['statement_type'])) {
                $newType = (string) $data['statement_type'];
                if (! in_array($newType, ['balance_sheet', 'income_statement'], true)) {
                    throw ValidationException::withMessages(['statement_type' => ['Statement type must be balance_sheet or income_statement.']]);
                }
                if ($newType !== $line->statement_type && $line->accounts()->count() > 0) {
                    throw ValidationException::withMessages(['statement_type' => ['Cannot change statement type when line has assigned accounts.']]);
                }
                $line->statement_type = $newType;
            }

            if (isset($data['section_code'])) {
                $line->section_code = trim((string) $data['section_code']);
            }

            if (isset($data['normal_balance'])) {
                $nb = (string) $data['normal_balance'];
                if (! in_array($nb, ['debit', 'credit'], true)) {
                    throw ValidationException::withMessages(['normal_balance' => ['Normal balance must be debit or credit.']]);
                }
                $line->normal_balance = $nb;
            }

            if (isset($data['name'])) {
                $line->name = $data['name'];
            }

            if (isset($data['sort_order'])) {
                $line->sort_order = (int) $data['sort_order'];
            }

            if (isset($data['is_active'])) {
                $line->is_active = (bool) $data['is_active'];
            }

            $line->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'financial_statement_line.update',
                entityType: 'financial_statement_line',
                entityId: $line->id,
                before: $before,
                after: $line->fresh()->toArray()
            );

            return $line->fresh();
        });
    }

    public function deleteStatementLine(string $id, int $actorId): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var FinancialStatementLine $line */
            $line = FinancialStatementLine::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($line->is_system) {
                throw ValidationException::withMessages(['line' => ['System financial statement lines cannot be deleted.']]);
            }

            if ($line->accounts()->count() > 0) {
                throw ValidationException::withMessages(['line' => ['Cannot delete financial statement line that has assigned accounts.']]);
            }

            $before = $line->toArray();
            $line->delete();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'financial_statement_line.delete',
                entityType: 'financial_statement_line',
                entityId: $id,
                before: $before,
                after: null
            );
        });
    }

    public function assignAccount(string $accountId, ?string $statementLineId, int $actorId): Account
    {
        return DB::transaction(function () use ($accountId, $statementLineId, $actorId): Account {
            /** @var Account $account */
            $account = Account::query()->with(['accountType.accountCategory'])->where('id', $accountId)->lockForUpdate()->firstOrFail();
            $before = $account->toArray();

            if ($statementLineId === null || $statementLineId === '') {
                $account->financial_statement_line_id = null;
                $account->save();

                $this->auditLogger->record(
                    actorId: $actorId,
                    action: 'account.statement_line_unassign',
                    entityType: 'account',
                    entityId: $account->id,
                    before: $before,
                    after: $account->fresh()->toArray()
                );

                return $account->fresh(['financialStatementLine', 'accountType', 'group']);
            }

            /** @var FinancialStatementLine $line */
            $line = FinancialStatementLine::query()->where('id', $statementLineId)->first();
            if (! $line) {
                throw ValidationException::withMessages(['financial_statement_line_id' => ['Financial statement line does not exist.']]);
            }

            if (! $line->is_active) {
                throw ValidationException::withMessages(['financial_statement_line_id' => ['Cannot assign account to an inactive financial statement line.']]);
            }

            // Check statement type matching
            $expectedType = $this->resolveAccountStatementType($account);
            if ($expectedType !== null && $line->statement_type !== $expectedType) {
                throw ValidationException::withMessages([
                    'financial_statement_line_id' => ["Statement line statement type ({$line->statement_type}) does not match account statement type ({$expectedType})."],
                ]);
            }

            $account->financial_statement_line_id = $line->id;
            $account->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'account.statement_line_assign',
                entityType: 'account',
                entityId: $account->id,
                before: $before,
                after: $account->fresh()->toArray()
            );

            return $account->fresh(['financialStatementLine', 'accountType', 'group']);
        });
    }

    /**
     * @param  list<array{account_id: string, financial_statement_line_id: string|null}>  $assignments
     */
    public function bulkAssignAccounts(array $assignments, int $actorId): void
    {
        DB::transaction(function () use ($assignments, $actorId): void {
            foreach ($assignments as $item) {
                if (empty($item['account_id'])) {
                    continue;
                }
                $this->assignAccount($item['account_id'], $item['financial_statement_line_id'] ?? null, $actorId);
            }
        });
    }

    public function resolveAccountStatementType(Account $account): ?string
    {
        if ($account->accountType?->statement_type) {
            return $account->accountType->statement_type;
        }

        if ($account->accountType?->accountCategory?->statement_type) {
            return $account->accountType->accountCategory->statement_type;
        }

        return match ($account->type) {
            'asset', 'liability', 'equity', 'contra_asset', 'contra_liability' => 'balance_sheet',
            'revenue', 'expense', 'contra_revenue' => 'income_statement',
            default => null,
        };
    }
}
