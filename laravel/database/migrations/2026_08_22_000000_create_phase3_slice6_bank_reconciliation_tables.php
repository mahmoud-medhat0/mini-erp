<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_account_id')->constrained('bank_account')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();

            $table->string('statement_reference')->nullable();
            $table->date('date_from');
            $table->date('date_to');

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('statement_opening_balance_minor');
            $table->bigInteger('statement_closing_balance_minor');

            $table->bigInteger('system_opening_balance_minor')->default(0);
            $table->bigInteger('system_movement_minor')->default(0);
            $table->bigInteger('system_closing_balance_minor')->default(0);
            $table->bigInteger('statement_movement_minor')->default(0);
            $table->bigInteger('matched_system_movement_minor')->default(0);
            $table->bigInteger('difference_minor')->default(0);

            $table->string('status')->default('draft');
            $table->timestamp('reconciled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['bank_account_id', 'status']);
            $table->index(['date_from', 'date_to']);
            $table->index('currency');
        });

        Schema::create('bank_reconciliation_line', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_reconciliation_id')->constrained('bank_reconciliation')->cascadeOnDelete();
            $table->unsignedInteger('line_no');

            $table->date('statement_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);

            $table->foreignUuid('matched_ledger_entry_id')->nullable()->constrained('ledger_entry')->restrictOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('unmatched');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['bank_reconciliation_id', 'line_no']);
            $table->index(['bank_reconciliation_id', 'status']);
            $table->index('matched_ledger_entry_id');
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE bank_reconciliation ADD CONSTRAINT check_bank_recon_status CHECK (status IN ('draft', 'in_progress', 'reconciled'))");
            DB::statement('ALTER TABLE bank_reconciliation ADD CONSTRAINT check_bank_recon_dates CHECK (date_from <= date_to)');

            DB::statement("ALTER TABLE bank_reconciliation_line ADD CONSTRAINT check_bank_recon_line_status CHECK (status IN ('unmatched', 'matched'))");
            DB::statement('ALTER TABLE bank_reconciliation_line ADD CONSTRAINT check_bank_recon_line_amounts CHECK (debit_minor >= 0 AND credit_minor >= 0 AND ((debit_minor > 0 AND credit_minor = 0) OR (debit_minor = 0 AND credit_minor > 0)))');

            DB::statement('CREATE UNIQUE INDEX bank_recon_line_matched_ledger_unique ON bank_reconciliation_line (matched_ledger_entry_id) WHERE matched_ledger_entry_id IS NOT NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX bank_recon_line_matched_ledger_unique ON bank_reconciliation_line (matched_ledger_entry_id) WHERE matched_ledger_entry_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_line');
        Schema::dropIfExists('bank_reconciliation');
    }
};
