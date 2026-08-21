<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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
        } elseif ($driver === 'sqlite') {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS enforce_ledger_entry_immutability ON ledger_entry;
                DROP FUNCTION IF EXISTS prevent_ledger_entry_mutation();
            ');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_ledger_entry_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_ledger_entry_no_delete');
        }
    }
};
