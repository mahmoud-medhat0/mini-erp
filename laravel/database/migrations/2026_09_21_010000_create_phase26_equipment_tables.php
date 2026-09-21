<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_category', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active']);
        });

        Schema::create('tool', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->foreignUuid('tool_category_id')->constrained('tool_category')->restrictOnDelete();
            $table->string('serial_number')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('status')->default('available');
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('custodian_employee_id')->nullable()->constrained('employee')->nullOnDelete();
            $table->string('location_note')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_active']);
            $table->index(['branch_id', 'status']);
            $table->index(['custodian_employee_id', 'status']);
            $table->index(['tool_category_id', 'status']);
        });

        Schema::create('tool_movement', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tool_id')->constrained('tool')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignUuid('from_custodian_employee_id')->nullable()->constrained('employee')->nullOnDelete();
            $table->foreignUuid('to_custodian_employee_id')->nullable()->constrained('employee')->nullOnDelete();
            $table->foreignUuid('from_branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('to_branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at')->useCurrent();
            $table->timestamps();

            $table->index(['tool_id', 'at']);
            $table->index(['event_type', 'at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tool ADD CONSTRAINT tool_status_check CHECK (status IN ('available', 'issued', 'damaged', 'lost', 'maintenance', 'retired'))");
            DB::statement('ALTER TABLE tool ADD CONSTRAINT tool_quantity_check CHECK (quantity >= 1)');
            DB::statement("ALTER TABLE tool_movement ADD CONSTRAINT tool_movement_event_type_check CHECK (event_type IN ('created', 'details_updated', 'issued', 'returned', 'transferred', 'status_changed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_movement');
        Schema::dropIfExists('tool');
        Schema::dropIfExists('tool_category');
    }
};
