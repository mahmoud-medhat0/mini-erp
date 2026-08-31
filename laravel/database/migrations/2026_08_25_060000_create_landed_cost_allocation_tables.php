<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_cost_allocation', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->nullable()->unique();
            $table->uuid('goods_receipt_id');
            $table->uuid('supplier_id');
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->date('allocation_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('allocation_method', 30)->default('by_value');
            $table->bigInteger('cost_amount_minor');
            $table->bigInteger('tax_amount_minor')->default(0);
            $table->bigInteger('total_amount_minor');
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('payable_entry_id')->nullable();
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

            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipt')->restrictOnDelete();
            $table->foreign('supplier_id')->references('id')->on('supplier')->restrictOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->restrictOnDelete();
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->restrictOnDelete();
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->nullOnDelete();
            $table->foreign('payable_entry_id')->references('id')->on('payable_entry')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'allocation_date']);
            $table->index(['goods_receipt_id']);
            $table->index(['supplier_id', 'allocation_date']);
            $table->index(['financial_period_id']);
        });

        Schema::create('landed_cost_allocation_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('landed_cost_allocation_id');
            $table->uuid('goods_receipt_line_id');
            $table->unsignedInteger('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->bigInteger('quantity_e6_snapshot');
            $table->bigInteger('receipt_value_minor_snapshot');
            $table->bigInteger('allocated_cost_minor')->default(0);
            $table->bigInteger('capitalized_amount_minor')->default(0);
            $table->bigInteger('expensed_amount_minor')->default(0);
            $table->uuid('stock_movement_id')->nullable();
            $table->timestamps();

            $table->foreign('landed_cost_allocation_id')->references('id')->on('landed_cost_allocation')->cascadeOnDelete();
            $table->foreign('goods_receipt_line_id')->references('id')->on('goods_receipt_line')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->restrictOnDelete();
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->restrictOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movement_ledger')->nullOnDelete();

            $table->unique(['landed_cost_allocation_id', 'line_no'], 'landed_cost_line_no_unique');
            $table->unique(['landed_cost_allocation_id', 'goods_receipt_line_id'], 'landed_cost_receipt_line_unique');
            $table->index(['goods_receipt_line_id']);
            $table->index(['product_id']);
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        $this->dropPostgresConstraints();

        Schema::dropIfExists('landed_cost_allocation_line');
        Schema::dropIfExists('landed_cost_allocation');
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE landed_cost_allocation DROP CONSTRAINT IF EXISTS landed_cost_allocation_status_check');
        DB::statement("ALTER TABLE landed_cost_allocation ADD CONSTRAINT landed_cost_allocation_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'cancelled'))");

        DB::statement('ALTER TABLE landed_cost_allocation DROP CONSTRAINT IF EXISTS landed_cost_allocation_method_check');
        DB::statement("ALTER TABLE landed_cost_allocation ADD CONSTRAINT landed_cost_allocation_method_check CHECK (allocation_method IN ('by_value', 'by_quantity', 'manual'))");

        DB::statement('ALTER TABLE landed_cost_allocation DROP CONSTRAINT IF EXISTS landed_cost_allocation_amounts_check');
        DB::statement('ALTER TABLE landed_cost_allocation ADD CONSTRAINT landed_cost_allocation_amounts_check CHECK (fx_rate_e6 = 1000000 AND cost_amount_minor > 0 AND tax_amount_minor >= 0 AND total_amount_minor = cost_amount_minor + tax_amount_minor)');

        DB::statement('ALTER TABLE landed_cost_allocation_line DROP CONSTRAINT IF EXISTS landed_cost_allocation_line_amounts_check');
        DB::statement('ALTER TABLE landed_cost_allocation_line ADD CONSTRAINT landed_cost_allocation_line_amounts_check CHECK (quantity_e6_snapshot > 0 AND receipt_value_minor_snapshot >= 0 AND allocated_cost_minor >= 0 AND capitalized_amount_minor >= 0 AND expensed_amount_minor >= 0 AND (capitalized_amount_minor + expensed_amount_minor = 0 OR capitalized_amount_minor + expensed_amount_minor = allocated_cost_minor))');

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_movement_type_check');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_movement_type_check CHECK (movement_type IN ('receipt', 'issue', 'reversal', 'scrap', 'transfer_out', 'transfer_in', 'adjustment', 'landed_cost'))");

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_quantity_non_zero_check');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_quantity_non_zero_check CHECK ((movement_type = 'landed_cost' AND quantity_delta_e6 = 0) OR (movement_type <> 'landed_cost' AND quantity_delta_e6 <> 0))");

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_direction_check');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_direction_check CHECK (
            (movement_type IN ('receipt', 'transfer_in') AND quantity_delta_e6 > 0 AND value_delta_minor > 0)
            OR (movement_type IN ('issue', 'transfer_out', 'scrap') AND quantity_delta_e6 < 0 AND value_delta_minor < 0)
            OR (movement_type = 'landed_cost' AND quantity_delta_e6 = 0 AND value_delta_minor > 0)
            OR (movement_type IN ('reversal', 'adjustment'))
        )");
    }

    private function dropPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE landed_cost_allocation_line DROP CONSTRAINT IF EXISTS landed_cost_allocation_line_amounts_check');
        DB::statement('ALTER TABLE landed_cost_allocation DROP CONSTRAINT IF EXISTS landed_cost_allocation_amounts_check');
        DB::statement('ALTER TABLE landed_cost_allocation DROP CONSTRAINT IF EXISTS landed_cost_allocation_method_check');
        DB::statement('ALTER TABLE landed_cost_allocation DROP CONSTRAINT IF EXISTS landed_cost_allocation_status_check');

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_direction_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_quantity_non_zero_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_movement_type_check');

        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_movement_type_check CHECK (movement_type IN ('receipt', 'issue', 'reversal', 'scrap', 'transfer_out', 'transfer_in', 'adjustment'))");
        DB::statement('ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_quantity_non_zero_check CHECK (quantity_delta_e6 <> 0)');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_direction_check CHECK (
            (movement_type IN ('receipt', 'transfer_in') AND quantity_delta_e6 > 0 AND value_delta_minor > 0)
            OR (movement_type IN ('issue', 'transfer_out', 'scrap') AND quantity_delta_e6 < 0 AND value_delta_minor < 0)
            OR (movement_type IN ('reversal', 'adjustment'))
        )");
    }
};
