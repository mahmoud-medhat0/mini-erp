<?php

namespace App\Application\Equipment;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 26 - Tools & Equipment custody. Deliberately scoped as a simple
 * operational register with no mandatory GL impact (per the owner decision
 * pack, PHASE_25_GAP_CLOSURE_DECISION_PACK.md §5): each `tool` row is one
 * indivisible custody unit (matching the RentableItem precedent), `quantity`
 * is descriptive only (e.g. "kit of 5 wrenches"), and custody/location only
 * change through the dedicated issue/returnToStock/transfer/markStatus
 * methods below so every change is captured in `tool_movement`.
 */
class ToolService
{
    public const STATUSES = ['available', 'issued', 'damaged', 'lost', 'maintenance', 'retired'];

    private const STATUS_CHANGE_TARGETS = ['available', 'damaged', 'lost', 'maintenance', 'retired'];

    private const TERMINAL_STATUSES = ['retired'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, ?int $actorId = null): Tool
    {
        return DB::transaction(function () use ($data, $actorId): Tool {
            $payload = $this->validatedPayload($data);

            /** @var Tool $tool */
            $tool = Tool::query()->create([
                ...$payload,
                'status' => 'available',
                'custodian_employee_id' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->recordMovement($tool, 'created', null, 'available', null, null, null, null, $data['reason'] ?? null, $actorId);
            $this->auditLogger->record($actorId, 'tool.create', 'tool', $tool->id, after: $tool->fresh($this->relations())->toArray());

            return $tool->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): Tool
    {
        return DB::transaction(function () use ($id, $data, $actorId): Tool {
            /** @var Tool $tool */
            $tool = Tool::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $this->assertLockVersion($tool, $data);
            $before = $tool->fresh($this->relations())->toArray();

            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $tool->code,
                'name' => $data['name'] ?? $tool->getTranslations('name'),
                'description' => array_key_exists('description', $data) ? $data['description'] : $tool->getTranslations('description'),
                'tool_category_id' => $data['tool_category_id'] ?? $tool->tool_category_id,
                'serial_number' => array_key_exists('serial_number', $data) ? $data['serial_number'] : $tool->serial_number,
                'quantity' => $data['quantity'] ?? $tool->quantity,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $tool->branch_id,
                'location_note' => array_key_exists('location_note', $data) ? $data['location_note'] : $tool->location_note,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $tool->notes,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $tool->is_active,
            ], $tool->id);

            $tool->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => ((int) $tool->lock_version) + 1,
            ]);

            $this->recordMovement($tool, 'details_updated', $tool->status, $tool->status, null, null, null, null, $data['reason'] ?? null, $actorId);
            $this->auditLogger->record($actorId, 'tool.update', 'tool', $tool->id, before: $before, after: $tool->fresh($this->relations())->toArray());

            return $tool->fresh($this->relations());
        });
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var Tool $tool */
            $tool = Tool::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($tool->status === 'issued') {
                throw ValidationException::withMessages(['tool' => [__('Tools currently issued to a custodian cannot be deleted; return the tool first.')]]);
            }

            $before = $tool->fresh($this->relations())->toArray();
            $tool->delete();
            $this->auditLogger->record($actorId, 'tool.delete', 'tool', $id, before: $before);
        });
    }

    public function issue(string $id, string $custodianEmployeeId, ?string $branchId, ?string $reason, ?int $actorId = null): Tool
    {
        return DB::transaction(function () use ($id, $custodianEmployeeId, $branchId, $reason, $actorId): Tool {
            /** @var Tool $tool */
            $tool = Tool::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($tool->status !== 'available') {
                throw ValidationException::withMessages(['status' => [__('Only available tools can be issued to a custodian.')]]);
            }

            $employee = $this->requireActiveEmployee($custodianEmployeeId);
            $branchId = $this->nullableUuid($branchId, 'branch_id');
            if ($branchId !== null) {
                $this->requireActiveBranch($branchId);
            }

            $fromBranch = $tool->branch_id;

            $tool->update([
                'status' => 'issued',
                'custodian_employee_id' => $employee->id,
                'branch_id' => $branchId ?? $tool->branch_id,
                'updated_by' => $actorId,
                'lock_version' => ((int) $tool->lock_version) + 1,
            ]);

            $this->recordMovement($tool, 'issued', 'available', 'issued', null, $employee->id, $fromBranch, $tool->branch_id, $reason, $actorId);
            $this->auditLogger->record($actorId, 'tool.issue', 'tool', $tool->id, after: $tool->fresh($this->relations())->toArray());

            return $tool->fresh($this->relations());
        });
    }

    public function returnToStock(string $id, ?string $reason, ?int $actorId = null): Tool
    {
        return DB::transaction(function () use ($id, $reason, $actorId): Tool {
            /** @var Tool $tool */
            $tool = Tool::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($tool->status !== 'issued') {
                throw ValidationException::withMessages(['status' => [__('Only issued tools can be returned to stock.')]]);
            }

            $fromCustodian = $tool->custodian_employee_id;

            $tool->update([
                'status' => 'available',
                'custodian_employee_id' => null,
                'updated_by' => $actorId,
                'lock_version' => ((int) $tool->lock_version) + 1,
            ]);

            $this->recordMovement($tool, 'returned', 'issued', 'available', $fromCustodian, null, null, null, $reason, $actorId);
            $this->auditLogger->record($actorId, 'tool.return', 'tool', $tool->id, after: $tool->fresh($this->relations())->toArray());

            return $tool->fresh($this->relations());
        });
    }

    public function transfer(string $id, ?string $toBranchId, ?string $toCustodianEmployeeId, ?string $reason, ?int $actorId = null): Tool
    {
        return DB::transaction(function () use ($id, $toBranchId, $toCustodianEmployeeId, $reason, $actorId): Tool {
            /** @var Tool $tool */
            $tool = Tool::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (in_array($tool->status, self::TERMINAL_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => [__('Retired tools cannot be transferred.')]]);
            }

            $toBranchId = $this->nullableUuid($toBranchId, 'branch_id');
            if ($toBranchId !== null) {
                $this->requireActiveBranch($toBranchId);
            }

            $toCustodianEmployeeId = $this->nullableUuid($toCustodianEmployeeId, 'custodian_employee_id');
            if ($toCustodianEmployeeId !== null) {
                if ($tool->status !== 'issued') {
                    throw ValidationException::withMessages(['custodian_employee_id' => [__('Only issued tools can be transferred to a different custodian.')]]);
                }
                $this->requireActiveEmployee($toCustodianEmployeeId);
            }

            $fromBranch = $tool->branch_id;
            $fromCustodian = $tool->custodian_employee_id;

            $tool->update([
                'branch_id' => $toBranchId ?? $tool->branch_id,
                'custodian_employee_id' => $toCustodianEmployeeId ?? $tool->custodian_employee_id,
                'updated_by' => $actorId,
                'lock_version' => ((int) $tool->lock_version) + 1,
            ]);

            $this->recordMovement($tool, 'transferred', $tool->status, $tool->status, $fromCustodian, $tool->custodian_employee_id, $fromBranch, $tool->branch_id, $reason, $actorId);
            $this->auditLogger->record($actorId, 'tool.transfer', 'tool', $tool->id, after: $tool->fresh($this->relations())->toArray());

            return $tool->fresh($this->relations());
        });
    }

    public function markStatus(string $id, string $toStatus, ?string $reason, ?int $actorId = null): Tool
    {
        return DB::transaction(function () use ($id, $toStatus, $reason, $actorId): Tool {
            if (! in_array($toStatus, self::STATUS_CHANGE_TARGETS, true)) {
                throw ValidationException::withMessages(['status' => [__('Invalid tool status.')]]);
            }

            /** @var Tool $tool */
            $tool = Tool::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (in_array($tool->status, self::TERMINAL_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => [__('Retired tools cannot change status again.')]]);
            }

            $fromStatus = $tool->status;
            $clearsCustody = $toStatus === 'available';

            $tool->update([
                'status' => $toStatus,
                'custodian_employee_id' => $clearsCustody ? null : $tool->custodian_employee_id,
                'updated_by' => $actorId,
                'lock_version' => ((int) $tool->lock_version) + 1,
            ]);

            $this->recordMovement(
                $tool,
                'status_changed',
                $fromStatus,
                $toStatus,
                $clearsCustody ? $tool->getOriginal('custodian_employee_id') : null,
                null,
                null,
                null,
                $reason,
                $actorId,
            );
            $this->auditLogger->record($actorId, 'tool.status_change', 'tool', $tool->id, after: $tool->fresh($this->relations())->toArray());

            return $tool->fresh($this->relations());
        });
    }

    private function assertLockVersion(Tool $tool, array $data): void
    {
        if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $tool->lock_version) {
            throw ValidationException::withMessages(['lock_version' => [__('The tool was modified by another user. Please refresh and try again.')]]);
        }
    }

    private function requireActiveEmployee(string $employeeId): Employee
    {
        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($employeeId)->where('status', 'active')->first();
        if (! $employee) {
            throw ValidationException::withMessages(['custodian_employee_id' => [__('Selected employee is inactive or missing.')]]);
        }

        return $employee;
    }

    private function requireActiveBranch(string $branchId): void
    {
        if (! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is inactive or missing.')]]);
        }
    }

    private function recordMovement(
        Tool $tool,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $fromCustodianEmployeeId,
        ?string $toCustodianEmployeeId,
        ?string $fromBranchId,
        ?string $toBranchId,
        mixed $reason,
        ?int $actorId
    ): void {
        ToolMovement::query()->create([
            'tool_id' => $tool->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_custodian_employee_id' => $fromCustodianEmployeeId,
            'to_custodian_employee_id' => $toCustodianEmployeeId,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'reason' => $this->nullableString($reason),
            'actor_id' => $actorId,
        ]);
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Tool code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = Tool::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Tool code already exists.')]]);
        }

        $categoryId = (string) ($data['tool_category_id'] ?? '');
        if ($categoryId === '' || ! Str::isUuid($categoryId) || ! ToolCategory::query()->whereKey($categoryId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['tool_category_id' => [__('Selected tool category is inactive or missing.')]]);
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => [__('Quantity must be at least 1.')]]);
        }

        $serialNumber = $this->nullableString($data['serial_number'] ?? null);
        if ($serialNumber !== null) {
            $serialExists = Tool::query()
                ->where('serial_number', $serialNumber)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();
            if ($serialExists) {
                throw ValidationException::withMessages(['serial_number' => [__('Serial number already exists on another tool.')]]);
            }
        }

        $branchId = $this->nullableUuid($data['branch_id'] ?? null, 'branch_id');
        if ($branchId !== null) {
            $this->requireActiveBranch($branchId);
        }

        return [
            'code' => $code,
            'name' => $this->normalizeRequiredTranslations($data['name'] ?? []),
            'description' => $this->normalizeOptionalTranslations($data['description'] ?? null),
            'tool_category_id' => $categoryId,
            'serial_number' => $serialNumber,
            'quantity' => $quantity,
            'branch_id' => $branchId,
            'location_note' => $this->nullableString($data['location_note'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function normalizeRequiredTranslations(mixed $value): array
    {
        $translations = is_array($value) ? $value : [];
        $en = trim((string) ($translations['en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $en));

        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English tool name is required.')]]);
        }

        return ['en' => $en, 'ar' => $ar === '' ? $en : $ar];
    }

    private function normalizeOptionalTranslations(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $en = trim((string) ($value['en'] ?? ''));
        $ar = trim((string) ($value['ar'] ?? ''));

        if ($en === '' && $ar === '') {
            return null;
        }

        return ['en' => $en === '' ? $ar : $en, 'ar' => $ar === '' ? $en : $ar];
    }

    private function nullableUuid(mixed $value, string $field): ?string
    {
        $value = $this->nullableString($value);
        if ($value !== null && ! Str::isUuid($value)) {
            throw ValidationException::withMessages([$field => [__('Invalid reference.')]]);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }

    private function relations(): array
    {
        return ['category', 'branch', 'custodian', 'movements'];
    }
}
