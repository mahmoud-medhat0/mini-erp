<?php

namespace App\Application\CostCenters;

use App\Domain\Audit\AuditLogger;
use App\Models\CostCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CostCenterService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, int|string|null $actorId = null): CostCenter
    {
        return DB::transaction(function () use ($data, $actorId): CostCenter {
            $code = strtoupper(trim((string) $data['code']));
            $this->assertUniqueCode($code);

            $category = ! empty($data['category']) ? (string) $data['category'] : null;
            $this->assertCategory($category);

            /** @var CostCenter $costCenter */
            $costCenter = CostCenter::query()->create([
                'code' => $code,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $category,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'lock_version' => 1,
                'created_by' => $this->actorId($actorId),
                'updated_by' => $this->actorId($actorId),
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'cost_center.create',
                'cost_center',
                (string) $costCenter->id,
                after: $costCenter->fresh()->toArray()
            );

            return $costCenter->fresh();
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): CostCenter
    {
        return DB::transaction(function () use ($id, $data, $actorId): CostCenter {
            /** @var CostCenter $costCenter */
            $costCenter = CostCenter::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $costCenter->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim((string) $data['code']));
                if ($data['code'] !== $costCenter->code) {
                    $this->assertUniqueCode((string) $data['code'], $costCenter->id);
                }
            }

            if (array_key_exists('category', $data)) {
                $category = ! empty($data['category']) ? (string) $data['category'] : null;
                $this->assertCategory($category);
                $data['category'] = $category;
            }

            $before = $costCenter->toArray();
            $updates = [];

            foreach (['code', 'name', 'description', 'category', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            $updates['updated_by'] = $this->actorId($actorId);
            $updates['lock_version'] = $costCenter->lock_version + 1;
            $costCenter->update($updates);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'cost_center.update',
                'cost_center',
                (string) $costCenter->id,
                before: $before,
                after: $costCenter->fresh()->toArray()
            );

            return $costCenter->fresh();
        });
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var CostCenter $costCenter */
            $costCenter = CostCenter::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($costCenter->journalLines()->exists() || $costCenter->ledgerEntries()->exists() || $costCenter->expenseLines()->exists()) {
                throw ValidationException::withMessages([
                    'cost_center' => [__('Cannot delete cost center referenced by expense lines, journal lines, or ledger entries.')],
                ]);
            }

            $before = $costCenter->toArray();
            $costCenter->delete();

            $this->auditLogger->record(
                $this->actorId($actorId),
                'cost_center.delete',
                'cost_center',
                $id,
                before: $before
            );
        });
    }

    private function assertUniqueCode(string $code, ?string $ignoreId = null): void
    {
        $exists = CostCenter::query()
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => [__('Cost center code [:code] already exists.', ['code' => $code])],
            ]);
        }
    }

    private function assertCategory(?string $category): void
    {
        if ($category === null || $category === '') {
            return;
        }

        if (! in_array($category, ['administrative', 'sales', 'operations', 'finance', 'other'], true)) {
            throw ValidationException::withMessages([
                'category' => [__('Invalid cost center category [:category].', ['category' => $category])],
            ]);
        }
    }

    private function actorId(int|string|null $actorId): ?int
    {
        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
