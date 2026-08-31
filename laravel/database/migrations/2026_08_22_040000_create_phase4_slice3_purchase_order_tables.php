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
        Schema::create('purchase_order', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('supplier_id');
            $table->date('order_date');
            $table->date('expected_receipt_date')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);

            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'order_date']);
            $table->index('supplier_id');
        });

        Schema::create('purchase_order_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->integer('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_order')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['purchase_order_id', 'line_no']);
            $table->index(['purchase_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_line');
        Schema::dropIfExists('purchase_order');
    }
};
