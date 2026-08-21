<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currency', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $this->jsonColumn($table, 'name');
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('exponent')->default(2);
        });

        Schema::create('company', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->jsonColumn($table, 'name');
            $table->char('base_currency', 3)->default('EGP');
            $this->jsonColumn($table, 'settings_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('branch', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code');
            $this->jsonColumn($table, 'name');
            $table->boolean('is_active')->default(true);

            $table->foreign('company_id')->references('id')->on('company')->restrictOnDelete();
            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->foreignId('user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('company')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->primary(['company_id', 'user_id']);
        });

        Schema::create('exchange_rate', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('currency', 3);
            $table->date('date');
            $table->bigInteger('rate_e6');

            $table->unique(['currency', 'date']);
            $table->index(['currency', 'date']);
        });

        Schema::create('fiscal_year', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->integer('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open');

            $table->foreign('company_id')->references('id')->on('company')->restrictOnDelete();
            $table->unique(['company_id', 'year']);
        });

        Schema::create('financial_period', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fiscal_year_id');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open');

            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_year')->cascadeOnDelete();
            $table->unique(['fiscal_year_id', 'month']);
        });

        Schema::create('number_sequence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('key');
            $table->string('doc_type');
            $table->string('prefix');
            $table->boolean('include_year')->default(true);
            $table->boolean('include_branch')->default(false);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->string('reset_policy')->default('yearly');
            $table->unsignedInteger('next_value')->default(0);

            $table->foreign('company_id')->references('id')->on('company')->restrictOnDelete();
            $table->unique(['company_id', 'key']);
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $this->jsonColumn($table, 'before_json')->nullable();
            $this->jsonColumn($table, 'after_json')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip')->nullable();
            $table->string('device')->nullable();
            $table->timestamp('at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('company')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branch')->nullOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'entity_type', 'entity_id']);
            $table->index(['company_id', 'at']);
        });

        Schema::create('attachment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->string('file_ref');
            $table->string('name');
            $table->string('mime')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable();
            $table->timestamp('at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('company')->restrictOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'entity_type', 'entity_id']);
        });

        Schema::create('notification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->foreignId('user_id');
            $table->string('type');
            $table->string('target_ref');
            $table->boolean('read')->default(false);
            $table->timestamp('at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('company')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['company_id', 'user_id', 'read']);
        });

        $this->addCheckConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification');
        Schema::dropIfExists('attachment');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('number_sequence');
        Schema::dropIfExists('financial_period');
        Schema::dropIfExists('fiscal_year');
        Schema::dropIfExists('exchange_rate');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('branch');
        Schema::dropIfExists('company');
        Schema::dropIfExists('currency');
    }

    private function jsonColumn(Blueprint $table, string $name): mixed
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? $table->jsonb($name)
            : $table->json($name);
    }

    private function addCheckConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE fiscal_year ADD CONSTRAINT fiscal_year_status_check CHECK (status IN ('open', 'closed', 'locked'))");
        DB::statement('ALTER TABLE financial_period ADD CONSTRAINT financial_period_month_check CHECK (month BETWEEN 1 AND 12)');
        DB::statement("ALTER TABLE financial_period ADD CONSTRAINT financial_period_status_check CHECK (status IN ('open', 'closed', 'reopened'))");
    }
};
