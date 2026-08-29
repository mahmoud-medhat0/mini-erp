<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('fiscal_year_id')->references('id')->on('fiscal_year')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('version_code');
            $table->json('name');
            $table->text('description')->nullable();
            $table->string('status');
            $table->string('default_currency', 3);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('default_currency')->references('code')->on('currency')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['fiscal_year_id', 'version_code']);
            $table->index(['fiscal_year_id', 'status']);
            $table->index('status');
            $table->index('default_currency');
        });

        Schema::create('budget_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_id')->references('id')->on('budget')->cascadeOnDelete();
            $table->foreignUuid('financial_period_id')->references('id')->on('financial_period')->restrictOnDelete();
            $table->foreignUuid('account_id')->references('id')->on('account')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->references('id')->on('project')->restrictOnDelete();
            $table->foreignUuid('cost_center_id')->nullable()->references('id')->on('cost_center')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete()->cascadeOnUpdate();

            $table->index(['budget_id', 'financial_period_id']);
            $table->index(['budget_id', 'account_id']);
            $table->index(['budget_id', 'project_id']);
            $table->index(['budget_id', 'cost_center_id']);
            $table->index(['budget_id', 'currency']);
            $table->index(['financial_period_id', 'account_id', 'currency']);
            $table->index(['project_id', 'cost_center_id', 'currency']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE budget ADD CONSTRAINT budget_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'active', 'archived', 'cancelled'))");
            DB::statement('ALTER TABLE budget_line ADD CONSTRAINT budget_line_amount_check CHECK (amount_minor >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line');
        Schema::dropIfExists('budget');
    }
};
