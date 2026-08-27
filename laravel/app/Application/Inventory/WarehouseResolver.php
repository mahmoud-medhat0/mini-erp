<?php

namespace App\Application\Inventory;

use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;

class WarehouseResolver
{
    public function active(?string $warehouseId, string $field = 'warehouse_id'): Warehouse
    {
        if ($warehouseId) {
            /** @var Warehouse|null $warehouse */
            $warehouse = Warehouse::query()->where('id', $warehouseId)->first();
            if (! $warehouse || ! $warehouse->is_active) {
                throw ValidationException::withMessages([$field => [__('Selected warehouse is invalid or inactive.')]]);
            }

            return $warehouse;
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => ['en' => 'Main Warehouse', 'ar' => 'المخزن الرئيسي'],
                'warehouse_type' => 'standard',
                'is_default' => true,
                'is_active' => true,
                'lock_version' => 1,
            ],
        );

        if (! $warehouse->is_active) {
            throw ValidationException::withMessages([$field => [__('Default warehouse is inactive.')]]);
        }

        return $warehouse;
    }
}
