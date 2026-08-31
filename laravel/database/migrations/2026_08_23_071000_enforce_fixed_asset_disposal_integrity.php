<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS fixed_asset_disposal_one_posted_per_asset
                ON fixed_asset_disposal (fixed_asset_id)
                WHERE status = 'posted';

                CREATE OR REPLACE FUNCTION prevent_fixed_asset_disposal_mutation()
                RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'DELETE' AND OLD.status IN ('posted', 'reversed') THEN
                        RAISE EXCEPTION 'Posted fixed asset disposal rows cannot be deleted.';
                    END IF;

                    IF TG_OP = 'UPDATE' AND OLD.status IN ('posted', 'reversed') THEN
                        IF NEW.number IS DISTINCT FROM OLD.number
                            OR NEW.fixed_asset_id IS DISTINCT FROM OLD.fixed_asset_id
                            OR NEW.disposal_date IS DISTINCT FROM OLD.disposal_date
                            OR NEW.financial_period_id IS DISTINCT FROM OLD.financial_period_id
                            OR NEW.disposal_type IS DISTINCT FROM OLD.disposal_type
                            OR NEW.proceeds_minor IS DISTINCT FROM OLD.proceeds_minor
                            OR NEW.net_book_value_minor IS DISTINCT FROM OLD.net_book_value_minor
                            OR NEW.gain_minor IS DISTINCT FROM OLD.gain_minor
                            OR NEW.loss_minor IS DISTINCT FROM OLD.loss_minor
                            OR NEW.journal_entry_id IS DISTINCT FROM OLD.journal_entry_id
                            OR NEW.posted_at IS DISTINCT FROM OLD.posted_at
                            OR NEW.posted_by IS DISTINCT FROM OLD.posted_by
                        THEN
                            RAISE EXCEPTION 'Posted fixed asset disposal financial fields are immutable.';
                        END IF;

                        IF OLD.status = 'posted' THEN
                            IF NEW.status NOT IN ('posted', 'reversed') THEN
                                RAISE EXCEPTION 'Posted fixed asset disposal rows can only remain posted or be marked reversed.';
                            END IF;

                            IF NEW.status = 'reversed' AND NEW.reversal_journal_entry_id IS NULL THEN
                                RAISE EXCEPTION 'Reversed fixed asset disposal rows require a reversal journal entry.';
                            END IF;

                            IF NEW.status = 'posted'
                                AND NEW.reversal_journal_entry_id IS DISTINCT FROM OLD.reversal_journal_entry_id
                            THEN
                                RAISE EXCEPTION 'Posted fixed asset disposal rows cannot reference a reversal journal entry.';
                            END IF;
                        END IF;

                        IF OLD.status = 'reversed' THEN
                            IF NEW.status IS DISTINCT FROM OLD.status
                                OR NEW.reversal_journal_entry_id IS DISTINCT FROM OLD.reversal_journal_entry_id
                            THEN
                                RAISE EXCEPTION 'Reversed fixed asset disposal rows are immutable.';
                            END IF;
                        END IF;
                    END IF;

                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS enforce_fixed_asset_disposal_integrity ON fixed_asset_disposal;

                CREATE TRIGGER enforce_fixed_asset_disposal_integrity
                BEFORE UPDATE OR DELETE ON fixed_asset_disposal
                FOR EACH ROW
                EXECUTE FUNCTION prevent_fixed_asset_disposal_mutation();
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS fixed_asset_disposal_one_posted_per_asset
                ON fixed_asset_disposal (fixed_asset_id)
                WHERE status = 'posted';
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_fixed_asset_disposal_no_delete_posted
                BEFORE DELETE ON fixed_asset_disposal
                WHEN OLD.status IN ('posted', 'reversed')
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset disposal rows cannot be deleted.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_fixed_asset_disposal_no_financial_update
                BEFORE UPDATE ON fixed_asset_disposal
                WHEN OLD.status IN ('posted', 'reversed')
                    AND (
                        NEW.number IS NOT OLD.number
                        OR NEW.fixed_asset_id IS NOT OLD.fixed_asset_id
                        OR NEW.disposal_date IS NOT OLD.disposal_date
                        OR NEW.financial_period_id IS NOT OLD.financial_period_id
                        OR NEW.disposal_type IS NOT OLD.disposal_type
                        OR NEW.proceeds_minor IS NOT OLD.proceeds_minor
                        OR NEW.net_book_value_minor IS NOT OLD.net_book_value_minor
                        OR NEW.gain_minor IS NOT OLD.gain_minor
                        OR NEW.loss_minor IS NOT OLD.loss_minor
                        OR NEW.journal_entry_id IS NOT OLD.journal_entry_id
                        OR NEW.posted_at IS NOT OLD.posted_at
                        OR NEW.posted_by IS NOT OLD.posted_by
                    )
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset disposal financial fields are immutable.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_fixed_asset_disposal_status_transition
                BEFORE UPDATE ON fixed_asset_disposal
                WHEN OLD.status = 'posted'
                    AND (
                        NEW.status NOT IN ('posted', 'reversed')
                        OR (NEW.status = 'reversed' AND NEW.reversal_journal_entry_id IS NULL)
                        OR (NEW.status = 'posted' AND NEW.reversal_journal_entry_id IS NOT OLD.reversal_journal_entry_id)
                    )
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset disposal rows can only remain posted or be marked reversed with a reversal journal entry.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS enforce_fixed_asset_disposal_reversed_immutable
                BEFORE UPDATE ON fixed_asset_disposal
                WHEN OLD.status = 'reversed'
                    AND (
                        NEW.status IS NOT OLD.status
                        OR NEW.reversal_journal_entry_id IS NOT OLD.reversal_journal_entry_id
                    )
                BEGIN
                    SELECT RAISE(FAIL, 'Reversed fixed asset disposal rows are immutable.');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_disposal_integrity ON fixed_asset_disposal;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_fixed_asset_disposal_mutation();');
            DB::unprepared('DROP INDEX IF EXISTS fixed_asset_disposal_one_posted_per_asset;');

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_disposal_reversed_immutable;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_disposal_status_transition;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_disposal_no_financial_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_disposal_no_delete_posted;');
            DB::unprepared('DROP INDEX IF EXISTS fixed_asset_disposal_one_posted_per_asset;');
        }
    }
};
