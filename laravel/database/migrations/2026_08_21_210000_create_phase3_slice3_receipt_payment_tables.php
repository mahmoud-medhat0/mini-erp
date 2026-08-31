<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receipt', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->foreignUuid('customer_id')->constrained('customer')->cascadeOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->date('receipt_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_account')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_account')->restrictOnDelete();

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('unapplied_minor')->default(0);
            $table->bigInteger('fx_rate_e6')->default(1000000);

            $table->string('status')->default('draft');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->nullOnDelete();
            $table->foreignUuid('receivable_entry_id')->nullable()->constrained('receivable_entry')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_payment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->foreignUuid('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_account')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_account')->restrictOnDelete();

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('unapplied_minor')->default(0);
            $table->bigInteger('fx_rate_e6')->default(1000000);

            $table->string('status')->default('draft');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->nullOnDelete();
            $table->foreignUuid('payable_entry_id')->nullable()->constrained('payable_entry')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment');
        Schema::dropIfExists('customer_receipt');
    }
};
