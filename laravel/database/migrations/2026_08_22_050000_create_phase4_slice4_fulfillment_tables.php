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
        // Delivery Note tables
        Schema::create('delivery_note', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('sales_order_id');
            $table->date('delivery_date');
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_order')->onDelete('restrict');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'delivery_date']);
            $table->index('sales_order_id');
        });

        Schema::create('delivery_note_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('delivery_note_id');
            $table->uuid('sales_order_line_id');
            $table->integer('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->timestamps();

            $table->foreign('delivery_note_id')->references('id')->on('delivery_note')->onDelete('cascade');
            $table->foreign('sales_order_line_id')->references('id')->on('sales_order_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['delivery_note_id', 'line_no']);
            $table->index(['delivery_note_id', 'sales_order_line_id']);
        });

        // Goods Receipt tables
        Schema::create('goods_receipt', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->uuid('purchase_order_id');
            $table->date('receipt_date');
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_order')->onDelete('restrict');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'receipt_date']);
            $table->index('purchase_order_id');
        });

        Schema::create('goods_receipt_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('goods_receipt_id');
            $table->uuid('purchase_order_line_id');
            $table->integer('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->timestamps();

            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipt')->onDelete('cascade');
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_line')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['goods_receipt_id', 'line_no']);
            $table->index(['goods_receipt_id', 'purchase_order_line_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_line');
        Schema::dropIfExists('goods_receipt');
        Schema::dropIfExists('delivery_note_line');
        Schema::dropIfExists('delivery_note');
    }
};
