<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateMappingConstraint(includeAdjustmentKeys: true);

        Schema::create('stock_adjustment', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->nullable()->unique();
            $table->date('adjustment_date');
            $table->uuid('warehouse_id');
            $table->string('currency', 3);
            $table->string('status', 30)->default('draft');
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->bigInteger('total_value_delta_minor')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouse')->restrictOnDelete();
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['warehouse_id', 'adjustment_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'adjustment_date']);
        });

        Schema::create('stock_adjustment_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('stock_adjustment_id');
            $table->unsignedInteger('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->bigInteger('quantity_delta_e6');
            $table->bigInteger('unit_cost_minor')->nullable();
            $table->bigInteger('value_delta_minor')->nullable();
            $table->uuid('stock_movement_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustment')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->restrictOnDelete();
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->restrictOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movement_ledger')->nullOnDelete();
            $table->unique(['stock_adjustment_id', 'line_no']);
        });

        Schema::create('stock_count', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->nullable()->unique();
            $table->date('count_date');
            $table->uuid('warehouse_id');
            $table->string('currency', 3);
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('stock_adjustment_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouse')->restrictOnDelete();
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();
            $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustment')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['warehouse_id', 'count_date']);
            $table->index(['status', 'count_date']);
        });

        Schema::create('stock_count_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('stock_count_id');
            $table->unsignedInteger('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->bigInteger('expected_quantity_e6')->default(0);
            $table->bigInteger('counted_quantity_e6')->default(0);
            $table->bigInteger('variance_quantity_e6')->default(0);
            $table->bigInteger('unit_cost_minor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_count_id')->references('id')->on('stock_count')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->restrictOnDelete();
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->restrictOnDelete();
            $table->unique(['stock_count_id', 'line_no']);
            $table->unique(['stock_count_id', 'product_id']);
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        $this->dropPostgresConstraints();

        Schema::dropIfExists('stock_adjustment_line');
        Schema::dropIfExists('stock_count_line');
        Schema::dropIfExists('stock_count');
        Schema::dropIfExists('stock_adjustment');

        $this->updateMappingConstraint(includeAdjustmentKeys: false);
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE stock_count ADD CONSTRAINT stock_count_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'cancelled'))");
        DB::statement("ALTER TABLE stock_adjustment ADD CONSTRAINT stock_adjustment_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE stock_count_line ADD CONSTRAINT stock_count_line_quantity_check CHECK (expected_quantity_e6 >= 0 AND counted_quantity_e6 >= 0 AND variance_quantity_e6 = counted_quantity_e6 - expected_quantity_e6)');
        DB::statement('ALTER TABLE stock_count_line ADD CONSTRAINT stock_count_line_unit_cost_check CHECK (unit_cost_minor IS NULL OR unit_cost_minor > 0)');
        DB::statement('ALTER TABLE stock_adjustment_line ADD CONSTRAINT stock_adjustment_line_quantity_check CHECK (quantity_delta_e6 <> 0)');
        DB::statement('ALTER TABLE stock_adjustment_line ADD CONSTRAINT stock_adjustment_line_unit_cost_check CHECK (unit_cost_minor IS NULL OR unit_cost_minor > 0)');
        DB::statement('ALTER TABLE stock_adjustment_line ADD CONSTRAINT stock_adjustment_line_value_check CHECK (value_delta_minor IS NULL OR value_delta_minor <> 0)');
    }

    private function dropPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_adjustment_line DROP CONSTRAINT IF EXISTS stock_adjustment_line_value_check');
        DB::statement('ALTER TABLE stock_adjustment_line DROP CONSTRAINT IF EXISTS stock_adjustment_line_unit_cost_check');
        DB::statement('ALTER TABLE stock_adjustment_line DROP CONSTRAINT IF EXISTS stock_adjustment_line_quantity_check');
        DB::statement('ALTER TABLE stock_count_line DROP CONSTRAINT IF EXISTS stock_count_line_unit_cost_check');
        DB::statement('ALTER TABLE stock_count_line DROP CONSTRAINT IF EXISTS stock_count_line_quantity_check');
        DB::statement('ALTER TABLE stock_adjustment DROP CONSTRAINT IF EXISTS stock_adjustment_status_check');
        DB::statement('ALTER TABLE stock_count DROP CONSTRAINT IF EXISTS stock_count_status_check');
    }

    private function updateMappingConstraint(bool $includeAdjustmentKeys): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $keys = [
            'ar_control',
            'ap_control',
            'opening_balance_offset',
            'cheques_under_collection',
            'cheques_payable',
            'sales_revenue',
            'purchase_expense',
            'inventory_asset',
            'grni_clearing',
            'cogs',
            'sales_returns',
            'inventory_return_variance',
            'inventory_scrap_loss',
            'purchase_returns_allowances',
            'output_tax_payable',
            'input_tax_receivable',
            'fixed_asset_cost',
            'accumulated_depreciation',
            'depreciation_expense',
            'fixed_asset_disposal_gain',
            'fixed_asset_disposal_loss',
            'fixed_asset_clearing',
        ];

        if ($includeAdjustmentKeys) {
            $keys[] = 'inventory_adjustment_gain';
            $keys[] = 'inventory_adjustment_loss';
        }

        $quotedKeys = collect($keys)
            ->map(fn (string $key): string => "'{$key}'")
            ->implode(', ');

        DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
        DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ({$quotedKeys}))");
    }
};
