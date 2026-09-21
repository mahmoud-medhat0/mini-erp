<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_employee_loan', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employee')->restrictOnDelete();
            $table->string('loan_type')->default('loan');
            $table->string('currency', 3);
            $table->bigInteger('principal_minor');
            $table->bigInteger('remaining_balance_minor');
            $table->bigInteger('installment_amount_minor');
            $table->date('disbursement_date');
            $table->string('disbursement_method');
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_account')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_account')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['employee_id', 'status']);
            $table->index(['status']);
        });

        Schema::create('payroll_employee_loan_installment', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_employee_loan_id')->constrained('payroll_employee_loan')->cascadeOnDelete();
            $table->foreignUuid('payroll_run_id')->nullable()->constrained('payroll_run')->nullOnDelete();
            $table->foreignUuid('payroll_run_line_id')->nullable()->constrained('payroll_run_line')->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->date('applied_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['payroll_employee_loan_id', 'applied_date']);
            $table->unique(['payroll_employee_loan_id', 'payroll_run_id']);
        });

        Schema::table('payroll_run_line_component', function (Blueprint $table): void {
            $table->foreignUuid('payroll_employee_loan_id')->nullable()->after('payroll_component_id')->constrained('payroll_employee_loan')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payroll_employee_loan ADD CONSTRAINT payroll_employee_loan_type_check CHECK (loan_type IN ('loan', 'advance'))");
            DB::statement("ALTER TABLE payroll_employee_loan ADD CONSTRAINT payroll_employee_loan_status_check CHECK (status IN ('active', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE payroll_employee_loan ADD CONSTRAINT payroll_employee_loan_method_check CHECK (disbursement_method IN ('cash', 'bank'))");
            DB::statement('ALTER TABLE payroll_employee_loan ADD CONSTRAINT payroll_employee_loan_amounts_check CHECK (principal_minor > 0 AND installment_amount_minor > 0 AND installment_amount_minor <= principal_minor AND remaining_balance_minor >= 0 AND remaining_balance_minor <= principal_minor)');
            DB::statement('ALTER TABLE payroll_employee_loan ADD CONSTRAINT payroll_employee_loan_settlement_check CHECK ((disbursement_method = \'cash\' AND cash_account_id IS NOT NULL AND bank_account_id IS NULL) OR (disbursement_method = \'bank\' AND bank_account_id IS NOT NULL AND cash_account_id IS NULL))');
            DB::statement('ALTER TABLE payroll_employee_loan_installment ADD CONSTRAINT payroll_employee_loan_installment_amount_check CHECK (amount_minor > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('payroll_run_line_component', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payroll_employee_loan_id');
        });
        Schema::dropIfExists('payroll_employee_loan_installment');
        Schema::dropIfExists('payroll_employee_loan');
    }
};
