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
        Schema::create('warehouse', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->uuid('branch_id')->nullable();
            $table->string('warehouse_type', 30)->default('standard');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branch')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('stock_location', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_id');
            $table->string('code', 50);
            $table->json('name');
            $table->string('location_type', 30)->default('standard');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouse')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'is_active']);
        });

        $defaultWarehouseId = $this->ensureDefaultWarehouse();

        Schema::table('stock_balance', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_balance', 'warehouse_id')) {
                $table->uuid('warehouse_id')->nullable()->after('id');
            }
        });

        Schema::table('stock_movement_ledger', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movement_ledger', 'warehouse_id')) {
                $table->uuid('warehouse_id')->nullable()->after('id');
            }
        });

        DB::table('stock_balance')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        $this->backfillStockMovementWarehouse($defaultWarehouseId);

        $this->dropStockBalanceLegacyUniqueIndex();

        Schema::table('stock_balance', function (Blueprint $table): void {
            $table->unique(['warehouse_id', 'product_id', 'currency'], 'stock_balance_warehouse_product_currency_unique');
            $table->index(['warehouse_id', 'product_id'], 'stock_balance_warehouse_product_index');
        });

        Schema::table('stock_movement_ledger', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'product_id', 'movement_date'], 'stock_movement_warehouse_product_date_index');
        });

        Schema::create('stock_transfer', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->nullable()->unique();
            $table->date('transfer_date');
            $table->uuid('source_warehouse_id');
            $table->uuid('destination_warehouse_id');
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('source_warehouse_id')->references('id')->on('warehouse')->restrictOnDelete();
            $table->foreign('destination_warehouse_id')->references('id')->on('warehouse')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'transfer_date']);
            $table->index(['source_warehouse_id', 'destination_warehouse_id']);
        });

        Schema::create('stock_transfer_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('stock_transfer_id');
            $table->unsignedInteger('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->bigInteger('quantity_e6');
            $table->bigInteger('issued_quantity_e6')->default(0);
            $table->bigInteger('received_quantity_e6')->default(0);
            $table->bigInteger('issued_value_minor')->default(0);
            $table->uuid('source_movement_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_transfer_id')->references('id')->on('stock_transfer')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->restrictOnDelete();
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->restrictOnDelete();
            $table->foreign('source_movement_id')->references('id')->on('stock_movement_ledger')->nullOnDelete();
            $table->unique(['stock_transfer_id', 'line_no']);
        });

        Schema::create('stock_transfer_receipt', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('stock_transfer_id');
            $table->date('receipt_date');
            $table->string('status', 30)->default('posted');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('stock_transfer_id')->references('id')->on('stock_transfer')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['stock_transfer_id', 'receipt_date']);
        });

        Schema::create('stock_transfer_receipt_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('stock_transfer_receipt_id');
            $table->uuid('stock_transfer_line_id');
            $table->bigInteger('quantity_e6');
            $table->bigInteger('value_minor');
            $table->uuid('destination_movement_id')->nullable();
            $table->timestamps();

            $table->foreign('stock_transfer_receipt_id')->references('id')->on('stock_transfer_receipt')->cascadeOnDelete();
            $table->foreign('stock_transfer_line_id')->references('id')->on('stock_transfer_line')->restrictOnDelete();
            $table->foreign('destination_movement_id')->references('id')->on('stock_movement_ledger')->nullOnDelete();
            $table->index(['stock_transfer_line_id']);
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        $this->dropPostgresConstraints();

        Schema::dropIfExists('stock_transfer_receipt_line');
        Schema::dropIfExists('stock_transfer_receipt');
        Schema::dropIfExists('stock_transfer_line');
        Schema::dropIfExists('stock_transfer');

        Schema::table('stock_movement_ledger', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_movement_ledger', 'warehouse_id')) {
                $table->dropIndex('stock_movement_warehouse_product_date_index');
                $table->dropColumn('warehouse_id');
            }
        });

        Schema::table('stock_balance', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_balance', 'warehouse_id')) {
                $table->dropUnique('stock_balance_warehouse_product_currency_unique');
                $table->dropIndex('stock_balance_warehouse_product_index');
                $table->dropColumn('warehouse_id');
                $table->unique(['product_id', 'currency']);
            }
        });

        Schema::dropIfExists('stock_location');
        Schema::dropIfExists('warehouse');
    }

    private function ensureDefaultWarehouse(): string
    {
        $existing = DB::table('warehouse')->where('code', 'MAIN')->value('id');
        if ($existing) {
            return (string) $existing;
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

    private function dropStockBalanceLegacyUniqueIndex(): void
    {
        try {
            Schema::table('stock_balance', function (Blueprint $table): void {
                $table->dropUnique('stock_balance_product_id_currency_unique');
            });
        } catch (Throwable) {
            // The legacy index may have already been removed in a restored environment.
        }
    }

    private function backfillStockMovementWarehouse(string $defaultWarehouseId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            DB::table('stock_movement_ledger')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);

            return;
        }

        DB::statement('ALTER TABLE stock_movement_ledger DISABLE TRIGGER trg_prevent_stock_movement_ledger_update');

        try {
            DB::table('stock_movement_ledger')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        } finally {
            DB::statement('ALTER TABLE stock_movement_ledger ENABLE TRIGGER trg_prevent_stock_movement_ledger_update');
        }
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_warehouse_fk');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_warehouse_fk');
        DB::statement('ALTER TABLE stock_balance ADD CONSTRAINT stock_balance_warehouse_fk FOREIGN KEY (warehouse_id) REFERENCES warehouse(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_warehouse_fk FOREIGN KEY (warehouse_id) REFERENCES warehouse(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE warehouse DROP CONSTRAINT IF EXISTS warehouse_type_check');
        DB::statement("ALTER TABLE warehouse ADD CONSTRAINT warehouse_type_check CHECK (warehouse_type IN ('standard', 'in_transit', 'quarantine', 'repair', 'scrap', 'supplier_return'))");

        DB::statement('ALTER TABLE stock_location DROP CONSTRAINT IF EXISTS stock_location_type_check');
        DB::statement("ALTER TABLE stock_location ADD CONSTRAINT stock_location_type_check CHECK (location_type IN ('standard', 'quarantine', 'repair', 'scrap', 'supplier_return'))");

        DB::statement('ALTER TABLE stock_transfer DROP CONSTRAINT IF EXISTS stock_transfer_status_check');
        DB::statement("ALTER TABLE stock_transfer ADD CONSTRAINT stock_transfer_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'issued', 'partially_received', 'received', 'cancelled'))");

        DB::statement('ALTER TABLE stock_transfer DROP CONSTRAINT IF EXISTS stock_transfer_distinct_warehouses_check');
        DB::statement('ALTER TABLE stock_transfer ADD CONSTRAINT stock_transfer_distinct_warehouses_check CHECK (source_warehouse_id <> destination_warehouse_id)');

        DB::statement('ALTER TABLE stock_transfer_line DROP CONSTRAINT IF EXISTS stock_transfer_line_quantity_check');
        DB::statement('ALTER TABLE stock_transfer_line ADD CONSTRAINT stock_transfer_line_quantity_check CHECK (quantity_e6 > 0 AND issued_quantity_e6 >= 0 AND received_quantity_e6 >= 0 AND issued_value_minor >= 0 AND issued_quantity_e6 <= quantity_e6 AND received_quantity_e6 <= issued_quantity_e6)');

        DB::statement('ALTER TABLE stock_transfer_receipt DROP CONSTRAINT IF EXISTS stock_transfer_receipt_status_check');
        DB::statement("ALTER TABLE stock_transfer_receipt ADD CONSTRAINT stock_transfer_receipt_status_check CHECK (status IN ('posted'))");

        DB::statement('ALTER TABLE stock_transfer_receipt_line DROP CONSTRAINT IF EXISTS stock_transfer_receipt_line_amount_check');
        DB::statement('ALTER TABLE stock_transfer_receipt_line ADD CONSTRAINT stock_transfer_receipt_line_amount_check CHECK (quantity_e6 > 0 AND value_minor > 0)');

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_movement_type_check');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_movement_type_check CHECK (movement_type IN ('receipt', 'issue', 'reversal', 'scrap', 'transfer_out', 'transfer_in', 'adjustment'))");

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_direction_check');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_direction_check CHECK (
            (movement_type IN ('receipt', 'transfer_in') AND quantity_delta_e6 > 0 AND value_delta_minor > 0)
            OR (movement_type IN ('issue', 'transfer_out', 'scrap') AND quantity_delta_e6 < 0 AND value_delta_minor < 0)
            OR (movement_type IN ('reversal', 'adjustment'))
        )");
    }

    private function dropPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_direction_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_movement_type_check');
        DB::statement('ALTER TABLE stock_transfer_receipt_line DROP CONSTRAINT IF EXISTS stock_transfer_receipt_line_amount_check');
        DB::statement('ALTER TABLE stock_transfer_receipt DROP CONSTRAINT IF EXISTS stock_transfer_receipt_status_check');
        DB::statement('ALTER TABLE stock_transfer_line DROP CONSTRAINT IF EXISTS stock_transfer_line_quantity_check');
        DB::statement('ALTER TABLE stock_transfer DROP CONSTRAINT IF EXISTS stock_transfer_distinct_warehouses_check');
        DB::statement('ALTER TABLE stock_transfer DROP CONSTRAINT IF EXISTS stock_transfer_status_check');
        DB::statement('ALTER TABLE stock_location DROP CONSTRAINT IF EXISTS stock_location_type_check');
        DB::statement('ALTER TABLE warehouse DROP CONSTRAINT IF EXISTS warehouse_type_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_warehouse_fk');
        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_warehouse_fk');

        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_movement_type_check CHECK (movement_type IN ('receipt', 'issue', 'reversal'))");
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_direction_check CHECK (
            (movement_type = 'receipt' AND quantity_delta_e6 > 0 AND value_delta_minor > 0)
            OR (movement_type = 'issue' AND quantity_delta_e6 < 0 AND value_delta_minor < 0)
            OR (movement_type = 'reversal')
        )");
    }
};
