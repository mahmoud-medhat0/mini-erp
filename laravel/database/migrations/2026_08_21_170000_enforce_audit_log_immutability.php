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
                CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Audit logs are append-only. UPDATE and DELETE operations are strictly prohibited.';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS enforce_audit_log_immutability ON audit_log;

                CREATE TRIGGER enforce_audit_log_immutability
                BEFORE UPDATE OR DELETE ON audit_log
                FOR EACH ROW
                EXECUTE FUNCTION prevent_audit_log_mutation();
            ");
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_audit_log_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_audit_log_no_delete');

            DB::unprepared("
                CREATE TRIGGER enforce_audit_log_no_update
                BEFORE UPDATE ON audit_log
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(FAIL, 'Audit logs are append-only. UPDATE operations are strictly prohibited.');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER enforce_audit_log_no_delete
                BEFORE DELETE ON audit_log
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(FAIL, 'Audit logs are append-only. DELETE operations are strictly prohibited.');
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
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_audit_log_immutability ON audit_log;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_log_mutation();');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_audit_log_no_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_audit_log_no_delete;');
        }
    }
};
