<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_account', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_account', 'branch_id')) {
                $table->uuid('branch_id')->nullable()->after('name');
                $table->foreign('branch_id')->references('id')->on('branch')->nullOnDelete();
                $table->index(['branch_id', 'is_active'], 'cash_account_branch_active_index');
            }
        });

        Schema::table('bank_account', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_account', 'branch_id')) {
                $table->uuid('branch_id')->nullable()->after('bank_name');
                $table->foreign('branch_id')->references('id')->on('branch')->nullOnDelete();
                $table->index(['branch_id', 'is_active'], 'bank_account_branch_active_index');
            }
        });

        Schema::create('treasury_transfer', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->nullable()->unique();
            $table->date('transfer_date');
            $table->string('source_type', 20);
            $table->uuid('source_cash_account_id')->nullable();
            $table->uuid('source_bank_account_id')->nullable();
            $table->string('destination_type', 20);
            $table->uuid('destination_cash_account_id')->nullable();
            $table->uuid('destination_bank_account_id')->nullable();
            $table->uuid('source_branch_id')->nullable();
            $table->uuid('destination_branch_id')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->uuid('fiscal_year_id');
            $table->uuid('financial_period_id');
            $table->uuid('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('source_cash_account_id')->references('id')->on('cash_account')->restrictOnDelete();
            $table->foreign('source_bank_account_id')->references('id')->on('bank_account')->restrictOnDelete();
            $table->foreign('destination_cash_account_id')->references('id')->on('cash_account')->restrictOnDelete();
            $table->foreign('destination_bank_account_id')->references('id')->on('bank_account')->restrictOnDelete();
            $table->foreign('source_branch_id')->references('id')->on('branch')->nullOnDelete();
            $table->foreign('destination_branch_id')->references('id')->on('branch')->nullOnDelete();
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->restrictOnDelete();
            $table->foreign('financial_period_id')->references('id')->on('financial_period')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entry')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['transfer_date', 'status']);
            $table->index(['source_branch_id', 'destination_branch_id'], 'treasury_transfer_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_transfer');

        Schema::table('bank_account', function (Blueprint $table): void {
            if (Schema::hasColumn('bank_account', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropIndex('bank_account_branch_active_index');
                $table->dropColumn('branch_id');
            }
        });

        Schema::table('cash_account', function (Blueprint $table): void {
            if (Schema::hasColumn('cash_account', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropIndex('cash_account_branch_active_index');
                $table->dropColumn('branch_id');
            }
        });
    }
};
