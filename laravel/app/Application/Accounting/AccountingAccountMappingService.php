<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use Illuminate\Validation\ValidationException;

class AccountingAccountMappingService
{
    public const ALLOWED_KEYS = [
        'ar_control',
        'ap_control',
        'opening_balance_offset',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getMapping(string $key): ?AccountingAccountMapping
    {
        return AccountingAccountMapping::query()
            ->with('account')
            ->where('key', $key)
            ->first();
    }

    public function getAccount(string $key): Account
    {
        $this->assertAllowedKey($key);

        $mapping = $this->getMapping($key);

        if (! $mapping || ! $mapping->account_id) {
            throw ValidationException::withMessages([
                'mapping' => ["Accounting mapping for [{$key}] is not configured."],
            ]);
        }

        if (! $mapping->account || ! $mapping->account->is_active) {
            throw ValidationException::withMessages([
                'mapping' => ["Mapped account for [{$key}] is missing or inactive."],
            ]);
        }

        $this->assertAccountMatchesKey($key, $mapping->account);

        return $mapping->account;
    }

    public function getAccountId(string $key): string
    {
        return $this->getAccount($key)->id;
    }

    public function setMapping(string $key, string $accountId, ?string $description = null, int|string|null $actorId = null): AccountingAccountMapping
    {
        $this->assertAllowedKey($key);

        /** @var Account|null $account */
        $account = Account::query()->find($accountId);
        if (! $account) {
            throw ValidationException::withMessages([
                'account_id' => ["GL Account [{$accountId}] does not exist."],
            ]);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'account_id' => ["GL Account [{$account->code}] is inactive."],
            ]);
        }

        $this->assertAccountMatchesKey($key, $account);

        $existing = AccountingAccountMapping::query()->where('key', $key)->first();
        $before = $existing?->toArray();

        $mapping = AccountingAccountMapping::query()->updateOrCreate(
            ['key' => $key],
            [
                'account_id' => $accountId,
                'description' => $description ?? $existing?->description,
                'is_system' => true,
                'created_by' => $existing ? $existing->created_by : $actorId,
                'updated_by' => $actorId,
            ]
        );

        $this->auditLogger->record(
            actorId: $actorId,
            action: $before ? 'update' : 'create',
            entityType: 'accounting_account_mapping',
            entityId: $mapping->id,
            before: $before,
            after: $mapping->fresh()->toArray(),
        );

        return $mapping->fresh(['account']);
    }

    private function assertAllowedKey(string $key): void
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw ValidationException::withMessages([
                'key' => ["Mapping key [{$key}] is not allowed in current slice."],
            ]);
        }
    }

    private function assertAccountMatchesKey(string $key, Account $account): void
    {
        $expectedTypes = match ($key) {
            'ar_control' => ['asset'],
            'ap_control' => ['liability'],
            'opening_balance_offset' => ['equity'],
        };

        if (! in_array($account->type, $expectedTypes, true)) {
            throw ValidationException::withMessages([
                'account_id' => ["Mapping [{$key}] requires account type [".implode(', ', $expectedTypes).'].'],
            ]);
        }

        $expectedNature = match ($key) {
            'ar_control' => 'debit',
            'ap_control' => 'credit',
            'opening_balance_offset' => null,
        };

        if ($expectedNature !== null && $account->nature !== $expectedNature) {
            throw ValidationException::withMessages([
                'account_id' => ["Mapping [{$key}] requires account nature [{$expectedNature}]."],
            ]);
        }
    }
}
