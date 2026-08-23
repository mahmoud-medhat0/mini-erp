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
                CREATE OR REPLACE FUNCTION prevent_posted_fixed_asset_depreciation_schedule_mutation()
                RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'DELETE' AND OLD.status = 'posted' THEN
                        RAISE EXCEPTION 'Posted fixed asset depreciation schedule rows cannot be deleted.';
                    END IF;

                    IF TG_OP = 'UPDATE' AND OLD.status = 'posted' THEN
                        IF NEW.fixed_asset_id IS DISTINCT FROM OLD.fixed_asset_id
                            OR NEW.period_number IS DISTINCT FROM OLD.period_number
                            OR NEW.financial_period_id IS DISTINCT FROM OLD.financial_period_id
                            OR NEW.period_start_date IS DISTINCT FROM OLD.period_start_date
                            OR NEW.period_end_date IS DISTINCT FROM OLD.period_end_date
                            OR NEW.depreciation_minor IS DISTINCT FROM OLD.depreciation_minor
                            OR NEW.accumulated_depreciation_minor IS DISTINCT FROM OLD.accumulated_depreciation_minor
                            OR NEW.net_book_value_minor IS DISTINCT FROM OLD.net_book_value_minor
                            OR NEW.journal_entry_id IS DISTINCT FROM OLD.journal_entry_id
                            OR NEW.posted_at IS DISTINCT FROM OLD.posted_at
                            OR NEW.posted_by IS DISTINCT FROM OLD.posted_by
                        THEN
                            RAISE EXCEPTION 'Posted fixed asset depreciation schedule financial fields are immutable.';
                        END IF;

                        IF NEW.status NOT IN ('posted', 'reversed') THEN
                            RAISE EXCEPTION 'Posted fixed asset depreciation schedule rows can only remain posted or be marked reversed.';
                        END IF;
                    END IF;

                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_immutability ON fixed_asset_depreciation_schedule;

                CREATE TRIGGER enforce_fixed_asset_depreciation_schedule_immutability
                BEFORE UPDATE OR DELETE ON fixed_asset_depreciation_schedule
                FOR EACH ROW
                EXECUTE FUNCTION prevent_posted_fixed_asset_depreciation_schedule_mutation();
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_fixed_asset_depreciation_schedule_no_delete_posted
                BEFORE DELETE ON fixed_asset_depreciation_schedule
                WHEN OLD.status = 'posted'
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset depreciation schedule rows cannot be deleted.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_fixed_asset_depreciation_schedule_no_financial_update_posted
                BEFORE UPDATE ON fixed_asset_depreciation_schedule
                WHEN OLD.status = 'posted'
                    AND (
                        NEW.fixed_asset_id IS NOT OLD.fixed_asset_id
                        OR NEW.period_number IS NOT OLD.period_number
                        OR NEW.financial_period_id IS NOT OLD.financial_period_id
                        OR NEW.period_start_date IS NOT OLD.period_start_date
                        OR NEW.period_end_date IS NOT OLD.period_end_date
                        OR NEW.depreciation_minor IS NOT OLD.depreciation_minor
                        OR NEW.accumulated_depreciation_minor IS NOT OLD.accumulated_depreciation_minor
                        OR NEW.net_book_value_minor IS NOT OLD.net_book_value_minor
                        OR NEW.journal_entry_id IS NOT OLD.journal_entry_id
                        OR NEW.posted_at IS NOT OLD.posted_at
                        OR NEW.posted_by IS NOT OLD.posted_by
                        OR NEW.status NOT IN ('posted', 'reversed')
                    )
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset depreciation schedule financial fields are immutable.');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_immutability ON fixed_asset_depreciation_schedule;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_posted_fixed_asset_depreciation_schedule_mutation();');

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_no_financial_update_posted;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_no_delete_posted;');
        }
    }
};
