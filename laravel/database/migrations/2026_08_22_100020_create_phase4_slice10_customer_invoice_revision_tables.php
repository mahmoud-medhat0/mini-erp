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
        Schema::create('customer_invoice_revision', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_invoice_id');
            $table->uuid('customer_credit_note_id')->nullable();
            $table->uuid('sales_return_id')->nullable();
            $table->unsignedInteger('revision_no');
            $table->string('display_string')->unique();
            $table->date('revision_date');
            $table->string('currency', 3);

            $table->bigInteger('original_subtotal_minor')->default(0);
            $table->bigInteger('credited_subtotal_minor')->default(0);
            $table->bigInteger('net_subtotal_minor')->default(0);
            $table->bigInteger('original_tax_minor')->default(0);
            $table->bigInteger('credited_tax_minor')->default(0);
            $table->bigInteger('net_tax_minor')->default(0);
            $table->bigInteger('original_total_minor')->default(0);
            $table->bigInteger('credited_total_minor')->default(0);
            $table->bigInteger('net_total_minor')->default(0);

            $table->json('snapshot_json')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_invoice_id')->references('id')->on('customer_invoice')->onDelete('restrict');
            $table->foreign('customer_credit_note_id')->references('id')->on('customer_credit_note')->onDelete('restrict');
            $table->foreign('sales_return_id')->references('id')->on('sales_return')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['customer_invoice_id', 'revision_no']);
            $table->index('customer_invoice_id');
            $table->index('customer_credit_note_id');
            $table->index('sales_return_id');
        });

        Schema::create('customer_invoice_revision_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_invoice_revision_id');
            $table->uuid('customer_invoice_line_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->uuid('unit_of_measure_id')->nullable();
            $table->unsignedBigInteger('line_no');
            $table->text('description');

            $table->bigInteger('original_quantity_e6')->nullable();
            $table->bigInteger('returned_quantity_e6')->nullable()->default(0);
            $table->bigInteger('net_quantity_e6')->nullable();

            $table->bigInteger('unit_price_minor')->default(0);

            $table->bigInteger('original_subtotal_minor')->default(0);
            $table->bigInteger('credited_subtotal_minor')->default(0);
            $table->bigInteger('net_subtotal_minor')->default(0);
            $table->bigInteger('original_tax_minor')->default(0);
            $table->bigInteger('credited_tax_minor')->default(0);
            $table->bigInteger('net_tax_minor')->default(0);
            $table->bigInteger('original_total_minor')->default(0);
            $table->bigInteger('credited_total_minor')->default(0);
            $table->bigInteger('net_total_minor')->default(0);

            $table->json('source_summary_json')->nullable();
            $table->timestamps();

            $table->foreign('customer_invoice_revision_id')->references('id')->on('customer_invoice_revision')->onDelete('cascade');
            $table->foreign('customer_invoice_line_id')->references('id')->on('customer_invoice_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['customer_invoice_revision_id', 'line_no']);
            $table->index('customer_invoice_revision_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_revision_line');
        Schema::dropIfExists('customer_invoice_revision');
    }
};
