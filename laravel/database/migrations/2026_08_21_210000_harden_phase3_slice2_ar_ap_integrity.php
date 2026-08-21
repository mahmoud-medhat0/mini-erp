<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_opening_balance')) {
            return;
        }

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS customer_opening_balance_party_year_active_unique ON customer_opening_balance (customer_id, fiscal_year_id) WHERE status <> 'cancelled'");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS supplier_opening_balance_party_year_active_unique ON supplier_opening_balance (supplier_id, fiscal_year_id) WHERE status <> 'cancelled'");
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS receivable_entry_source_unique ON receivable_entry (source_type, source_id)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS payable_entry_source_unique ON payable_entry (source_type, source_id)');

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset'))");

        DB::statement("ALTER TABLE customer_opening_balance ADD CONSTRAINT customer_opening_balance_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE customer_opening_balance ADD CONSTRAINT customer_opening_balance_amount_check CHECK (amount_minor > 0 AND fx_rate_e6 > 0)');

        DB::statement("ALTER TABLE supplier_opening_balance ADD CONSTRAINT supplier_opening_balance_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE supplier_opening_balance ADD CONSTRAINT supplier_opening_balance_amount_check CHECK (amount_minor > 0 AND fx_rate_e6 > 0)');

        DB::statement('ALTER TABLE receivable_entry ADD CONSTRAINT receivable_entry_amounts_check CHECK (debit_minor >= 0 AND credit_minor >= 0 AND debit_txn_minor >= 0 AND credit_txn_minor >= 0 AND fx_rate_e6 > 0 AND ((debit_minor > 0 AND credit_minor = 0) OR (debit_minor = 0 AND credit_minor > 0)))');
        DB::statement('ALTER TABLE payable_entry ADD CONSTRAINT payable_entry_amounts_check CHECK (debit_minor >= 0 AND credit_minor >= 0 AND debit_txn_minor >= 0 AND credit_txn_minor >= 0 AND fx_rate_e6 > 0 AND ((debit_minor > 0 AND credit_minor = 0) OR (debit_minor = 0 AND credit_minor > 0)))');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payable_entry DROP CONSTRAINT IF EXISTS payable_entry_amounts_check');
            DB::statement('ALTER TABLE receivable_entry DROP CONSTRAINT IF EXISTS receivable_entry_amounts_check');
            DB::statement('ALTER TABLE supplier_opening_balance DROP CONSTRAINT IF EXISTS supplier_opening_balance_amount_check');
            DB::statement('ALTER TABLE supplier_opening_balance DROP CONSTRAINT IF EXISTS supplier_opening_balance_status_check');
            DB::statement('ALTER TABLE customer_opening_balance DROP CONSTRAINT IF EXISTS customer_opening_balance_amount_check');
            DB::statement('ALTER TABLE customer_opening_balance DROP CONSTRAINT IF EXISTS customer_opening_balance_status_check');
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
        }

        DB::statement('DROP INDEX IF EXISTS payable_entry_source_unique');
        DB::statement('DROP INDEX IF EXISTS receivable_entry_source_unique');
        DB::statement('DROP INDEX IF EXISTS supplier_opening_balance_party_year_active_unique');
        DB::statement('DROP INDEX IF EXISTS customer_opening_balance_party_year_active_unique');
    }
};
