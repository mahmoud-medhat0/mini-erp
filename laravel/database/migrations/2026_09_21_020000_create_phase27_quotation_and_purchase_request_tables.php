<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotation', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 64)->nullable()->unique();
            $table->uuid('customer_id');
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status', 32)->default('draft');
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->uuid('converted_sales_order_id')->nullable();

            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('converted_sales_order_id')->references('id')->on('sales_order')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('decided_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'quotation_date']);
        });

        Schema::create('sales_quotation_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sales_quotation_id');
            $table->integer('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->timestamps();

            $table->foreign('sales_quotation_id')->references('id')->on('sales_quotation')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['sales_quotation_id', 'line_no']);
        });

        Schema::create('purchase_request', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 64)->nullable()->unique();
            $table->uuid('supplier_id')->nullable();
            $table->date('requested_date');
            $table->date('needed_by_date')->nullable();
            $table->string('currency', 3);
            $table->string('status', 32)->default('draft');
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->uuid('converted_purchase_order_id')->nullable();

            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('converted_purchase_order_id')->references('id')->on('purchase_order')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('decided_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'requested_date']);
        });

        Schema::create('purchase_request_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_request_id');
            $table->integer('line_no');
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6');
            $table->bigInteger('estimated_unit_price_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
            $table->timestamps();

            $table->foreign('purchase_request_id')->references('id')->on('purchase_request')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->onDelete('restrict');

            $table->unique(['purchase_request_id', 'line_no']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sales_quotation ADD CONSTRAINT sales_quotation_status_check CHECK (status IN ('draft', 'submitted', 'accepted', 'rejected', 'expired', 'converted'))");
            DB::statement("ALTER TABLE purchase_request ADD CONSTRAINT purchase_request_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'converted'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_line');
        Schema::dropIfExists('purchase_request');
        Schema::dropIfExists('sales_quotation_line');
        Schema::dropIfExists('sales_quotation');
    }
};
