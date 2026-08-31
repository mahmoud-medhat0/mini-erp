<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_finalized_bank_reconciliation_mutation()
                RETURNS trigger AS $$
                BEGIN
                    IF OLD.status = 'reconciled' THEN
                        RAISE EXCEPTION 'Finalized bank reconciliations are immutable. UPDATE and DELETE operations are strictly prohibited.';
                    END IF;

                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS enforce_bank_reconciliation_immutability ON bank_reconciliation;

                CREATE TRIGGER enforce_bank_reconciliation_immutability
                BEFORE UPDATE OR DELETE ON bank_reconciliation
                FOR EACH ROW
                EXECUTE FUNCTION prevent_finalized_bank_reconciliation_mutation();
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_finalized_bank_reconciliation_line_mutation()
                RETURNS trigger AS $$
                DECLARE
                    parent_status text;
                BEGIN
                    SELECT status INTO parent_status
                    FROM bank_reconciliation
                    WHERE id = OLD.bank_reconciliation_id;

                    IF parent_status = 'reconciled' THEN
                        RAISE EXCEPTION 'Finalized bank reconciliation lines are immutable. UPDATE and DELETE operations are strictly prohibited.';
                    END IF;

                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS enforce_bank_reconciliation_line_immutability ON bank_reconciliation_line;

                CREATE TRIGGER enforce_bank_reconciliation_line_immutability
                BEFORE UPDATE OR DELETE ON bank_reconciliation_line
                FOR EACH ROW
                EXECUTE FUNCTION prevent_finalized_bank_reconciliation_line_mutation();
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_bank_reconciliation_no_update_finalized
                BEFORE UPDATE ON bank_reconciliation
                WHEN OLD.status = 'reconciled'
                BEGIN
                    SELECT RAISE(FAIL, 'Finalized bank reconciliations are immutable. UPDATE operations are strictly prohibited.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_bank_reconciliation_no_delete_finalized
                BEFORE DELETE ON bank_reconciliation
                WHEN OLD.status = 'reconciled'
                BEGIN
                    SELECT RAISE(FAIL, 'Finalized bank reconciliations are immutable. DELETE operations are strictly prohibited.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_bank_reconciliation_line_no_update_finalized
                BEFORE UPDATE ON bank_reconciliation_line
                WHEN (SELECT status FROM bank_reconciliation WHERE id = OLD.bank_reconciliation_id) = 'reconciled'
                BEGIN
                    SELECT RAISE(FAIL, 'Finalized bank reconciliation lines are immutable. UPDATE operations are strictly prohibited.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_bank_reconciliation_line_no_delete_finalized
                BEFORE DELETE ON bank_reconciliation_line
                WHEN (SELECT status FROM bank_reconciliation WHERE id = OLD.bank_reconciliation_id) = 'reconciled'
                BEGIN
                    SELECT RAISE(FAIL, 'Finalized bank reconciliation lines are immutable. DELETE operations are strictly prohibited.');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_bank_reconciliation_line_immutability ON bank_reconciliation_line;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_finalized_bank_reconciliation_line_mutation();');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_bank_reconciliation_immutability ON bank_reconciliation;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_finalized_bank_reconciliation_mutation();');

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_bank_reconciliation_line_no_delete_finalized;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_bank_reconciliation_line_no_update_finalized;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_bank_reconciliation_no_delete_finalized;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_bank_reconciliation_no_update_finalized;');
        }
    }
};
