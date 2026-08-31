<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update accounting mapping key check constraint if PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable', 'sales_revenue', 'purchase_expense', 'inventory_asset', 'grni_clearing', 'cogs'))");
        }

        Schema::create('stock_balance', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->string('currency', 3);
            $table->bigInteger('quantity_e6')->default(0);
            $table->bigInteger('valuation_amount_minor')->default(0);
            $table->bigInteger('avg_unit_cost_e6')->default(0);
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');

            $table->unique(['product_id', 'currency']);
            $table->index(['product_id']);
        });

        Schema::create('stock_movement_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('movement_date');
            $table->string('source_type', 50);
            $table->uuid('source_id');
            $table->uuid('source_line_id')->nullable();
            $table->string('movement_type', 30); // receipt, issue, reversal
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->string('currency', 3);

            $table->bigInteger('quantity_delta_e6');
            $table->bigInteger('value_delta_minor');
            $table->bigInteger('unit_cost_e6')->default(0);

            $table->bigInteger('balance_quantity_e6');
            $table->bigInteger('balance_valuation_amount_minor');

            $table->uuid('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['source_type', 'source_line_id', 'movement_type']);
            $table->index(['product_id', 'movement_date']);
        });

        $this->createImmutabilityTriggers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropImmutabilityTriggers();

        Schema::dropIfExists('stock_movement_ledger');
        Schema::dropIfExists('stock_balance');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable', 'sales_revenue', 'purchase_expense'))");
        }
    }

    private function createImmutabilityTriggers(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                CREATE OR REPLACE FUNCTION prevent_stock_movement_ledger_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'stock_movement_ledger is append-only. Updates and deletions are forbidden.';
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::statement('
                CREATE TRIGGER trg_prevent_stock_movement_ledger_update
                BEFORE UPDATE ON stock_movement_ledger
                FOR EACH ROW EXECUTE FUNCTION prevent_stock_movement_ledger_mutation();
            ');

            DB::statement('
                CREATE TRIGGER trg_prevent_stock_movement_ledger_delete
                BEFORE DELETE ON stock_movement_ledger
                FOR EACH ROW EXECUTE FUNCTION prevent_stock_movement_ledger_mutation();
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement("
                CREATE TRIGGER trg_prevent_stock_movement_ledger_update
                BEFORE UPDATE ON stock_movement_ledger
                BEGIN
                    SELECT RAISE(ABORT, 'stock_movement_ledger is append-only. Updates and deletions are forbidden.');
                END;
            ");

            DB::statement("
                CREATE TRIGGER trg_prevent_stock_movement_ledger_delete
                BEFORE DELETE ON stock_movement_ledger
                BEGIN
                    SELECT RAISE(ABORT, 'stock_movement_ledger is append-only. Updates and deletions are forbidden.');
                END;
            ");
        }
    }

    private function dropImmutabilityTriggers(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_prevent_stock_movement_ledger_update ON stock_movement_ledger;');
            DB::statement('DROP TRIGGER IF EXISTS trg_prevent_stock_movement_ledger_delete ON stock_movement_ledger;');
            DB::statement('DROP FUNCTION IF EXISTS prevent_stock_movement_ledger_mutation();');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_prevent_stock_movement_ledger_update;');
            DB::statement('DROP TRIGGER IF EXISTS trg_prevent_stock_movement_ledger_delete;');
        }
    }
};
