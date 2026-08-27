<?php

namespace App\Application\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Models\UnitOfMeasure;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class UnitOfMeasureService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function create(array $data, int|string|null $actorId = null): UnitOfMeasure
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => [__('Unit of measure code is required.')],
            ]);
        }

        if (UnitOfMeasure::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('Unit of measure code [:code] already exists.', ['code' => $code])],
            ]);
        }

        $uom = UnitOfMeasure::query()->create([
            'code' => $code,
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? $code,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 1,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'unit_of_measure',
            entityId: $uom->id,
            before: null,
            after: $uom->fresh()->toArray(),
        );

        return $uom;
    }

    public function update(string $id, array $data, int|string|null $actorId = null): UnitOfMeasure
    {
        /** @var UnitOfMeasure $uom */
        $uom = UnitOfMeasure::query()->findOrFail($id);
        $before = $uom->toArray();

        if (isset($data['lock_version'])) {
            $this->optimisticLock->verify($uom, (int) $data['lock_version']);
        }

        if (isset($data['code'])) {
            $code = strtoupper(trim((string) $data['code']));
            if ($code !== $uom->code && UnitOfMeasure::query()->where('code', $code)->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => [__('Unit of measure code [:code] already exists.', ['code' => $code])],
                ]);
            }
            $uom->code = $code;
        }

        if (isset($data['name'])) {
            $uom->name = $data['name'];
        }

        if (isset($data['symbol'])) {
            $uom->symbol = $data['symbol'];
        }

        if (isset($data['is_active'])) {
            $uom->is_active = (bool) $data['is_active'];
        }

        $uom->updated_by = $actorId;
        $uom->lock_version = ((int) $uom->lock_version) + 1;
        $uom->save();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'unit_of_measure',
            entityId: $uom->id,
            before: $before,
            after: $uom->fresh()->toArray(),
        );

        return $uom;
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        /** @var UnitOfMeasure $uom */
        $uom = UnitOfMeasure::query()->findOrFail($id);

        if ($uom->products()->exists()) {
            throw ValidationException::withMessages([
                'id' => [__('Cannot delete Unit of Measure [:code] because it is referenced by existing products.', ['code' => $uom->code])],
            ]);
        }

        $before = $uom->toArray();
        $uom->delete();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'delete',
            entityType: 'unit_of_measure',
            entityId: $id,
            before: $before,
            after: null,
        );
    }
}
