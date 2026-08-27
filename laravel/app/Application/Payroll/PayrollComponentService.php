<?php

namespace App\Application\Payroll;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\PayrollComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollComponentService
{
    public const TYPES = ['earning', 'deduction'];

    public const CALCULATION_TYPES = ['fixed', 'percent_of_base'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, ?int $actorId = null): PayrollComponent
    {
        return DB::transaction(function () use ($data, $actorId): PayrollComponent {
            $payload = $this->validatedPayload($data);

            /** @var PayrollComponent $component */
            $component = PayrollComponent::query()->create([
                ...$payload,
                'is_system' => (bool) ($data['is_system'] ?? false),
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_component.create', 'payroll_component', $component->id, after: $component->toArray());

            return $component->fresh(['expenseAccount', 'liabilityAccount']);
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): PayrollComponent
    {
        return DB::transaction(function () use ($id, $data, $actorId): PayrollComponent {
            /** @var PayrollComponent $component */
            $component = PayrollComponent::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $component->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The component was modified by another user. Please refresh and try again.')]]);
            }

            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $component->code,
                'name' => $data['name'] ?? $component->getTranslations('name'),
                'type' => $data['type'] ?? $component->type,
                'calculation_type' => $data['calculation_type'] ?? $component->calculation_type,
                'default_amount_minor' => array_key_exists('default_amount_minor', $data) ? $data['default_amount_minor'] : $component->default_amount_minor,
                'rate_bps' => array_key_exists('rate_bps', $data) ? $data['rate_bps'] : $component->rate_bps,
                'expense_account_id' => array_key_exists('expense_account_id', $data) ? $data['expense_account_id'] : $component->expense_account_id,
                'liability_account_id' => array_key_exists('liability_account_id', $data) ? $data['liability_account_id'] : $component->liability_account_id,
                'sort_order' => $data['sort_order'] ?? $component->sort_order,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $component->is_active,
            ], $component->id);
            $before = $component->toArray();

            $component->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => $component->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'payroll_component.update', 'payroll_component', $component->id, before: $before, after: $component->fresh()->toArray());

            return $component->fresh(['expenseAccount', 'liabilityAccount']);
        });
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var PayrollComponent $component */
            $component = PayrollComponent::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($component->is_system) {
                throw ValidationException::withMessages(['component' => [__('System payroll components cannot be deleted.')]]);
            }

            if ($component->employeeAssignments()->exists()) {
                throw ValidationException::withMessages(['component' => [__('Payroll component is assigned to employees and cannot be deleted.')]]);
            }

            $before = $component->toArray();
            $component->delete();

            $this->auditLogger->record($actorId, 'payroll_component.delete', 'payroll_component', $id, before: $before);
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = $this->normalizeName($data['name'] ?? []);
        $type = (string) ($data['type'] ?? 'earning');
        $calculationType = (string) ($data['calculation_type'] ?? 'fixed');
        $defaultAmountMinor = array_key_exists('default_amount_minor', $data) && $data['default_amount_minor'] !== null && $data['default_amount_minor'] !== ''
            ? (int) $data['default_amount_minor']
            : null;
        $rateBps = array_key_exists('rate_bps', $data) && $data['rate_bps'] !== null && $data['rate_bps'] !== ''
            ? (int) $data['rate_bps']
            : null;
        $expenseAccountId = $this->nullableString($data['expense_account_id'] ?? null);
        $liabilityAccountId = $this->nullableString($data['liability_account_id'] ?? null);

        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Component code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = PayrollComponent::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Component code already exists.')]]);
        }

        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => [__('Invalid component type.')]]);
        }

        if (! in_array($calculationType, self::CALCULATION_TYPES, true)) {
            throw ValidationException::withMessages(['calculation_type' => [__('Invalid calculation type.')]]);
        }

        if ($calculationType === 'fixed' && $defaultAmountMinor !== null && $defaultAmountMinor < 0) {
            throw ValidationException::withMessages(['default_amount_minor' => [__('Default amount cannot be negative.')]]);
        }

        if ($calculationType === 'percent_of_base' && ($rateBps === null || $rateBps < 0 || $rateBps > 1000000)) {
            throw ValidationException::withMessages(['rate_bps' => [__('Percent-based component requires a rate between 0 and 1000000 basis points.')]]);
        }

        if ($expenseAccountId !== null) {
            /** @var Account $account */
            $account = Account::query()->findOrFail($expenseAccountId);
            if (! $account->is_active || $account->type !== 'expense' || $account->nature !== 'debit' || $account->is_control) {
                throw ValidationException::withMessages(['expense_account_id' => [__('Expense account must be an active non-control debit expense account.')]]);
            }
        }

        if ($liabilityAccountId !== null) {
            /** @var Account $account */
            $account = Account::query()->findOrFail($liabilityAccountId);
            if (! $account->is_active || $account->type !== 'liability' || $account->nature !== 'credit') {
                throw ValidationException::withMessages(['liability_account_id' => [__('Liability account must be an active credit liability account.')]]);
            }
        }

        return [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'calculation_type' => $calculationType,
            'default_amount_minor' => $defaultAmountMinor,
            'rate_bps' => $rateBps,
            'expense_account_id' => $expenseAccountId,
            'liability_account_id' => $liabilityAccountId,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 100)),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function normalizeName(mixed $value): array
    {
        $translations = is_array($value) ? $value : [];
        $en = trim((string) ($translations['en'] ?? $translations['name_en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $translations['name_ar'] ?? $en));

        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English component name is required.')]]);
        }

        return ['en' => $en, 'ar' => $ar === '' ? $en : $ar];
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }
}
