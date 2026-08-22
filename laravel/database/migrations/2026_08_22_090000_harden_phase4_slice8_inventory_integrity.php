<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_balance') || ! Schema::hasTable('stock_movement_ledger')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->assertExistingRowsAreValid();

        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_quantity_non_negative_check');
        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_valuation_non_negative_check');
        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_average_cost_non_negative_check');

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_movement_type_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_quantity_non_zero_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_value_non_zero_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_running_balance_non_negative_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_direction_check');

        DB::statement('ALTER TABLE stock_balance ADD CONSTRAINT stock_balance_quantity_non_negative_check CHECK (quantity_e6 >= 0)');
        DB::statement('ALTER TABLE stock_balance ADD CONSTRAINT stock_balance_valuation_non_negative_check CHECK (valuation_amount_minor >= 0)');
        DB::statement('ALTER TABLE stock_balance ADD CONSTRAINT stock_balance_average_cost_non_negative_check CHECK (avg_unit_cost_e6 >= 0)');

        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_movement_type_check CHECK (movement_type IN ('receipt', 'issue', 'reversal'))");
        DB::statement('ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_quantity_non_zero_check CHECK (quantity_delta_e6 <> 0)');
        DB::statement('ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_value_non_zero_check CHECK (value_delta_minor <> 0)');
        DB::statement('ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_running_balance_non_negative_check CHECK (unit_cost_e6 >= 0 AND balance_quantity_e6 >= 0 AND balance_valuation_amount_minor >= 0)');
        DB::statement("ALTER TABLE stock_movement_ledger ADD CONSTRAINT stock_movement_ledger_direction_check CHECK (
            (movement_type = 'receipt' AND quantity_delta_e6 > 0 AND value_delta_minor > 0)
            OR (movement_type = 'issue' AND quantity_delta_e6 < 0 AND value_delta_minor < 0)
            OR (movement_type = 'reversal')
        )");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_direction_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_running_balance_non_negative_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_value_non_zero_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_quantity_non_zero_check');
        DB::statement('ALTER TABLE stock_movement_ledger DROP CONSTRAINT IF EXISTS stock_movement_ledger_movement_type_check');

        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_average_cost_non_negative_check');
        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_valuation_non_negative_check');
        DB::statement('ALTER TABLE stock_balance DROP CONSTRAINT IF EXISTS stock_balance_quantity_non_negative_check');
    }

    private function assertExistingRowsAreValid(): void
    {
        $invalidBalances = DB::table('stock_balance')
            ->where('quantity_e6', '<', 0)
            ->orWhere('valuation_amount_minor', '<', 0)
            ->orWhere('avg_unit_cost_e6', '<', 0)
            ->count();

        if ($invalidBalances > 0) {
            throw new RuntimeException('Cannot harden stock_balance constraints because invalid rows already exist.');
        }

        $invalidMovements = DB::table('stock_movement_ledger')
            ->whereNotIn('movement_type', ['receipt', 'issue', 'reversal'])
            ->orWhere('quantity_delta_e6', '=', 0)
            ->orWhere('value_delta_minor', '=', 0)
            ->orWhere('unit_cost_e6', '<', 0)
            ->orWhere('balance_quantity_e6', '<', 0)
            ->orWhere('balance_valuation_amount_minor', '<', 0)
            ->orWhere(function ($query): void {
                $query
                    ->where('movement_type', 'receipt')
                    ->where(function ($receipt): void {
                        $receipt->where('quantity_delta_e6', '<=', 0)->orWhere('value_delta_minor', '<=', 0);
                    });
            })
            ->orWhere(function ($query): void {
                $query
                    ->where('movement_type', 'issue')
                    ->where(function ($issue): void {
                        $issue->where('quantity_delta_e6', '>=', 0)->orWhere('value_delta_minor', '>=', 0);
                    });
            })
            ->count();

        if ($invalidMovements > 0) {
            throw new RuntimeException('Cannot harden stock_movement_ledger constraints because invalid rows already exist.');
        }
    }
};
