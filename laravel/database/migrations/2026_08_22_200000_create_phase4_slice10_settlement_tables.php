<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_entry_settlement', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('source_receivable_entry_id');
            $table->uuid('target_receivable_entry_id');
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->string('status')->default('active');
            $table->timestamp('settled_at');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('reversed_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('restrict');
            $table->foreign('source_receivable_entry_id')->references('id')->on('receivable_entry')->onDelete('restrict');
            $table->foreign('target_receivable_entry_id')->references('id')->on('receivable_entry')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reversed_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['customer_id', 'settled_at']);
            $table->index(['source_receivable_entry_id', 'status']);
            $table->index(['target_receivable_entry_id', 'status']);
            $table->index('currency');
        });

        Schema::create('payable_entry_settlement', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->uuid('source_payable_entry_id');
            $table->uuid('target_payable_entry_id');
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->string('status')->default('active');
            $table->timestamp('settled_at');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('reversed_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('restrict');
            $table->foreign('source_payable_entry_id')->references('id')->on('payable_entry')->onDelete('restrict');
            $table->foreign('target_payable_entry_id')->references('id')->on('payable_entry')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reversed_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['supplier_id', 'settled_at']);
            $table->index(['source_payable_entry_id', 'status']);
            $table->index(['target_payable_entry_id', 'status']);
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_entry_settlement');
        Schema::dropIfExists('receivable_entry_settlement');
    }
};
