<?php

namespace App\Application\MasterData;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class CashAccountService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    /**
     * @param  array{code: string, name: array<string, string>|string, branch_id?: string|null, gl_account_id: string, currency: string, is_active?: bool}  $data
     */
    public function create(array $data, int|string|null $actorId = null): CashAccount
    {
        if (CashAccount::query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('Cash Account code :code already exists.', ['code' => $data['code']])],
            ]);
        }

        $glAccount = Account::query()->find($data['gl_account_id']);
        if (! $glAccount) {
            throw ValidationException::withMessages([
                'gl_account_id' => [__('GL Account :account does not exist.', ['account' => $data['gl_account_id']])],
            ]);
        }

        if (! $glAccount->is_active) {
            throw ValidationException::withMessages([
                'gl_account_id' => [__('GL Account :account is inactive.', ['account' => $data['gl_account_id']])],
            ]);
        }

        if (! Currency::query()->where('code', $data['currency'])->exists()) {
            throw ValidationException::withMessages([
                'currency' => [__('Currency :currency does not exist.', ['currency' => $data['currency']])],
            ]);
        }

        $branchId = $this->normalizeBranchId($data['branch_id'] ?? null);
        $this->validateBranch($branchId);

        $cashAccount = CashAccount::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'branch_id' => $branchId,
            'gl_account_id' => $data['gl_account_id'],
            'currency' => $data['currency'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 0,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'cash_account',
            entityId: $cashAccount->id,
            before: null,
            after: $cashAccount->fresh()->toArray(),
        );

        return $cashAccount;
    }

    /**
     * @param  array{code?: string, name?: array<string, string>|string, branch_id?: string|null, gl_account_id?: string, currency?: string, is_active?: bool}  $data
     */
    public function update(string $id, array $data, int $expectedVersion, int|string|null $actorId = null): CashAccount
    {
        /** @var CashAccount $cashAccount */
        $cashAccount = CashAccount::query()->findOrFail($id);
        $before = $cashAccount->toArray();

        if (isset($data['code']) && $data['code'] !== $cashAccount->code) {
            if (CashAccount::query()->where('code', $data['code'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => [__('Cash Account code :code already exists.', ['code' => $data['code']])],
                ]);
            }
        }

        if (isset($data['gl_account_id'])) {
            $glAccount = Account::query()->find($data['gl_account_id']);
            if (! $glAccount) {
                throw ValidationException::withMessages([
                    'gl_account_id' => [__('GL Account :account does not exist.', ['account' => $data['gl_account_id']])],
                ]);
            }

            if (! $glAccount->is_active) {
                throw ValidationException::withMessages([
                    'gl_account_id' => [__('GL Account :account is inactive.', ['account' => $data['gl_account_id']])],
                ]);
            }
        }

        if (isset($data['currency'])) {
            if (! Currency::query()->where('code', $data['currency'])->exists()) {
                throw ValidationException::withMessages([
                    'currency' => [__('Currency :currency does not exist.', ['currency' => $data['currency']])],
                ]);
            }
        }

        if (array_key_exists('branch_id', $data)) {
            $data['branch_id'] = $this->normalizeBranchId($data['branch_id']);
            $this->validateBranch($data['branch_id']);
        }

        $updateValues = [];

        foreach (['code', 'branch_id', 'gl_account_id', 'currency', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateValues[$field] = $data[$field];
            }
        }

        if (array_key_exists('name', $data)) {
            $updateValues['name'] = $this->encodeTranslatable($data['name']);
        }

        if ($actorId !== null) {
            $updateValues['updated_by'] = $actorId;
        }

        $updateValues['updated_at'] = now();

        $this->optimisticLock->update('cash_account', ['id' => $id], $expectedVersion, $updateValues);

        $updatedCashAccount = $cashAccount->fresh();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'cash_account',
            entityId: $id,
            before: $before,
            after: $updatedCashAccount->toArray(),
        );

        return $updatedCashAccount;
    }

    /**
     * @param  array<string, string>|string  $value
     */
    private function encodeTranslatable(array|string $value): string
    {
        return is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    }

    private function validateBranch(?string $branchId): void
    {
        if ($branchId === null || $branchId === '') {
            return;
        }

        if (! Branch::query()->where('id', $branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => [__('Branch :branch does not exist or is inactive.', ['branch' => $branchId])],
            ]);
        }
    }

    private function normalizeBranchId(?string $branchId): ?string
    {
        return $branchId === '' ? null : $branchId;
    }
}
