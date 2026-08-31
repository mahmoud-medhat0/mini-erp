<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->string('status')->default('active');
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('base_salary_minor')->default(0);
            $table->string('payment_method')->default('manual');
            $table->text('notes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'code']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('payroll_component', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->string('type');
            $table->string('calculation_type')->default('fixed');
            $table->bigInteger('default_amount_minor')->nullable();
            $table->integer('rate_bps')->nullable();
            $table->foreignUuid('expense_account_id')->nullable()->constrained('account')->restrictOnDelete();
            $table->foreignUuid('liability_account_id')->nullable()->constrained('account')->restrictOnDelete();
            $table->integer('sort_order')->default(100);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('employee_payroll_component', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->foreignUuid('payroll_component_id')->constrained('payroll_component')->restrictOnDelete();
            $table->bigInteger('amount_minor')->nullable();
            $table->integer('rate_bps')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_active', 'effective_from']);
            $table->index(['payroll_component_id', 'is_active']);
        });

        Schema::create('payroll_period', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date');
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('status')->default('open');
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['status', 'start_date']);
        });

        Schema::create('payroll_run', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('payroll_period_id')->constrained('payroll_period')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->date('payroll_date');
            $table->string('run_type')->default('regular');
            $table->string('currency', 3);
            $table->string('status')->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('deductions_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
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
            $table->index(['status', 'payroll_date']);
            $table->index(['branch_id', 'payroll_date']);
            $table->index(['payroll_period_id', 'run_type']);
        });

        Schema::create('payroll_run_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_run')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employee')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('base_salary_minor')->default(0);
            $table->bigInteger('earnings_minor')->default(0);
            $table->bigInteger('deductions_minor')->default(0);
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['branch_id']);
        });

        Schema::create('payroll_run_line_component', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_line_id')->constrained('payroll_run_line')->cascadeOnDelete();
            $table->foreignUuid('payroll_component_id')->nullable()->constrained('payroll_component')->nullOnDelete();
            $table->foreignUuid('expense_account_id')->nullable()->constrained('account')->restrictOnDelete();
            $table->foreignUuid('liability_account_id')->nullable()->constrained('account')->restrictOnDelete();
            $table->string('code');
            $table->json('name');
            $table->string('type');
            $table->bigInteger('amount_minor');
            $table->timestamps();

            $table->index(['payroll_run_line_id', 'type']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE employee ADD CONSTRAINT employee_status_check CHECK (status IN ('active', 'inactive', 'terminated'))");
            DB::statement('ALTER TABLE employee ADD CONSTRAINT employee_salary_check CHECK (base_salary_minor >= 0)');
            DB::statement("ALTER TABLE employee ADD CONSTRAINT employee_payment_method_check CHECK (payment_method IN ('manual', 'cash', 'bank'))");
            DB::statement('ALTER TABLE employee ADD CONSTRAINT employee_dates_check CHECK (termination_date IS NULL OR termination_date >= hire_date)');

            DB::statement("ALTER TABLE payroll_component ADD CONSTRAINT payroll_component_type_check CHECK (type IN ('earning', 'deduction'))");
            DB::statement("ALTER TABLE payroll_component ADD CONSTRAINT payroll_component_calc_check CHECK (calculation_type IN ('fixed', 'percent_of_base'))");
            DB::statement('ALTER TABLE payroll_component ADD CONSTRAINT payroll_component_amount_check CHECK ((default_amount_minor IS NULL OR default_amount_minor >= 0) AND (rate_bps IS NULL OR (rate_bps >= 0 AND rate_bps <= 1000000)))');

            DB::statement('ALTER TABLE employee_payroll_component ADD CONSTRAINT employee_payroll_component_amount_check CHECK ((amount_minor IS NULL OR amount_minor >= 0) AND (rate_bps IS NULL OR (rate_bps >= 0 AND rate_bps <= 1000000)) AND (effective_to IS NULL OR effective_to >= effective_from))');

            DB::statement('ALTER TABLE payroll_period ADD CONSTRAINT payroll_period_month_check CHECK (month BETWEEN 1 AND 12)');
            DB::statement("ALTER TABLE payroll_period ADD CONSTRAINT payroll_period_status_check CHECK (status IN ('open', 'locked'))");
            DB::statement('ALTER TABLE payroll_period ADD CONSTRAINT payroll_period_dates_check CHECK (end_date >= start_date)');

            DB::statement("ALTER TABLE payroll_run ADD CONSTRAINT payroll_run_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'cancelled'))");
            DB::statement("ALTER TABLE payroll_run ADD CONSTRAINT payroll_run_type_check CHECK (run_type IN ('regular', 'bonus', 'adjustment'))");
            DB::statement('ALTER TABLE payroll_run ADD CONSTRAINT payroll_run_amounts_check CHECK (employee_count >= 0 AND gross_minor >= 0 AND deductions_minor >= 0 AND net_minor >= 0 AND gross_minor >= deductions_minor)');

            DB::statement('ALTER TABLE payroll_run_line ADD CONSTRAINT payroll_run_line_amounts_check CHECK (base_salary_minor >= 0 AND earnings_minor >= 0 AND deductions_minor >= 0 AND gross_minor >= 0 AND net_minor >= 0 AND gross_minor >= deductions_minor)');
            DB::statement("ALTER TABLE payroll_run_line_component ADD CONSTRAINT payroll_run_line_component_type_check CHECK (type IN ('earning', 'deduction'))");
            DB::statement('ALTER TABLE payroll_run_line_component ADD CONSTRAINT payroll_run_line_component_amount_check CHECK (amount_minor >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_line_component');
        Schema::dropIfExists('payroll_run_line');
        Schema::dropIfExists('payroll_run');
        Schema::dropIfExists('payroll_period');
        Schema::dropIfExists('employee_payroll_component');
        Schema::dropIfExists('payroll_component');
        Schema::dropIfExists('employee');
    }
};
