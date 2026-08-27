<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaultWarehouseId = $this->ensureDefaultWarehouse();

        $this->addWarehouseColumn('delivery_note', 'sales_order_id');
        $this->addWarehouseColumn('goods_receipt', 'purchase_order_id');
        $this->addWarehouseColumn('sales_return', 'delivery_note_id');
        $this->addWarehouseColumn('purchase_return', 'goods_receipt_id');

        DB::table('delivery_note')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        DB::table('goods_receipt')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        DB::table('sales_return')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        DB::table('purchase_return')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE delivery_note ALTER COLUMN warehouse_id SET NOT NULL');
            DB::statement('ALTER TABLE goods_receipt ALTER COLUMN warehouse_id SET NOT NULL');
            DB::statement('ALTER TABLE sales_return ALTER COLUMN warehouse_id SET NOT NULL');
            DB::statement('ALTER TABLE purchase_return ALTER COLUMN warehouse_id SET NOT NULL');
        }
    }

    public function down(): void
    {
        $this->dropWarehouseColumn('purchase_return');
        $this->dropWarehouseColumn('sales_return');
        $this->dropWarehouseColumn('goods_receipt');
        $this->dropWarehouseColumn('delivery_note');
    }

    private function addWarehouseColumn(string $tableName, string $afterColumn): void
    {
        if (! Schema::hasColumn($tableName, 'warehouse_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $afterColumn): void {
                $table->uuid('warehouse_id')->nullable()->after($afterColumn);
                $table->foreign('warehouse_id', "{$tableName}_warehouse_id_foreign")
                    ->references('id')
                    ->on('warehouse')
                    ->restrictOnDelete();
                $table->index('warehouse_id', "{$tableName}_warehouse_id_index");
            });
        }
    }

    private function dropWarehouseColumn(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'warehouse_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_warehouse_id_foreign");
                $table->dropIndex("{$tableName}_warehouse_id_index");
                $table->dropColumn('warehouse_id');
            });
        }
    }

    private function ensureDefaultWarehouse(): string
    {
        $existing = DB::table('warehouse')->where('is_default', true)->orderBy('created_at')->first();
        if ($existing) {
            return (string) $existing->id;
        }

        $fallback = DB::table('warehouse')->where('code', 'MAIN')->first();
        if ($fallback) {
            DB::table('warehouse')->where('id', $fallback->id)->update([
                'is_default' => true,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (string) $fallback->id;
        }

        $id = (string) Str::uuid();
        DB::table('warehouse')->insert([
            'id' => $id,
            'code' => 'MAIN',
            'name' => json_encode(['en' => 'Main Warehouse', 'ar' => 'المخزن الرئيسي'], JSON_UNESCAPED_UNICODE),
            'branch_id' => null,
            'warehouse_type' => 'standard',
            'is_default' => true,
            'is_active' => true,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
};
