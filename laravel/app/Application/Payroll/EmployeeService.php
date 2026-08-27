<?php

namespace App\Application\Payroll;

use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public const STATUSES = ['active', 'inactive', 'terminated'];

    public const PAYMENT_METHODS = ['manual', 'cash', 'bank'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, ?int $actorId = null): Employee
    {
        return DB::transaction(function () use ($data, $actorId): Employee {
            $payload = $this->validatedPayload($data);

            /** @var Employee $employee */
            $employee = Employee::query()->create([
                ...$payload,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'employee.create', 'employee', $employee->id, after: $employee->toArray());

            return $employee->fresh(['branch', 'currencyRef']);
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): Employee
    {
        return DB::transaction(function () use ($id, $data, $actorId): Employee {
            /** @var Employee $employee */
            $employee = Employee::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $employee->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The employee was modified by another user. Please refresh and try again.')]]);
            }

            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $employee->code,
                'name' => $data['name'] ?? $employee->getTranslations('name'),
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $employee->branch_id,
                'status' => $data['status'] ?? $employee->status,
                'hire_date' => $data['hire_date'] ?? $employee->hire_date?->format('Y-m-d'),
                'termination_date' => array_key_exists('termination_date', $data) ? $data['termination_date'] : $employee->termination_date?->format('Y-m-d'),
                'currency' => $data['currency'] ?? $employee->currency,
                'base_salary_minor' => $data['base_salary_minor'] ?? $employee->base_salary_minor,
                'payment_method' => $data['payment_method'] ?? $employee->payment_method,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $employee->notes,
            ], $employee->id);
            $before = $employee->toArray();

            $employee->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => $employee->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'employee.update', 'employee', $employee->id, before: $before, after: $employee->fresh()->toArray());

            return $employee->fresh(['branch', 'currencyRef']);
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = $this->normalizeName($data['name'] ?? []);
        $branchId = $this->nullableUuid($data['branch_id'] ?? null);
        $status = (string) ($data['status'] ?? 'active');
        $hireDate = (string) ($data['hire_date'] ?? '');
        $terminationDate = $this->nullableString($data['termination_date'] ?? null);
        $currency = CurrencyInput::required($data['currency'] ?? null);
        $baseSalaryMinor = (int) ($data['base_salary_minor'] ?? 0);
        $paymentMethod = (string) ($data['payment_method'] ?? 'manual');

        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Employee code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = Employee::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Employee code already exists.')]]);
        }

        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => [__('Invalid employee status.')]]);
        }

        if ($hireDate === '') {
            throw ValidationException::withMessages(['hire_date' => [__('Hire date is required.')]]);
        }

        if ($terminationDate !== null && $terminationDate < $hireDate) {
            throw ValidationException::withMessages(['termination_date' => [__('Termination date cannot be before hire date.')]]);
        }

        if ($baseSalaryMinor < 0) {
            throw ValidationException::withMessages(['base_salary_minor' => [__('Base salary cannot be negative.')]]);
        }

        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw ValidationException::withMessages(['payment_method' => [__('Invalid payment method.')]]);
        }

        if (! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected currency is missing from the currency registry.')]]);
        }

        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is inactive or missing.')]]);
        }

        return [
            'code' => $code,
            'name' => $name,
            'branch_id' => $branchId,
            'status' => $status,
            'hire_date' => $hireDate,
            'termination_date' => $terminationDate,
            'currency' => $currency,
            'base_salary_minor' => $baseSalaryMinor,
            'payment_method' => $paymentMethod,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function normalizeName(mixed $value): array
    {
        $translations = is_array($value) ? $value : [];
        $en = trim((string) ($translations['en'] ?? $translations['name_en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $translations['name_ar'] ?? $en));

        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English employee name is required.')]]);
        }

        return ['en' => $en, 'ar' => $ar === '' ? $en : $ar];
    }

    private function nullableUuid(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value !== null && ! Str::isUuid($value)) {
            throw ValidationException::withMessages(['branch_id' => [__('Invalid branch reference.')]]);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }
}
