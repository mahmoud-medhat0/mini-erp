<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 30 - Partners & Equity (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §9).
     * Follows the same control-account-plus-tagged-subledger pattern already
     * used for AR (ar_control + ReceivableEntry), AP (ap_control +
     * PayableEntry), and employee loans (employee_loan_receivable +
     * PayrollEmployeeLoan) rather than creating one real chart-of-accounts
     * row per partner: two shared control accounts
     * (partner_capital_account, partner_loan_payable) carry every partner's
     * balance in the general ledger, while `partner_transaction` and
     * `partner_loan` are the per-partner subledger.
     */
    public function up(): void
    {
        Schema::create('partner', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->integer('share_bps')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('partner_transaction', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 64)->nullable()->unique();
            $table->foreignUuid('partner_id')->constrained('partner')->restrictOnDelete();
            $table->string('transaction_type');
            $table->date('transaction_date');
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->string('status')->default('draft');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['partner_id', 'status']);
            $table->index(['transaction_type', 'transaction_date']);
        });

        Schema::create('partner_loan', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_id')->constrained('partner')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('principal_minor');
            $table->bigInteger('remaining_balance_minor');
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
            $table->index(['partner_id', 'status']);
        });

        Schema::create('partner_loan_repayment', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_loan_id')->constrained('partner_loan')->cascadeOnDelete();
            $table->date('repayment_date');
            $table->bigInteger('amount_minor');
            $table->string('repayment_method');
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_account')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_account')->restrictOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_loan_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE partner ADD CONSTRAINT partner_share_bps_check CHECK (share_bps >= 0 AND share_bps <= 10000)');
            DB::statement("ALTER TABLE partner ADD CONSTRAINT partner_status_check CHECK (status IN ('active', 'inactive'))");

            DB::statement("ALTER TABLE partner_transaction ADD CONSTRAINT partner_transaction_type_check CHECK (transaction_type IN ('contribution', 'drawing', 'distribution'))");
            DB::statement("ALTER TABLE partner_transaction ADD CONSTRAINT partner_transaction_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
            DB::statement('ALTER TABLE partner_transaction ADD CONSTRAINT partner_transaction_amount_check CHECK (amount_minor > 0)');

            DB::statement("ALTER TABLE partner_loan ADD CONSTRAINT partner_loan_status_check CHECK (status IN ('active', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE partner_loan ADD CONSTRAINT partner_loan_method_check CHECK (disbursement_method IN ('cash', 'bank'))");
            DB::statement('ALTER TABLE partner_loan ADD CONSTRAINT partner_loan_amounts_check CHECK (principal_minor > 0 AND remaining_balance_minor >= 0 AND remaining_balance_minor <= principal_minor)');
            DB::statement('ALTER TABLE partner_loan ADD CONSTRAINT partner_loan_settlement_check CHECK ((disbursement_method = \'cash\' AND cash_account_id IS NOT NULL AND bank_account_id IS NULL) OR (disbursement_method = \'bank\' AND bank_account_id IS NOT NULL AND cash_account_id IS NULL))');

            DB::statement("ALTER TABLE partner_loan_repayment ADD CONSTRAINT partner_loan_repayment_method_check CHECK (repayment_method IN ('cash', 'bank'))");
            DB::statement('ALTER TABLE partner_loan_repayment ADD CONSTRAINT partner_loan_repayment_amount_check CHECK (amount_minor > 0)');
            DB::statement('ALTER TABLE partner_loan_repayment ADD CONSTRAINT partner_loan_repayment_settlement_check CHECK ((repayment_method = \'cash\' AND cash_account_id IS NOT NULL AND bank_account_id IS NULL) OR (repayment_method = \'bank\' AND bank_account_id IS NOT NULL AND cash_account_id IS NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_loan_repayment');
        Schema::dropIfExists('partner_loan');
        Schema::dropIfExists('partner_transaction');
        Schema::dropIfExists('partner');
    }
};
