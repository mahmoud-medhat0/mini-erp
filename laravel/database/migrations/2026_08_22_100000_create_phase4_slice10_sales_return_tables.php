<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_return', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('customer_id');
            $table->uuid('delivery_note_id')->nullable();
            $table->uuid('customer_invoice_id')->nullable();
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->date('return_date');
            $table->string('status', 30)->default('draft');
            $table->string('currency', 3);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('journal_entry_id')->nullable();

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
            $table->foreign('delivery_note_id')->references('id')->on('delivery_note')->onDelete('restrict');
            $table->foreign('customer_invoice_id')->references('id')->on('customer_invoice')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->onDelete('restrict');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');

            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');

            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'return_date']);
            $table->index('customer_id');
        });

        Schema::create('sales_return_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sales_return_id');
            $table->unsignedBigInteger('line_no');
            $table->uuid('delivery_note_line_id');
            $table->uuid('customer_invoice_line_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->string('disposition', 40);
            $table->bigInteger('original_issue_cost_minor')->default(0);
            $table->bigInteger('manual_restock_value_minor')->nullable();
            $table->bigInteger('stock_value_minor')->default(0);
            $table->bigInteger('variance_minor')->default(0);
            $table->timestamps();

            $table->foreign('sales_return_id')->references('id')->on('sales_return')->onDelete('cascade');
            $table->foreign('delivery_note_line_id')->references('id')->on('delivery_note_line')->onDelete('restrict');
            $table->foreign('customer_invoice_line_id')->references('id')->on('customer_invoice_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['sales_return_id', 'line_no']);
            $table->index(['sales_return_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_line');
        Schema::dropIfExists('sales_return');
    }
};
