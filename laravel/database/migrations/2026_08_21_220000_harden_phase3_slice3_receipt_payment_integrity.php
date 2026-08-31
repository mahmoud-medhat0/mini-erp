<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_receipt')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE customer_receipt DROP CONSTRAINT IF EXISTS customer_receipt_customer_id_foreign');
        DB::statement('ALTER TABLE customer_receipt ADD CONSTRAINT customer_receipt_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE supplier_payment DROP CONSTRAINT IF EXISTS supplier_payment_supplier_id_foreign');
        DB::statement('ALTER TABLE supplier_payment ADD CONSTRAINT supplier_payment_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE RESTRICT');

        DB::statement("ALTER TABLE customer_receipt ADD CONSTRAINT customer_receipt_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE customer_receipt ADD CONSTRAINT customer_receipt_amounts_check CHECK (amount_minor > 0 AND allocated_minor >= 0 AND unapplied_minor >= 0 AND allocated_minor + unapplied_minor = amount_minor AND fx_rate_e6 > 0)');
        DB::statement('ALTER TABLE customer_receipt ADD CONSTRAINT customer_receipt_cash_or_bank_check CHECK ((cash_account_id IS NOT NULL AND bank_account_id IS NULL) OR (cash_account_id IS NULL AND bank_account_id IS NOT NULL))');

        DB::statement("ALTER TABLE supplier_payment ADD CONSTRAINT supplier_payment_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE supplier_payment ADD CONSTRAINT supplier_payment_amounts_check CHECK (amount_minor > 0 AND allocated_minor >= 0 AND unapplied_minor >= 0 AND allocated_minor + unapplied_minor = amount_minor AND fx_rate_e6 > 0)');
        DB::statement('ALTER TABLE supplier_payment ADD CONSTRAINT supplier_payment_cash_or_bank_check CHECK ((cash_account_id IS NOT NULL AND bank_account_id IS NULL) OR (cash_account_id IS NULL AND bank_account_id IS NOT NULL))');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE supplier_payment DROP CONSTRAINT IF EXISTS supplier_payment_cash_or_bank_check');
        DB::statement('ALTER TABLE supplier_payment DROP CONSTRAINT IF EXISTS supplier_payment_amounts_check');
        DB::statement('ALTER TABLE supplier_payment DROP CONSTRAINT IF EXISTS supplier_payment_status_check');

        DB::statement('ALTER TABLE customer_receipt DROP CONSTRAINT IF EXISTS customer_receipt_cash_or_bank_check');
        DB::statement('ALTER TABLE customer_receipt DROP CONSTRAINT IF EXISTS customer_receipt_amounts_check');
        DB::statement('ALTER TABLE customer_receipt DROP CONSTRAINT IF EXISTS customer_receipt_status_check');

        DB::statement('ALTER TABLE supplier_payment DROP CONSTRAINT IF EXISTS supplier_payment_supplier_id_foreign');
        DB::statement('ALTER TABLE supplier_payment ADD CONSTRAINT supplier_payment_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE customer_receipt DROP CONSTRAINT IF EXISTS customer_receipt_customer_id_foreign');
        DB::statement('ALTER TABLE customer_receipt ADD CONSTRAINT customer_receipt_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE CASCADE');
    }
};
