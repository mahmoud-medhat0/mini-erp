<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepaid_schedule', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->date('schedule_date');
            $table->date('start_date');
            $table->unsignedInteger('months');
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('expense_category_id')->nullable()->constrained('expense_category')->nullOnDelete();
            $table->foreignUuid('prepaid_asset_account_id')->constrained('account')->restrictOnDelete();
            $table->foreignUuid('expense_account_id')->constrained('account')->restrictOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('currency', 3);
            $table->integer('fx_rate_e6')->default(1000000);
            $table->bigInteger('total_minor');
            $table->bigInteger('recognized_minor')->default(0);
            $table->string('status')->default('draft');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'schedule_date']);
            $table->index(['branch_id', 'schedule_date']);
        });

        Schema::create('prepaid_recognition', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('prepaid_schedule_id')->constrained('prepaid_schedule')->cascadeOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->date('recognition_date');
            $table->bigInteger('amount_minor');
            $table->string('status')->default('pending');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['prepaid_schedule_id', 'financial_period_id']);
            $table->index(['status', 'recognition_date']);
        });

        Schema::create('accrual_schedule', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->date('schedule_date');
            $table->date('start_date');
            $table->unsignedInteger('months');
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('expense_category_id')->nullable()->constrained('expense_category')->nullOnDelete();
            $table->foreignUuid('expense_account_id')->constrained('account')->restrictOnDelete();
            $table->foreignUuid('accrued_liability_account_id')->constrained('account')->restrictOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('currency', 3);
            $table->integer('fx_rate_e6')->default(1000000);
            $table->bigInteger('total_minor');
            $table->bigInteger('accrued_minor')->default(0);
            $table->string('status')->default('draft');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'schedule_date']);
            $table->index(['branch_id', 'schedule_date']);
        });

        Schema::create('accrual_entry', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('accrual_schedule_id')->constrained('accrual_schedule')->cascadeOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->date('accrual_date');
            $table->bigInteger('amount_minor');
            $table->string('status')->default('pending');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['accrual_schedule_id', 'financial_period_id']);
            $table->index(['status', 'accrual_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE prepaid_schedule ADD CONSTRAINT prepaid_schedule_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE prepaid_schedule ADD CONSTRAINT prepaid_schedule_amounts_check CHECK (months > 0 AND months <= 120 AND fx_rate_e6 = 1000000 AND total_minor > 0 AND recognized_minor >= 0 AND recognized_minor <= total_minor)');
            DB::statement("ALTER TABLE prepaid_recognition ADD CONSTRAINT prepaid_recognition_status_check CHECK (status IN ('pending', 'posted', 'reversed'))");
            DB::statement('ALTER TABLE prepaid_recognition ADD CONSTRAINT prepaid_recognition_amount_check CHECK (amount_minor > 0)');

            DB::statement("ALTER TABLE accrual_schedule ADD CONSTRAINT accrual_schedule_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE accrual_schedule ADD CONSTRAINT accrual_schedule_amounts_check CHECK (months > 0 AND months <= 120 AND fx_rate_e6 = 1000000 AND total_minor > 0 AND accrued_minor >= 0 AND accrued_minor <= total_minor)');
            DB::statement("ALTER TABLE accrual_entry ADD CONSTRAINT accrual_entry_status_check CHECK (status IN ('pending', 'posted', 'reversed'))");
            DB::statement('ALTER TABLE accrual_entry ADD CONSTRAINT accrual_entry_amount_check CHECK (amount_minor > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accrual_entry');
        Schema::dropIfExists('accrual_schedule');
        Schema::dropIfExists('prepaid_recognition');
        Schema::dropIfExists('prepaid_schedule');
    }
};
