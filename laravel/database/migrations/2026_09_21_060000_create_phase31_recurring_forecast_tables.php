<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 31 - Recurring transactions engine + simple forecasting
     * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §10). Per the decision pack's
     * recommended defaults: a generic standalone recurring_template table
     * (not an extension of the narrow prepaid/accrual tables), scoped in
     * this first slice to direct expenses only, always generating a DRAFT
     * expense for human review/approval - never auto-posted. Forecasting
     * itself (§10 decisions 1-2) needs no new schema: it is computed on the
     * fly from existing posted GL history via IncomeStatementReportService.
     */
    public function up(): void
    {
        Schema::create('recurring_template', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->string('document_type')->default('expense');
            $table->string('frequency');
            $table->integer('interval_count')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date');
            $table->date('last_generated_date')->nullable();
            $table->string('status')->default('active');
            $table->json('template_payload');
            $table->integer('generated_count')->default(0);
            $table->text('last_error')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'next_run_date']);
        });

        Schema::table('expense', function (Blueprint $table): void {
            $table->foreignUuid('recurring_template_id')->nullable()->after('id')->constrained('recurring_template')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE recurring_template ADD CONSTRAINT recurring_template_document_type_check CHECK (document_type IN ('expense'))");
            DB::statement("ALTER TABLE recurring_template ADD CONSTRAINT recurring_template_frequency_check CHECK (frequency IN ('weekly', 'monthly', 'quarterly', 'yearly'))");
            DB::statement("ALTER TABLE recurring_template ADD CONSTRAINT recurring_template_status_check CHECK (status IN ('active', 'paused', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE recurring_template ADD CONSTRAINT recurring_template_interval_check CHECK (interval_count > 0 AND generated_count >= 0)');
        }
    }

    public function down(): void
    {
        Schema::table('expense', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurring_template_id');
        });
        Schema::dropIfExists('recurring_template');
    }
};
