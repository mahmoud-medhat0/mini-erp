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
        Schema::create('purchase_return', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('supplier_id');
            $table->uuid('goods_receipt_id')->nullable();
            $table->uuid('supplier_bill_id')->nullable();
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

            $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('restrict');
            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipt')->onDelete('restrict');
            $table->foreign('supplier_bill_id')->references('id')->on('supplier_bill')->onDelete('restrict');
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
            $table->index('supplier_id');
        });

        Schema::create('purchase_return_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_return_id');
            $table->unsignedBigInteger('line_no');
            $table->uuid('goods_receipt_line_id');
            $table->uuid('supplier_bill_line_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->bigInteger('original_receipt_cost_minor')->default(0);
            $table->bigInteger('stock_value_minor')->default(0);
            $table->bigInteger('variance_minor')->default(0);
            $table->timestamps();

            $table->foreign('purchase_return_id')->references('id')->on('purchase_return')->onDelete('cascade');
            $table->foreign('goods_receipt_line_id')->references('id')->on('goods_receipt_line')->onDelete('restrict');
            $table->foreign('supplier_bill_line_id')->references('id')->on('supplier_bill_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['purchase_return_id', 'line_no']);
            $table->index(['purchase_return_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_line');
        Schema::dropIfExists('purchase_return');
    }
};
