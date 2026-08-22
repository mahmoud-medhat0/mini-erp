<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE financial_statement_line ADD CONSTRAINT financial_statement_line_cash_flow_activity_check CHECK (cash_flow_activity IS NULL OR cash_flow_activity IN ('operating', 'investing', 'financing'))");
        DB::statement("ALTER TABLE account ADD CONSTRAINT account_cash_flow_activity_check CHECK (cash_flow_activity IS NULL OR cash_flow_activity IN ('operating', 'investing', 'financing'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE account DROP CONSTRAINT IF EXISTS account_cash_flow_activity_check');
        DB::statement('ALTER TABLE financial_statement_line DROP CONSTRAINT IF EXISTS financial_statement_line_cash_flow_activity_check');
    }
};
