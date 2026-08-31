<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update accounting mapping key check constraint if PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable', 'sales_revenue'))");
        }

        Schema::create('customer_invoice', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('customer_id');
            $table->uuid('sales_order_id')->nullable();
            $table->uuid('delivery_note_id')->nullable();
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->string('currency', 3);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);

            $table->string('status', 30)->default('draft');

            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('receivable_entry_id')->nullable();

            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('restrict');
            $table->foreign('sales_order_id')->references('id')->on('sales_order')->onDelete('restrict');
            $table->foreign('delivery_note_id')->references('id')->on('delivery_note')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->onDelete('restrict');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');

            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->foreign('receivable_entry_id')->references('id')->on('receivable_entry')->onDelete('set null');

            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'invoice_date']);
            $table->index('customer_id');
        });

        Schema::create('customer_invoice_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_invoice_id');
            $table->uuid('sales_order_line_id')->nullable();
            $table->uuid('delivery_note_line_id')->nullable();
            $table->integer('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->timestamps();

            $table->foreign('customer_invoice_id')->references('id')->on('customer_invoice')->onDelete('cascade');
            $table->foreign('sales_order_line_id')->references('id')->on('sales_order_line')->onDelete('restrict');
            $table->foreign('delivery_note_line_id')->references('id')->on('delivery_note_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['customer_invoice_id', 'line_no']);
            $table->index(['customer_invoice_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_line');
        Schema::dropIfExists('customer_invoice');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable'))");
        }
    }
};
