<?php

namespace App\Application\Payroll;

use App\Domain\Audit\AuditLogger;
use App\Models\Employee;
use App\Models\EmployeePayrollComponent;
use App\Models\PayrollComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeePayrollComponentService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(string $employeeId, array $data, ?int $actorId = null): EmployeePayrollComponent
    {
        return DB::transaction(function () use ($employeeId, $data, $actorId): EmployeePayrollComponent {
            /** @var Employee $employee */
            $employee = Employee::query()->whereKey($employeeId)->lockForUpdate()->firstOrFail();
            $component = $this->activeComponent((string) ($data['payroll_component_id'] ?? ''));
            $payload = $this->validatedPayload($component, $data);

            /** @var EmployeePayrollComponent $assignment */
            $assignment = $employee->componentAssignments()->create($payload);

            $this->auditLogger->record($actorId, 'employee_payroll_component.create', 'employee_payroll_component', $assignment->id, after: $assignment->toArray());

            return $assignment->fresh(['component']);
        });
    }

    public function delete(string $employeeId, string $assignmentId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($employeeId, $assignmentId, $actorId): void {
            /** @var EmployeePayrollComponent $assignment */
            $assignment = EmployeePayrollComponent::query()
                ->where('employee_id', $employeeId)
                ->whereKey($assignmentId)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $assignment->toArray();
            $assignment->delete();

            $this->auditLogger->record($actorId, 'employee_payroll_component.delete', 'employee_payroll_component', $assignmentId, before: $before);
        });
    }

    private function activeComponent(string $id): PayrollComponent
    {
        /** @var PayrollComponent|null $component */
        $component = PayrollComponent::query()->whereKey($id)->where('is_active', true)->first();

        if (! $component) {
            throw ValidationException::withMessages(['payroll_component_id' => [__('Selected payroll component is inactive or missing.')]]);
        }

        return $component;
    }

    private function validatedPayload(PayrollComponent $component, array $data): array
    {
        $amountMinor = array_key_exists('amount_minor', $data) && $data['amount_minor'] !== null && $data['amount_minor'] !== ''
            ? (int) $data['amount_minor']
            : null;
        $rateBps = array_key_exists('rate_bps', $data) && $data['rate_bps'] !== null && $data['rate_bps'] !== ''
            ? (int) $data['rate_bps']
            : null;
        $effectiveFrom = (string) ($data['effective_from'] ?? '');
        $effectiveTo = $this->nullableString($data['effective_to'] ?? null);

        if ($component->calculation_type === 'fixed' && $amountMinor !== null && $amountMinor < 0) {
            throw ValidationException::withMessages(['amount_minor' => [__('Amount cannot be negative.')]]);
        }

        if ($component->calculation_type === 'percent_of_base' && $rateBps !== null && ($rateBps < 0 || $rateBps > 1000000)) {
            throw ValidationException::withMessages(['rate_bps' => [__('Rate must be between 0 and 1000000 basis points.')]]);
        }

        if ($effectiveFrom === '') {
            throw ValidationException::withMessages(['effective_from' => [__('Effective start date is required.')]]);
        }

        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw ValidationException::withMessages(['effective_to' => [__('Effective end date cannot be before effective start date.')]]);
        }

        return [
            'payroll_component_id' => $component->id,
            'amount_minor' => $amountMinor,
            'rate_bps' => $rateBps,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }
}
