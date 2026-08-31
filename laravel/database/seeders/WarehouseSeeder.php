<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::query()->updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => ['en' => 'Main Warehouse', 'ar' => 'المخزن الرئيسي'],
                'branch_id' => null,
                'warehouse_type' => 'standard',
                'is_default' => true,
                'is_active' => true,
                'lock_version' => 1,
            ],
        );
    }
}
