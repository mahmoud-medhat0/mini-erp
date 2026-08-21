<?php

namespace App\Application\MasterData;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class BankAccountService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    /**
     * @param  array{code: string, name: array<string, string>|string, bank_name?: array<string, string>|string|null, account_number?: string|null, iban?: string|null, swift?: string|null, gl_account_id: string, currency: string, is_active?: bool}  $data
     */
    public function create(array $data, int|string|null $actorId = null): BankAccount
    {
        if (BankAccount::query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => ["Bank Account code [{$data['code']}] already exists."],
            ]);
        }

        $glAccount = Account::query()->find($data['gl_account_id']);
        if (! $glAccount) {
            throw ValidationException::withMessages([
                'gl_account_id' => ["GL Account [{$data['gl_account_id']}] does not exist."],
            ]);
        }

        if (! $glAccount->is_active) {
            throw ValidationException::withMessages([
                'gl_account_id' => ["GL Account [{$data['gl_account_id']}] is inactive."],
            ]);
        }

        if (! Currency::query()->where('code', $data['currency'])->exists()) {
            throw ValidationException::withMessages([
                'currency' => ["Currency [{$data['currency']}] does not exist."],
            ]);
        }

        $bankAccount = BankAccount::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'iban' => $data['iban'] ?? null,
            'swift' => $data['swift'] ?? null,
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
            entityType: 'bank_account',
            entityId: $bankAccount->id,
            before: null,
            after: $bankAccount->fresh()->toArray(),
        );

        return $bankAccount;
    }

    /**
     * @param  array{code?: string, name?: array<string, string>|string, bank_name?: array<string, string>|string|null, account_number?: string|null, iban?: string|null, swift?: string|null, gl_account_id?: string, currency?: string, is_active?: bool}  $data
     */
    public function update(string $id, array $data, int $expectedVersion, int|string|null $actorId = null): BankAccount
    {
        /** @var BankAccount $bankAccount */
        $bankAccount = BankAccount::query()->findOrFail($id);
        $before = $bankAccount->toArray();

        if (isset($data['code']) && $data['code'] !== $bankAccount->code) {
            if (BankAccount::query()->where('code', $data['code'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => ["Bank Account code [{$data['code']}] already exists."],
                ]);
            }
        }

        if (isset($data['gl_account_id'])) {
            $glAccount = Account::query()->find($data['gl_account_id']);
            if (! $glAccount) {
                throw ValidationException::withMessages([
                    'gl_account_id' => ["GL Account [{$data['gl_account_id']}] does not exist."],
                ]);
            }

            if (! $glAccount->is_active) {
                throw ValidationException::withMessages([
                    'gl_account_id' => ["GL Account [{$data['gl_account_id']}] is inactive."],
                ]);
            }
        }

        if (isset($data['currency'])) {
            if (! Currency::query()->where('code', $data['currency'])->exists()) {
                throw ValidationException::withMessages([
                    'currency' => ["Currency [{$data['currency']}] does not exist."],
                ]);
            }
        }

        $updateValues = [];

        foreach (['code', 'account_number', 'iban', 'swift', 'gl_account_id', 'currency', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateValues[$field] = $data[$field];
            }
        }

        if (array_key_exists('name', $data)) {
            $updateValues['name'] = $this->encodeTranslatable($data['name']);
        }

        if (array_key_exists('bank_name', $data)) {
            $updateValues['bank_name'] = $data['bank_name'] === null
                ? null
                : $this->encodeTranslatable($data['bank_name']);
        }

        if ($actorId !== null) {
            $updateValues['updated_by'] = $actorId;
        }

        $updateValues['updated_at'] = now();

        $this->optimisticLock->update('bank_account', ['id' => $id], $expectedVersion, $updateValues);

        $updatedBankAccount = $bankAccount->fresh();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'bank_account',
            entityId: $id,
            before: $before,
            after: $updatedBankAccount->toArray(),
        );

        return $updatedBankAccount;
    }

    /**
     * @param  array<string, string>|string  $value
     */
    private function encodeTranslatable(array|string $value): string
    {
        return is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    }
}
