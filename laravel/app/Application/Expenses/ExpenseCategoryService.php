<?php

namespace App\Application\Expenses;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\TaxCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, int|string|null $actorId = null): ExpenseCategory
    {
        return DB::transaction(function () use ($data, $actorId): ExpenseCategory {
            $code = strtoupper(trim((string) $data['code']));
            $this->assertUniqueCode($code);
            $this->assertDefaultAccount($data['default_expense_account_id'] ?? null);
            $this->assertTaxCode($data['default_tax_code_id'] ?? null);

            /** @var ExpenseCategory $category */
            $category = ExpenseCategory::query()->create([
                'code' => $code,
                'name' => $data['name'],
                'default_expense_account_id' => $data['default_expense_account_id'] ?? null,
                'default_tax_code_id' => $data['default_tax_code_id'] ?? null,
                'requires_attachment' => (bool) ($data['requires_attachment'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'lock_version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($this->actorId($actorId), 'expense_category.create', 'expense_category', $category->id, after: $category->fresh()->toArray());

            return $category->fresh(['defaultExpenseAccount', 'defaultTaxCode']);
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): ExpenseCategory
    {
        return DB::transaction(function () use ($id, $data, $actorId): ExpenseCategory {
            /** @var ExpenseCategory $category */
            $category = ExpenseCategory::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $category->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim((string) $data['code']));
            }

            if (isset($data['code']) && $data['code'] !== $category->code) {
                $this->assertUniqueCode((string) $data['code'], $category->id);
            }

            if (array_key_exists('default_expense_account_id', $data)) {
                $this->assertDefaultAccount($data['default_expense_account_id']);
            }

            if (array_key_exists('default_tax_code_id', $data)) {
                $this->assertTaxCode($data['default_tax_code_id']);
            }

            $before = $category->toArray();
            $updates = [];

            foreach (['code', 'name', 'default_expense_account_id', 'default_tax_code_id', 'requires_attachment', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            $updates['updated_by'] = $actorId;
            $updates['lock_version'] = $category->lock_version + 1;
            $category->update($updates);

            $this->auditLogger->record($this->actorId($actorId), 'expense_category.update', 'expense_category', $category->id, before: $before, after: $category->fresh()->toArray());

            return $category->fresh(['defaultExpenseAccount', 'defaultTaxCode']);
        });
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var ExpenseCategory $category */
            $category = ExpenseCategory::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($category->expenseLines()->exists()) {
                throw ValidationException::withMessages(['category' => [__('Expense categories already used on expenses cannot be deleted.')]]);
            }

            if ($category->prepaidSchedules()->exists() || $category->accrualSchedules()->exists()) {
                throw ValidationException::withMessages(['category' => [__('Expense categories already used on schedules cannot be deleted.')]]);
            }

            $before = $category->toArray();
            $category->delete();

            $this->auditLogger->record($this->actorId($actorId), 'expense_category.delete', 'expense_category', $id, before: $before);
        });
    }

    private function assertUniqueCode(string $code, ?string $ignoreId = null): void
    {
        $exists = ExpenseCategory::query()
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Expense category code [:code] already exists.', ['code' => $code])]]);
        }
    }

    private function assertDefaultAccount(?string $accountId): void
    {
        if (! $accountId) {
            return;
        }

        /** @var Account|null $account */
        $account = Account::query()->find($accountId);

        if (! $account || ! $account->is_active || $account->type !== 'expense' || $account->nature !== 'debit' || $account->is_control) {
            throw ValidationException::withMessages(['default_expense_account_id' => [__('Default expense account must be an active debit expense account and not a control account.')]]);
        }
    }

    private function assertTaxCode(?string $taxCodeId): void
    {
        if (! $taxCodeId) {
            return;
        }

        if (! TaxCode::query()->where('id', $taxCodeId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['default_tax_code_id' => [__('Default tax code must be active.')]]);
        }
    }

    private function actorId(int|string|null $actorId): ?int
    {
        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
