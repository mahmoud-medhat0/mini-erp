<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 29 - Withholding Tax (WHT) technical framework only
     * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §8). No real Egyptian WHT
     * categories or rates are seeded here - the owner/accountant must
     * configure real tax_codes with tax_type=withholding and real tax_rates
     * through the existing /taxes/codes and /taxes/rates screens before this
     * is usable. Entry is deliberately manual (an accountant records a
     * withholding_tax_entry against a supplier they know is subject to WHT)
     * rather than auto-triggered from supplier bill/payment posting, because
     * *when* withholding legally applies is itself an unresolved policy
     * question this framework does not answer.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tax_codes DROP CONSTRAINT IF EXISTS chk_tc_tax_type');
            DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT chk_tc_tax_type CHECK (tax_type IN ('vat', 'withholding'))");
        }

        Schema::create('withholding_tax_entry', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 64)->nullable()->unique();
            $table->foreignUuid('tax_code_id')->constrained('tax_codes')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('supplier')->restrictOnDelete();
            $table->string('reference', 255)->nullable();
            $table->date('entry_date');
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('base_amount_minor');
            $table->integer('rate_bps');
            $table->bigInteger('withheld_amount_minor');
            $table->string('status')->default('draft');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['supplier_id', 'status']);
            $table->index(['tax_code_id', 'entry_date']);
            $table->index(['status', 'entry_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE withholding_tax_entry ADD CONSTRAINT withholding_tax_entry_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
            DB::statement('ALTER TABLE withholding_tax_entry ADD CONSTRAINT withholding_tax_entry_amounts_check CHECK (base_amount_minor > 0 AND rate_bps >= 0 AND withheld_amount_minor >= 0 AND withheld_amount_minor <= base_amount_minor)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_tax_entry');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tax_codes DROP CONSTRAINT IF EXISTS chk_tc_tax_type');
            DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT chk_tc_tax_type CHECK (tax_type IN ('vat'))");
        }
    }
};
