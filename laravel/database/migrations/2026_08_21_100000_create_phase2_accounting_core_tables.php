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
        // 1. Account Groups
        Schema::create('account_group', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->jsonb('name'); // multilingual {"en": "...", "ar": "..."}
            $table->string('type', 20); // asset, liability, equity, revenue, expense
            $table->string('statement_section', 50)->nullable();
            $table->uuid('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('parent_id');
        });

        // Add self-referential FK for account_group
        Schema::table('account_group', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('account_group')->onDelete('restrict');
        });

        // 2. Chart of Accounts
        Schema::create('account', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->jsonb('name'); // multilingual {"en": "...", "ar": "..."}
            $table->string('type', 30); // asset, liability, equity, revenue, expense, contra_asset, contra_liability, contra_revenue
            $table->string('nature', 10); // debit, credit
            $table->uuid('account_group_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->boolean('is_control')->default(false);
            $table->boolean('allow_manual_posting')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('account_group_id')->references('id')->on('account_group')->onDelete('set null');
            $table->index('type');
            $table->index('nature');
        });

        // Add self-referential FK for account
        Schema::table('account', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('account')->onDelete('restrict');
        });

        // 3. Journal Entries
        Schema::create('journal_entry', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 100)->nullable()->unique();
            $table->date('entry_date');
            $table->uuid('financial_period_id');
            $table->string('source_type', 50)->default('manual_journal');
            $table->string('source_id', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, posted, reversed, cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reversed_at')->nullable();
            $table->uuid('reverses_entry_id')->nullable();
            $table->uuid('reversal_entry_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->index('financial_period_id');
            $table->index('entry_date');
            $table->index('status');
            $table->index(['source_type', 'source_id']);
        });

        // Add self-referential FKs for journal_entry
        Schema::table('journal_entry', function (Blueprint $table): void {
            $table->foreign('reverses_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->foreign('reversal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
        });

        // 4. Journal Lines
        Schema::create('journal_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->unsignedInteger('line_no');
            $table->uuid('account_id');
            $table->text('memo')->nullable();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->bigInteger('debit_txn_minor')->default(0);
            $table->bigInteger('credit_txn_minor')->default(0);
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('account')->onDelete('restrict');
            $table->unique(['journal_entry_id', 'line_no']);
            $table->index('account_id');
        });

        // 5. Ledger Entries (Immutable Derived Postings)
        Schema::create('ledger_entry', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('journal_line_id')->unique();
            $table->uuid('account_id');
            $table->uuid('financial_period_id');
            $table->date('entry_date');
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->bigInteger('debit_txn_minor')->default(0);
            $table->bigInteger('credit_txn_minor')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('restrict');
            $table->foreign('journal_line_id')->references('id')->on('journal_line')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('account')->onDelete('restrict');
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->onDelete('restrict');
            $table->index(['account_id', 'entry_date']);
            $table->index(['financial_period_id', 'account_id']);
        });

        // 6. Opening Balances
        Schema::create('opening_balance', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('fiscal_year_id');
            $table->uuid('account_id');
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->uuid('journal_entry_id')->nullable();
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('posted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('account')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->onDelete('set null');
            $table->unique(['fiscal_year_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance');
        Schema::dropIfExists('ledger_entry');
        Schema::dropIfExists('journal_line');
        Schema::dropIfExists('journal_entry');
        Schema::dropIfExists('account');
        Schema::dropIfExists('account_group');
    }
};
