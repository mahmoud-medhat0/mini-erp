<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_of_measure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->json('name');
            $table->string('symbol', 16);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('product_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('product', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('type', 32); // stock, service, non_stock
            $table->uuid('unit_of_measure_id');
            $table->uuid('product_category_id')->nullable();
            $table->string('status', 32)->default('active'); // active, inactive
            $table->boolean('is_sales_enabled')->default(true);
            $table->boolean('is_purchase_enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('unit_of_measure_id')->references('id')->on('unit_of_measure')->restrictOnDelete();
            $table->foreign('product_category_id')->references('id')->on('product_category')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
        Schema::dropIfExists('product_category');
        Schema::dropIfExists('unit_of_measure');
    }
};
