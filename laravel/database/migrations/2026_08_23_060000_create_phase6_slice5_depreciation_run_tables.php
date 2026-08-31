<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciation_run', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->unique();
            $table->uuid('financial_period_id');
            $table->date('run_date');
            $table->bigInteger('total_depreciation_minor');
            $table->integer('asset_count');
            $table->string('status', 50)->default('posted');
            $table->uuid('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamps();

            $table->foreign('financial_period_id')
                ->references('id')
                ->on('financial_period')
                ->onDelete('restrict');

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entry')
                ->onDelete('restrict');

            $table->foreign('posted_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE fixed_asset_depreciation_run
                ADD CONSTRAINT chk_fadr_status
                CHECK (status IN ('posted', 'reversed'));
            ");
            DB::statement('
                ALTER TABLE fixed_asset_depreciation_run
                ADD CONSTRAINT chk_fadr_amounts
                CHECK (total_depreciation_minor >= 0 AND asset_count >= 0);
            ');
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable', 'sales_revenue', 'purchase_expense', 'inventory_asset', 'grni_clearing', 'cogs', 'sales_returns', 'inventory_return_variance', 'inventory_scrap_loss', 'purchase_returns_allowances', 'output_tax_payable', 'input_tax_receivable', 'fixed_asset_cost', 'accumulated_depreciation', 'depreciation_expense', 'fixed_asset_disposal_gain', 'fixed_asset_disposal_loss', 'fixed_asset_clearing'))");
        }

        Schema::table('fixed_asset_depreciation_schedule', function (Blueprint $table): void {
            $table->uuid('depreciation_run_id')->nullable();

            $table->foreign('depreciation_run_id')
                ->references('id')
                ->on('fixed_asset_depreciation_run')
                ->onDelete('set null');
        });

        $this->refreshScheduleImmutabilityTrigger();
    }

    public function down(): void
    {
        $this->dropScheduleImmutabilityTriggers();

        Schema::table('fixed_asset_depreciation_schedule', function (Blueprint $table): void {
            $table->dropForeign(['depreciation_run_id']);
            $table->dropColumn('depreciation_run_id');
        });

        Schema::dropIfExists('fixed_asset_depreciation_run');

        $this->restoreScheduleImmutabilityTriggerWithoutRunColumn();
    }

    private function refreshScheduleImmutabilityTrigger(): void
    {
        $driver = DB::getDriverName();

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
                            OR NEW.depreciation_run_id IS DISTINCT FROM OLD.depreciation_run_id
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
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_no_financial_update_posted;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_no_delete_posted;');

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER enforce_fixed_asset_depreciation_schedule_no_delete_posted
                BEFORE DELETE ON fixed_asset_depreciation_schedule
                WHEN OLD.status = 'posted'
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset depreciation schedule rows cannot be deleted.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER enforce_fixed_asset_depreciation_schedule_no_financial_update_posted
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
                        OR NEW.depreciation_run_id IS NOT OLD.depreciation_run_id
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

    private function dropScheduleImmutabilityTriggers(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_immutability ON fixed_asset_depreciation_schedule;');

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_no_financial_update_posted;');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_fixed_asset_depreciation_schedule_no_delete_posted;');
        }
    }

    private function restoreScheduleImmutabilityTriggerWithoutRunColumn(): void
    {
        $driver = DB::getDriverName();

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

                CREATE TRIGGER enforce_fixed_asset_depreciation_schedule_immutability
                BEFORE UPDATE OR DELETE ON fixed_asset_depreciation_schedule
                FOR EACH ROW
                EXECUTE FUNCTION prevent_posted_fixed_asset_depreciation_schedule_mutation();
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER enforce_fixed_asset_depreciation_schedule_no_delete_posted
                BEFORE DELETE ON fixed_asset_depreciation_schedule
                WHEN OLD.status = 'posted'
                BEGIN
                    SELECT RAISE(FAIL, 'Posted fixed asset depreciation schedule rows cannot be deleted.');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER enforce_fixed_asset_depreciation_schedule_no_financial_update_posted
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
};
