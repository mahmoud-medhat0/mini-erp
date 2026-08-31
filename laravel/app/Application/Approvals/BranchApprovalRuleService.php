<?php

namespace App\Application\Approvals;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\BranchApprovalRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class BranchApprovalRuleService
{
    public const DOCUMENT_TYPES = ['stock_transfer', 'stock_count', 'stock_adjustment'];

    public const BRANCH_MATCHES = ['document', 'source', 'destination', 'either'];

    public const DEFAULT_REQUIRED_PERMISSION = 'approvals.override';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'rules' => BranchApprovalRule::query()
                ->with('branch')
                ->orderBy('document_type')
                ->orderBy('branch_match')
                ->orderByRaw('branch_id IS NULL DESC')
                ->get(),
            'branches' => Branch::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'is_active']),
            'documentTypes' => self::DOCUMENT_TYPES,
            'branchMatches' => self::BRANCH_MATCHES,
            'permissionOptions' => $this->configurablePermissions(),
        ];
    }

    public function create(array $data, int|string|null $actorId = null): BranchApprovalRule
    {
        return DB::transaction(function () use ($data, $actorId): BranchApprovalRule {
            $payload = $this->validatedPayload($data);
            $this->assertUniqueRule($payload['document_type'], $payload['branch_match'], $payload['branch_id']);

            /** @var BranchApprovalRule $rule */
            $rule = BranchApprovalRule::query()->create([
                ...$payload,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'branch_approval_rule.create',
                entityType: 'branch_approval_rule',
                entityId: $rule->id,
                before: null,
                after: $rule->load('branch')->toArray(),
            );

            return $rule->fresh('branch');
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): BranchApprovalRule
    {
        return DB::transaction(function () use ($id, $data, $actorId): BranchApprovalRule {
            /** @var BranchApprovalRule $rule */
            $rule = BranchApprovalRule::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            $before = $rule->load('branch')->toArray();
            $payload = $this->validatedPayload($data);
            $this->assertUniqueRule($payload['document_type'], $payload['branch_match'], $payload['branch_id'], $rule->id);

            $rule->update([
                ...$payload,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'branch_approval_rule.update',
                entityType: 'branch_approval_rule',
                entityId: $rule->id,
                before: $before,
                after: $rule->fresh('branch')?->toArray(),
            );

            return $rule->fresh('branch');
        });
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var BranchApprovalRule $rule */
            $rule = BranchApprovalRule::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            $before = $rule->load('branch')->toArray();
            $rule->delete();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'branch_approval_rule.delete',
                entityType: 'branch_approval_rule',
                entityId: $id,
                before: $before,
                after: null,
            );
        });
    }

    /**
     * @param  array{document?: string|null, source?: string|null, destination?: string|null}  $branchIds
     */
    public function assertUserMayApprove(string $documentType, array $branchIds, int|string|null $actorId): void
    {
        $this->assertDocumentType($documentType);

        $rules = BranchApprovalRule::query()
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        /** @var User|null $user */
        $user = $actorId ? User::query()->find($actorId) : null;

        foreach ($rules as $rule) {
            if (! $this->ruleApplies($rule, $branchIds)) {
                continue;
            }

            if ($user && $user->can($rule->required_permission)) {
                continue;
            }

            throw ValidationException::withMessages([
                'approval' => [__('This branch approval rule requires permission :permission.', ['permission' => $rule->required_permission])],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function configurablePermissions(): array
    {
        $permissionNames = Permission::query()
            ->where(function ($query): void {
                $query->where('name', 'like', '%.approve')
                    ->orWhere('name', self::DEFAULT_REQUIRED_PERMISSION)
                    ->orWhere('name', 'settings.configure');
            })
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return array_values(array_unique([
            self::DEFAULT_REQUIRED_PERMISSION,
            ...$permissionNames,
        ]));
    }

    private function validatedPayload(array $data): array
    {
        $documentType = (string) ($data['document_type'] ?? '');
        $branchMatch = (string) ($data['branch_match'] ?? 'document');
        $branchId = filled($data['branch_id'] ?? null) ? (string) $data['branch_id'] : null;
        $requiredPermission = (string) ($data['required_permission'] ?? self::DEFAULT_REQUIRED_PERMISSION);

        $this->assertDocumentType($documentType);
        $this->assertBranchMatch($branchMatch);
        $this->assertBranchAllowedForMatch($documentType, $branchMatch);
        $this->assertPermissionExists($requiredPermission);

        if ($branchId !== null && ! Branch::query()->where('id', $branchId)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch does not exist.')]]);
        }

        return [
            'document_type' => $documentType,
            'branch_match' => $branchMatch,
            'branch_id' => $branchId,
            'required_permission' => $requiredPermission,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function assertDocumentType(string $documentType): void
    {
        if (! in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => [__('Unsupported approval document type.')]]);
        }
    }

    private function assertBranchMatch(string $branchMatch): void
    {
        if (! in_array($branchMatch, self::BRANCH_MATCHES, true)) {
            throw ValidationException::withMessages(['branch_match' => [__('Unsupported branch match mode.')]]);
        }
    }

    private function assertBranchAllowedForMatch(string $documentType, string $branchMatch): void
    {
        if (in_array($documentType, ['stock_count', 'stock_adjustment'], true) && in_array($branchMatch, ['source', 'destination'], true)) {
            throw ValidationException::withMessages(['branch_match' => [__('This document type uses document branch matching only.')]]);
        }
    }

    private function assertPermissionExists(string $permission): void
    {
        if (! Permission::query()->where('name', $permission)->exists()) {
            throw ValidationException::withMessages(['required_permission' => [__('Selected permission does not exist.')]]);
        }
    }

    private function assertUniqueRule(string $documentType, string $branchMatch, ?string $branchId, ?string $ignoreId = null): void
    {
        $exists = BranchApprovalRule::query()
            ->where('document_type', $documentType)
            ->where('branch_match', $branchMatch)
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['branch_id' => [__('An approval rule already exists for this document, branch match, and branch scope.')]]);
        }
    }

    /**
     * @param  array{document?: string|null, source?: string|null, destination?: string|null}  $branchIds
     */
    private function ruleApplies(BranchApprovalRule $rule, array $branchIds): bool
    {
        $candidates = match ($rule->branch_match) {
            'source' => [$branchIds['source'] ?? null],
            'destination' => [$branchIds['destination'] ?? null],
            'either' => array_values(array_filter([
                $branchIds['document'] ?? null,
                $branchIds['source'] ?? null,
                $branchIds['destination'] ?? null,
            ])),
            default => [$branchIds['document'] ?? null],
        };

        if ($rule->branch_id === null) {
            return true;
        }

        return in_array((string) $rule->branch_id, array_map(static fn ($id): string => (string) $id, $candidates), true);
    }
}
