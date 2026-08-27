<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_category', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->foreignUuid('default_expense_account_id')->nullable()->constrained('account')->restrictOnDelete();
            $table->foreignUuid('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expense', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('supplier')->restrictOnDelete();
            $table->string('payee_name')->nullable();
            $table->string('settlement_method');
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_account')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_account')->restrictOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('currency', 3);
            $table->integer('fx_rate_e6')->default(1000000);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_amount_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('status')->default('draft');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('payable_entry_id')->nullable()->constrained('payable_entry')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'expense_date']);
            $table->index(['branch_id', 'expense_date']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('expense_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('expense_id')->constrained('expense')->cascadeOnDelete();
            $table->integer('line_no');
            $table->foreignUuid('expense_category_id')->constrained('expense_category')->restrictOnDelete();
            $table->foreignUuid('expense_account_id')->constrained('account')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->bigInteger('quantity_e6')->default(1000000);
            $table->bigInteger('unit_amount_minor');
            $table->bigInteger('line_total_minor');
            $table->foreignUuid('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->integer('tax_rate_bps')->default(0);
            $table->bigInteger('tax_amount_minor')->default(0);
            $table->bigInteger('gross_amount_minor')->default(0);
            $table->timestamps();

            $table->unique(['expense_id', 'line_no']);
            $table->index(['expense_category_id']);
            $table->index(['expense_account_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE expense ADD CONSTRAINT expense_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'cancelled'))");
            DB::statement("ALTER TABLE expense ADD CONSTRAINT expense_settlement_method_check CHECK (settlement_method IN ('payable', 'cash', 'bank'))");
            DB::statement('ALTER TABLE expense ADD CONSTRAINT expense_amounts_check CHECK (fx_rate_e6 = 1000000 AND subtotal_minor >= 0 AND tax_amount_minor >= 0 AND total_minor = subtotal_minor + tax_amount_minor)');
            DB::statement('ALTER TABLE expense ADD CONSTRAINT expense_settlement_reference_check CHECK ((settlement_method = \'payable\' AND supplier_id IS NOT NULL AND cash_account_id IS NULL AND bank_account_id IS NULL) OR (settlement_method = \'cash\' AND cash_account_id IS NOT NULL AND supplier_id IS NULL AND bank_account_id IS NULL) OR (settlement_method = \'bank\' AND bank_account_id IS NOT NULL AND supplier_id IS NULL AND cash_account_id IS NULL))');
            DB::statement('ALTER TABLE expense_line ADD CONSTRAINT expense_line_amounts_check CHECK (quantity_e6 > 0 AND unit_amount_minor > 0 AND line_total_minor >= 0 AND tax_rate_bps >= 0 AND tax_amount_minor >= 0 AND gross_amount_minor = line_total_minor + tax_amount_minor)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_line');
        Schema::dropIfExists('expense');
        Schema::dropIfExists('expense_category');
    }
};
