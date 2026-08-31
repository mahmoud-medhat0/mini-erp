<?php

namespace App\Application\Inventory;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    public const WAREHOUSE_TYPES = ['standard', 'in_transit', 'quarantine', 'repair', 'scrap', 'supplier_return'];

    public const LOCATION_TYPES = ['standard', 'quarantine', 'repair', 'scrap', 'supplier_return'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createWarehouse(array $data, ?int $actorId = null): Warehouse
    {
        return DB::transaction(function () use ($data, $actorId): Warehouse {
            $code = $this->normalizedCode($data['code'] ?? '', 'code');

            if (Warehouse::query()->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => [__('Warehouse code [:code] already exists.', ['code' => $code])]]);
            }

            $this->assertBranchIsValid($data['branch_id'] ?? null);
            $type = $this->warehouseType($data['warehouse_type'] ?? 'standard');

            if (($data['is_default'] ?? false) === true) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->create([
                'code' => $code,
                'name' => $this->translatableName($data['name'] ?? null),
                'branch_id' => $data['branch_id'] ?? null,
                'warehouse_type' => $type,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'warehouse.create',
                entityType: 'warehouse',
                entityId: $warehouse->id,
                before: null,
                after: $warehouse->fresh(['branch'])->toArray(),
            );

            return $warehouse->fresh(['branch']);
        });
    }

    public function updateWarehouse(string $id, array $data, ?int $actorId = null): Warehouse
    {
        return DB::transaction(function () use ($id, $data, $actorId): Warehouse {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            $before = $warehouse->fresh(['branch'])->toArray();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $warehouse->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            if (isset($data['code'])) {
                $code = $this->normalizedCode($data['code'], 'code');
                if ($code !== $warehouse->code && Warehouse::query()->where('code', $code)->where('id', '!=', $id)->exists()) {
                    throw ValidationException::withMessages(['code' => [__('Warehouse code [:code] already exists.', ['code' => $code])]]);
                }
                $warehouse->code = $code;
            }

            if (array_key_exists('name', $data)) {
                $warehouse->name = $this->translatableName($data['name']);
            }

            if (array_key_exists('branch_id', $data)) {
                $this->assertBranchIsValid($data['branch_id'] ?? null);
                $warehouse->branch_id = $data['branch_id'] ?: null;
            }

            if (array_key_exists('warehouse_type', $data)) {
                $warehouse->warehouse_type = $this->warehouseType($data['warehouse_type']);
            }

            if (array_key_exists('is_active', $data)) {
                $warehouse->is_active = (bool) $data['is_active'];
            }

            if (($data['is_default'] ?? false) === true && ! $warehouse->is_default) {
                Warehouse::query()->where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
                $warehouse->is_default = true;
            }

            $warehouse->updated_by = $actorId;
            $warehouse->lock_version = ((int) $warehouse->lock_version) + 1;
            $warehouse->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'warehouse.update',
                entityType: 'warehouse',
                entityId: $warehouse->id,
                before: $before,
                after: $warehouse->fresh(['branch'])->toArray(),
            );

            return $warehouse->fresh(['branch']);
        });
    }

    public function deleteWarehouse(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($warehouse->is_default) {
                throw ValidationException::withMessages(['warehouse' => [__('Default warehouse cannot be deleted.')]]);
            }

            if (StockBalance::query()->where('warehouse_id', $warehouse->id)->exists()) {
                throw ValidationException::withMessages(['warehouse' => [__('Warehouse has stock balances and cannot be deleted.')]]);
            }

            if (StockTransfer::query()
                ->where('source_warehouse_id', $warehouse->id)
                ->orWhere('destination_warehouse_id', $warehouse->id)
                ->exists()) {
                throw ValidationException::withMessages(['warehouse' => [__('Warehouse is used by stock transfers and cannot be deleted.')]]);
            }

            $before = $warehouse->fresh(['branch'])->toArray();
            $warehouse->delete();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'warehouse.delete',
                entityType: 'warehouse',
                entityId: $id,
                before: $before,
                after: null,
            );
        });
    }

    public function createLocation(array $data, ?int $actorId = null): StockLocation
    {
        return DB::transaction(function () use ($data, $actorId): StockLocation {
            $warehouse = $this->activeWarehouse($data['warehouse_id'] ?? null, 'warehouse_id');
            $code = $this->normalizedCode($data['code'] ?? '', 'code');

            if (StockLocation::query()->where('warehouse_id', $warehouse->id)->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => [__('Location code [:code] already exists in the selected warehouse.', ['code' => $code])]]);
            }

            /** @var StockLocation $location */
            $location = StockLocation::query()->create([
                'warehouse_id' => $warehouse->id,
                'code' => $code,
                'name' => $this->translatableName($data['name'] ?? null),
                'location_type' => $this->locationType($data['location_type'] ?? 'standard'),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_location.create',
                entityType: 'stock_location',
                entityId: $location->id,
                before: null,
                after: $location->fresh(['warehouse'])->toArray(),
            );

            return $location->fresh(['warehouse']);
        });
    }

    public function updateLocation(string $id, array $data, ?int $actorId = null): StockLocation
    {
        return DB::transaction(function () use ($id, $data, $actorId): StockLocation {
            /** @var StockLocation $location */
            $location = StockLocation::query()->where('id', $id)->lockForUpdate()->firstOrFail();
            $before = $location->fresh(['warehouse'])->toArray();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $location->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            if (array_key_exists('warehouse_id', $data)) {
                $location->warehouse_id = $this->activeWarehouse($data['warehouse_id'], 'warehouse_id')->id;
            }

            if (isset($data['code'])) {
                $code = $this->normalizedCode($data['code'], 'code');
                if ($code !== $location->code && StockLocation::query()->where('warehouse_id', $location->warehouse_id)->where('code', $code)->where('id', '!=', $id)->exists()) {
                    throw ValidationException::withMessages(['code' => [__('Location code [:code] already exists in the selected warehouse.', ['code' => $code])]]);
                }
                $location->code = $code;
            }

            if (array_key_exists('name', $data)) {
                $location->name = $this->translatableName($data['name']);
            }

            if (array_key_exists('location_type', $data)) {
                $location->location_type = $this->locationType($data['location_type']);
            }

            if (array_key_exists('is_active', $data)) {
                $location->is_active = (bool) $data['is_active'];
            }

            $location->updated_by = $actorId;
            $location->lock_version = ((int) $location->lock_version) + 1;
            $location->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_location.update',
                entityType: 'stock_location',
                entityId: $location->id,
                before: $before,
                after: $location->fresh(['warehouse'])->toArray(),
            );

            return $location->fresh(['warehouse']);
        });
    }

    private function activeWarehouse(?string $warehouseId, string $field): Warehouse
    {
        /** @var Warehouse|null $warehouse */
        $warehouse = $warehouseId ? Warehouse::query()->where('id', $warehouseId)->first() : null;
        if (! $warehouse || ! $warehouse->is_active) {
            throw ValidationException::withMessages([$field => [__('Selected warehouse is invalid or inactive.')]]);
        }

        return $warehouse;
    }

    private function assertBranchIsValid(?string $branchId): void
    {
        if (! $branchId) {
            return;
        }

        /** @var Branch|null $branch */
        $branch = Branch::query()->where('id', $branchId)->first();
        if (! $branch || ! $branch->is_active) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is invalid or inactive.')]]);
        }
    }

    private function translatableName(mixed $name): array
    {
        if (is_array($name)) {
            $en = trim((string) ($name['en'] ?? ''));
            $ar = trim((string) ($name['ar'] ?? $en));
        } else {
            $en = trim((string) $name);
            $ar = $en;
        }

        if ($en === '') {
            throw ValidationException::withMessages(['name' => [__('Name is required.')]]);
        }

        return ['en' => $en, 'ar' => $ar !== '' ? $ar : $en];
    }

    private function warehouseType(string $type): string
    {
        if (! in_array($type, self::WAREHOUSE_TYPES, true)) {
            throw ValidationException::withMessages(['warehouse_type' => [__('Invalid warehouse type.')]]);
        }

        return $type;
    }

    private function locationType(string $type): string
    {
        if (! in_array($type, self::LOCATION_TYPES, true)) {
            throw ValidationException::withMessages(['location_type' => [__('Invalid stock location type.')]]);
        }

        return $type;
    }

    private function normalizedCode(string $code, string $field): string
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            throw ValidationException::withMessages([$field => [__('Code is required.')]]);
        }

        return $normalized;
    }
}
