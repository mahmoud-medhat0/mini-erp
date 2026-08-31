<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journal_line', 'project_id')) {
            Schema::table('journal_line', function (Blueprint $table): void {
                $table->uuid('project_id')->nullable()->after('branch_id');
                $table->foreign('project_id', 'journal_line_project_id_foreign')
                    ->references('id')
                    ->on('project')
                    ->restrictOnDelete();
                $table->index('project_id', 'journal_line_project_id_index');
            });
        }

        if (! Schema::hasColumn('journal_line', 'cost_center_id')) {
            Schema::table('journal_line', function (Blueprint $table): void {
                $table->uuid('cost_center_id')->nullable()->after('project_id');
                $table->foreign('cost_center_id', 'journal_line_cost_center_id_foreign')
                    ->references('id')
                    ->on('cost_center')
                    ->restrictOnDelete();
                $table->index('cost_center_id', 'journal_line_cost_center_id_index');
            });
        }

        if (! Schema::hasColumn('ledger_entry', 'project_id')) {
            Schema::table('ledger_entry', function (Blueprint $table): void {
                $table->uuid('project_id')->nullable()->after('branch_id');
                $table->foreign('project_id', 'ledger_entry_project_id_foreign')
                    ->references('id')
                    ->on('project')
                    ->restrictOnDelete();
                $table->index(['project_id', 'financial_period_id', 'entry_date'], 'ledger_entry_project_period_date_index');
            });
        }

        if (! Schema::hasColumn('ledger_entry', 'cost_center_id')) {
            Schema::table('ledger_entry', function (Blueprint $table): void {
                $table->uuid('cost_center_id')->nullable()->after('project_id');
                $table->foreign('cost_center_id', 'ledger_entry_cost_center_id_foreign')
                    ->references('id')
                    ->on('cost_center')
                    ->restrictOnDelete();
                $table->index(['cost_center_id', 'financial_period_id', 'entry_date'], 'ledger_entry_cost_center_period_date_index');
            });

            $this->reapplyLedgerEntryImmutabilityTriggers();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ledger_entry', 'cost_center_id')) {
            Schema::table('ledger_entry', function (Blueprint $table): void {
                $table->dropIndex('ledger_entry_cost_center_period_date_index');
                $table->dropForeign('ledger_entry_cost_center_id_foreign');
                $table->dropColumn('cost_center_id');
            });
        }

        if (Schema::hasColumn('ledger_entry', 'project_id')) {
            Schema::table('ledger_entry', function (Blueprint $table): void {
                $table->dropIndex('ledger_entry_project_period_date_index');
                $table->dropForeign('ledger_entry_project_id_foreign');
                $table->dropColumn('project_id');
            });

            $this->reapplyLedgerEntryImmutabilityTriggers();
        }

        if (Schema::hasColumn('journal_line', 'cost_center_id')) {
            Schema::table('journal_line', function (Blueprint $table): void {
                $table->dropIndex('journal_line_cost_center_id_index');
                $table->dropForeign('journal_line_cost_center_id_foreign');
                $table->dropColumn('cost_center_id');
            });
        }

        if (Schema::hasColumn('journal_line', 'project_id')) {
            Schema::table('journal_line', function (Blueprint $table): void {
                $table->dropIndex('journal_line_project_id_index');
                $table->dropForeign('journal_line_project_id_foreign');
                $table->dropColumn('project_id');
            });
        }
    }

    private function reapplyLedgerEntryImmutabilityTriggers(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION prevent_ledger_entry_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Ledger entries are immutable. UPDATE and DELETE operations are strictly prohibited.';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS enforce_ledger_entry_immutability ON ledger_entry;

                CREATE TRIGGER enforce_ledger_entry_immutability
                BEFORE UPDATE OR DELETE ON ledger_entry
                FOR EACH ROW
                EXECUTE FUNCTION prevent_ledger_entry_mutation();
            ");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_ledger_entry_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_ledger_entry_no_delete');

            DB::unprepared("
                CREATE TRIGGER enforce_ledger_entry_no_update
                BEFORE UPDATE ON ledger_entry
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(FAIL, 'Ledger entries are immutable. UPDATE operations are strictly prohibited.');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER enforce_ledger_entry_no_delete
                BEFORE DELETE ON ledger_entry
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(FAIL, 'Ledger entries are immutable. DELETE operations are strictly prohibited.');
                END;
            ");
        }
    }
};
