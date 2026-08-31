<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Branch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AccountingAccountMappingService
{
    public const ALLOWED_KEYS = [
        'ar_control',
        'ap_control',
        'opening_balance_offset',
        'cheques_under_collection',
        'cheques_payable',
        'sales_revenue',
        'sales_returns',
        'purchase_expense',
        'purchase_returns_allowances',
        'inventory_asset',
        'grni_clearing',
        'cogs',
        'inventory_return_variance',
        'inventory_scrap_loss',
        'inventory_adjustment_gain',
        'inventory_adjustment_loss',
        'output_tax_payable',
        'input_tax_receivable',
        'fixed_asset_cost',
        'accumulated_depreciation',
        'depreciation_expense',
        'fixed_asset_disposal_gain',
        'fixed_asset_disposal_loss',
        'fixed_asset_clearing',
        'prepaid_expense_asset',
        'accrued_expense_liability',
        'payroll_expense',
        'payroll_payable',
        'payroll_deductions_payable',
        'rental_revenue',
        'rental_damage_revenue',
        'rental_late_fee_revenue',
        'rental_other_revenue',
        'rental_deposit_liability',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getMapping(string $key, ?string $branchId = null): ?AccountingAccountMapping
    {
        $this->assertAllowedKey($key);
        $this->assertBranchExists($branchId);

        if ($branchId !== null) {
            /** @var AccountingAccountMapping|null $branchMapping */
            $branchMapping = AccountingAccountMapping::query()
                ->where('key', $key)
                ->where('branch_id', $branchId)
                ->first();

            if ($branchMapping) {
                return $branchMapping;
            }
        }

        /** @var AccountingAccountMapping|null $mapping */
        $mapping = AccountingAccountMapping::query()
            ->where('key', $key)
            ->whereNull('branch_id')
            ->first();

        return $mapping;
    }

    public function getAccount(string $key, ?string $branchId = null): Account
    {
        $mapping = $this->getMapping($key, $branchId);

        if (! $mapping || ! $mapping->account_id) {
            throw ValidationException::withMessages([
                'account_mapping' => [__('Required accounting mapping [:key] is missing. Please configure it in Chart of Accounts settings.', ['key' => $key])],
            ]);
        }

        /** @var Account|null $account */
        $account = Account::query()->find($mapping->account_id);

        if (! $account || ! $account->is_active) {
            throw ValidationException::withMessages([
                'account_mapping' => [__('Mapped account for [:key] is inactive or missing.', ['key' => $key])],
            ]);
        }

        return $account;
    }

    public function getAccountId(string $key, ?string $branchId = null): string
    {
        return $this->getAccount($key, $branchId)->id;
    }

    public function setMapping(
        string $key,
        string $accountId,
        ?string $description = null,
        int|string|null $actorId = null,
        ?string $branchId = null,
    ): AccountingAccountMapping {
        $this->assertAllowedKey($key);
        $this->assertBranchExists($branchId);

        /** @var Account $account */
        $account = Account::query()->findOrFail($accountId);

        $this->assertAccountMatchesKey($key, $account);

        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        /** @var AccountingAccountMapping $mapping */
        $mapping = AccountingAccountMapping::query()->updateOrCreate(
            [
                'key' => $key,
                'branch_id' => $branchId,
            ],
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
            after: ['key' => $key, 'branch_id' => $branchId, 'account_id' => $account->id]
        );

        return $mapping;
    }

    public function deleteBranchMapping(string $id, int|string|null $actorId = null): void
    {
        /** @var AccountingAccountMapping|null $mapping */
        $mapping = AccountingAccountMapping::query()->find($id);

        if (! $mapping) {
            throw (new ModelNotFoundException)->setModel(AccountingAccountMapping::class, [$id]);
        }

        if ($mapping->branch_id === null) {
            throw ValidationException::withMessages([
                'mapping' => [__('Global accounting mappings cannot be deleted. Update the mapped account instead.')],
            ]);
        }

        $before = $mapping->toArray();
        $mapping->delete();

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'accounting_mapping.delete',
            entityType: 'accounting_account_mapping',
            entityId: $id,
            before: $before,
            after: null,
        );
    }

    private function assertAllowedKey(string $key): void
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw ValidationException::withMessages([
                'key' => [__('Mapping key [:key] is not allowed.', ['key' => $key])],
            ]);
        }
    }

    private function assertBranchExists(?string $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        if (! Branch::query()->whereKey($branchId)->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => [__('Branch [:branch] does not exist.', ['branch' => $branchId])],
            ]);
        }
    }

    private function assertAccountMatchesKey(string $key, Account $account): void
    {
        $expectedTypes = match ($key) {
            'ar_control', 'cheques_under_collection', 'inventory_asset', 'input_tax_receivable', 'fixed_asset_cost', 'accumulated_depreciation', 'fixed_asset_clearing', 'prepaid_expense_asset' => ['asset'],
            'ap_control', 'cheques_payable', 'grni_clearing', 'output_tax_payable', 'accrued_expense_liability',
            'payroll_payable', 'payroll_deductions_payable', 'rental_deposit_liability' => ['liability'],
            'opening_balance_offset' => ['equity'],
            'sales_revenue', 'sales_returns', 'fixed_asset_disposal_gain', 'inventory_adjustment_gain',
            'rental_revenue', 'rental_damage_revenue', 'rental_late_fee_revenue', 'rental_other_revenue' => ['revenue'],
            'purchase_expense', 'cogs', 'purchase_returns_allowances', 'inventory_return_variance', 'inventory_scrap_loss',
            'inventory_adjustment_loss', 'depreciation_expense', 'fixed_asset_disposal_loss', 'payroll_expense' => ['expense'],
        };

        if (! in_array($account->type, $expectedTypes, true)) {
            throw ValidationException::withMessages([
                'account_id' => [__('Mapping [:key] requires account type [:types].', [
                    'key' => $key,
                    'types' => implode(', ', $expectedTypes),
                ])],
            ]);
        }

        $expectedNature = match ($key) {
            'ar_control', 'cheques_under_collection', 'purchase_expense', 'inventory_asset', 'cogs',
            'sales_returns', 'purchase_returns_allowances', 'inventory_return_variance', 'inventory_scrap_loss',
            'inventory_adjustment_loss', 'input_tax_receivable', 'fixed_asset_cost', 'depreciation_expense',
            'fixed_asset_disposal_loss', 'fixed_asset_clearing', 'prepaid_expense_asset', 'payroll_expense' => 'debit',
            'ap_control', 'cheques_payable', 'sales_revenue', 'grni_clearing',
            'output_tax_payable', 'accumulated_depreciation', 'fixed_asset_disposal_gain', 'inventory_adjustment_gain',
            'accrued_expense_liability', 'payroll_payable', 'payroll_deductions_payable', 'rental_revenue',
            'rental_damage_revenue', 'rental_late_fee_revenue', 'rental_other_revenue', 'rental_deposit_liability' => 'credit',
            'opening_balance_offset' => null,
        };

        if ($expectedNature !== null && $account->nature !== $expectedNature) {
            throw ValidationException::withMessages([
                'account_id' => [__('Mapping [:key] requires account nature [:nature].', [
                    'key' => $key,
                    'nature' => $expectedNature,
                ])],
            ]);
        }
    }
}
