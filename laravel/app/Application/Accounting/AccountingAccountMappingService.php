<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AccountingAccountMappingService
{
    public const ALLOWED_KEYS = [
        'ar_control',
        'ap_control',
        'opening_balance_offset',
        'cheques_under_collection',
        'cheques_payable',
        'sales_revenue',
        'purchase_expense',
        'inventory_asset',
        'grni_clearing',
        'cogs',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getMapping(string $key): ?AccountingAccountMapping
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new InvalidArgumentException("Invalid account mapping key: {$key}");
        }

        /** @var AccountingAccountMapping|null $mapping */
        $mapping = AccountingAccountMapping::query()->where('key', $key)->first();

        return $mapping;
    }

    public function getAccount(string $key): Account
    {
        $mapping = $this->getMapping($key);

        if (! $mapping || ! $mapping->account_id) {
            throw ValidationException::withMessages([
                'account_mapping' => ["Required accounting mapping [{$key}] is missing. Please configure it in Chart of Accounts settings."],
            ]);
        }

        /** @var Account|null $account */
        $account = Account::query()->find($mapping->account_id);

        if (! $account || ! $account->is_active) {
            throw ValidationException::withMessages([
                'account_mapping' => ["Mapped account for [{$key}] is inactive or missing."],
            ]);
        }

        return $account;
    }

    public function getAccountId(string $key): string
    {
        return $this->getAccount($key)->id;
    }

    public function setMapping(string $key, string $accountId, ?string $description = null, int|string|null $actorId = null): AccountingAccountMapping
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new InvalidArgumentException("Invalid account mapping key: {$key}");
        }

        /** @var Account $account */
        $account = Account::query()->findOrFail($accountId);

        $this->assertAccountMatchesKey($key, $account);

        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        /** @var AccountingAccountMapping $mapping */
        $mapping = AccountingAccountMapping::query()->updateOrCreate(
            ['key' => $key],
            [
                'account_id' => $account->id,
                'description' => $description,
                'created_by' => $userActorId,
                'updated_by' => $userActorId,
            ]
        );

        $this->auditLogger->record(
            actorId: $userActorId,
            action: 'accounting_mapping.update',
            entityType: 'accounting_account_mapping',
            entityId: (string) $mapping->id,
            before: null,
            after: ['key' => $key, 'account_id' => $account->id]
        );

        return $mapping;
    }

    private function assertAccountMatchesKey(string $key, Account $account): void
    {
        $expectedTypes = match ($key) {
            'ar_control', 'cheques_under_collection', 'inventory_asset' => ['asset'],
            'ap_control', 'cheques_payable', 'grni_clearing' => ['liability'],
            'opening_balance_offset' => ['equity'],
            'sales_revenue' => ['revenue'],
            'purchase_expense', 'cogs' => ['expense'],
        };

        if (! in_array($account->type, $expectedTypes, true)) {
            throw ValidationException::withMessages([
                'account_id' => ["Mapping [{$key}] requires account type [".implode(', ', $expectedTypes).'].'],
            ]);
        }

        $expectedNature = match ($key) {
            'ar_control', 'cheques_under_collection', 'purchase_expense', 'inventory_asset', 'cogs' => 'debit',
            'ap_control', 'cheques_payable', 'sales_revenue', 'grni_clearing' => 'credit',
            'opening_balance_offset' => null,
        };

        if ($expectedNature !== null && $account->nature !== $expectedNature) {
            throw ValidationException::withMessages([
                'account_id' => ["Mapping [{$key}] requires account nature [{$expectedNature}]."],
            ]);
        }
    }
}
