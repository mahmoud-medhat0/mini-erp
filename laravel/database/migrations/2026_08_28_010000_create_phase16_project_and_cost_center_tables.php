<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('cost_center', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE project ADD CONSTRAINT project_status_check CHECK (status IN ('active', 'on_hold', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE project ADD CONSTRAINT project_date_order_check CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)');
            DB::statement("ALTER TABLE cost_center ADD CONSTRAINT cost_center_category_check CHECK (category IS NULL OR category IN ('administrative', 'sales', 'operations', 'finance', 'other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center');
        Schema::dropIfExists('project');
    }
};
