<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_cheque', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('customer_id')->constrained('customer')->restrictOnDelete();
            $table->string('cheque_number');
            $table->string('drawer_bank_name')->nullable();

            $table->foreignUuid('received_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('received_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('received_date')->nullable();

            $table->date('deposited_date')->nullable();
            $table->foreignUuid('deposit_bank_account_id')->nullable()->constrained('bank_account')->restrictOnDelete();

            $table->foreignUuid('cleared_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('cleared_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('cleared_date')->nullable();

            $table->foreignUuid('returned_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('returned_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('returned_date')->nullable();

            $table->foreignUuid('bounced_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('bounced_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('bounced_date')->nullable();

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status')->default('draft');

            $table->foreignUuid('receive_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('clear_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('return_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('bounce_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();

            $table->foreignUuid('receivable_entry_id')->nullable()->constrained('receivable_entry')->restrictOnDelete();
            $table->foreignUuid('return_receivable_entry_id')->nullable()->constrained('receivable_entry')->restrictOnDelete();
            $table->foreignUuid('bounce_receivable_entry_id')->nullable()->constrained('receivable_entry')->restrictOnDelete();

            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deposited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bounced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'received_date']);
            $table->index('currency');
        });

        Schema::create('outgoing_cheque', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('supplier_id')->constrained('supplier')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_account')->restrictOnDelete();
            $table->string('cheque_number');
            $table->string('payee_name')->nullable();

            $table->foreignUuid('issued_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('issued_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('issued_date')->nullable();

            $table->foreignUuid('cleared_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('cleared_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('cleared_date')->nullable();

            $table->foreignUuid('returned_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('returned_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('returned_date')->nullable();

            $table->foreignUuid('cancelled_fiscal_year_id')->nullable()->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('cancelled_financial_period_id')->nullable()->constrained('financial_period')->restrictOnDelete();
            $table->date('cancelled_date')->nullable();

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->string('status')->default('draft');

            $table->foreignUuid('issue_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('clear_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('return_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->foreignUuid('cancel_journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();

            $table->foreignUuid('payable_entry_id')->nullable()->constrained('payable_entry')->restrictOnDelete();
            $table->foreignUuid('return_payable_entry_id')->nullable()->constrained('payable_entry')->restrictOnDelete();
            $table->foreignUuid('cancel_payable_entry_id')->nullable()->constrained('payable_entry')->restrictOnDelete();

            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['bank_account_id', 'status']);
            $table->index(['status', 'issued_date']);
            $table->index('currency');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable'))");

            DB::statement('ALTER TABLE incoming_cheque ADD CONSTRAINT check_incoming_cheque_amount CHECK (amount_minor > 0)');
            DB::statement('ALTER TABLE incoming_cheque ADD CONSTRAINT check_incoming_cheque_fx CHECK (fx_rate_e6 > 0)');
            DB::statement("ALTER TABLE incoming_cheque ADD CONSTRAINT check_incoming_cheque_status CHECK (status IN ('draft', 'received', 'deposited', 'cleared', 'bounced', 'returned', 'cancelled'))");

            DB::statement('ALTER TABLE outgoing_cheque ADD CONSTRAINT check_outgoing_cheque_amount CHECK (amount_minor > 0)');
            DB::statement('ALTER TABLE outgoing_cheque ADD CONSTRAINT check_outgoing_cheque_fx CHECK (fx_rate_e6 > 0)');
            DB::statement("ALTER TABLE outgoing_cheque ADD CONSTRAINT check_outgoing_cheque_status CHECK (status IN ('draft', 'issued', 'cleared', 'returned', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_cheque');
        Schema::dropIfExists('incoming_cheque');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::table('accounting_account_mapping')
                ->whereIn('key', ['cheques_under_collection', 'cheques_payable'])
                ->delete();

            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
            DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ('ar_control', 'ap_control', 'opening_balance_offset'))");
        }
    }
};
