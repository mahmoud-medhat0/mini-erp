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
        // 1. accounting_account_mapping
        Schema::create('accounting_account_mapping', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->uuid('account_id');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('account')->onDelete('restrict');
        });

        // 2. receivable_entry
        Schema::create('receivable_entry', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('source_type');
            $table->uuid('source_id');
            $table->uuid('journal_entry_id');
            $table->uuid('journal_line_id')->nullable();
            $table->uuid('financial_period_id');
            $table->date('entry_date');
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->bigInteger('debit_txn_minor')->default(0);
            $table->bigInteger('credit_txn_minor')->default(0);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('restrict');
            $table->foreign('journal_line_id')->references('id')->on('journal_line')->onDelete('set null');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');

            $table->index(['customer_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
        });

        // 3. payable_entry
        Schema::create('payable_entry', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->string('source_type');
            $table->uuid('source_id');
            $table->uuid('journal_entry_id');
            $table->uuid('journal_line_id')->nullable();
            $table->uuid('financial_period_id');
            $table->date('entry_date');
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->bigInteger('debit_txn_minor')->default(0);
            $table->bigInteger('credit_txn_minor')->default(0);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('restrict');
            $table->foreign('journal_line_id')->references('id')->on('journal_line')->onDelete('set null');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');

            $table->index(['supplier_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
        });

        // 4. customer_opening_balance
        Schema::create('customer_opening_balance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->date('entry_date');
            $table->date('due_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status')->default('draft');
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('receivable_entry_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->onDelete('restrict');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->foreign('receivable_entry_id')->references('id')->on('receivable_entry')->onDelete('set null');
        });

        // 5. supplier_opening_balance
        Schema::create('supplier_opening_balance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->date('entry_date');
            $table->date('due_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status')->default('draft');
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('payable_entry_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->onDelete('restrict');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->foreign('payable_entry_id')->references('id')->on('payable_entry')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_opening_balance');
        Schema::dropIfExists('customer_opening_balance');
        Schema::dropIfExists('payable_entry');
        Schema::dropIfExists('receivable_entry');
        Schema::dropIfExists('accounting_account_mapping');
    }
};
