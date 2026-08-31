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
        Schema::create('supplier_adjustment_note', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('supplier_id');
            $table->uuid('supplier_bill_id')->nullable();
            $table->uuid('purchase_return_id')->nullable();
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->date('adjustment_date');
            $table->string('direction', 30);
            $table->string('ui_label')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('currency', 3);

            $table->bigInteger('subtotal_minor')->default(0);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('tax_mode', 20)->default('none');

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('payable_entry_id')->nullable();

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
            $table->foreign('supplier_bill_id')->references('id')->on('supplier_bill')->onDelete('restrict');
            $table->foreign('purchase_return_id')->references('id')->on('purchase_return')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->onDelete('restrict');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');

            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->foreign('payable_entry_id')->references('id')->on('payable_entry')->onDelete('set null');

            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'adjustment_date']);
            $table->index('supplier_id');
        });

        Schema::create('supplier_adjustment_note_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('supplier_adjustment_note_id');
            $table->unsignedBigInteger('line_no');
            $table->uuid('supplier_bill_line_id')->nullable();
            $table->uuid('purchase_return_line_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->uuid('unit_of_measure_id')->nullable();
            $table->text('description');
            $table->bigInteger('quantity_e6')->nullable();
            $table->bigInteger('unit_cost_minor')->default(0);
            $table->bigInteger('line_subtotal_minor')->default(0);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
            $table->timestamps();

            $table->foreign('supplier_adjustment_note_id')->references('id')->on('supplier_adjustment_note')->onDelete('cascade');
            $table->foreign('supplier_bill_line_id')->references('id')->on('supplier_bill_line')->onDelete('restrict');
            $table->foreign('purchase_return_line_id')->references('id')->on('purchase_return_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['supplier_adjustment_note_id', 'line_no']);
            $table->index(['supplier_adjustment_note_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_adjustment_note_line');
        Schema::dropIfExists('supplier_adjustment_note');
    }
};
