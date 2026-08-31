<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_handover', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('rental_contract_id')->constrained('rental_contract')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customer')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->date('handover_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->index(['status', 'handover_date']);
            $table->index(['rental_contract_id', 'status']);
            $table->index(['branch_id', 'handover_date']);
        });

        Schema::create('rental_handover_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_handover_id')->constrained('rental_handover')->cascadeOnDelete();
            $table->foreignUuid('rental_contract_line_id')->constrained('rental_contract_line')->restrictOnDelete();
            $table->foreignUuid('rentable_item_id')->constrained('rentable_item')->restrictOnDelete();
            $table->string('condition_out')->default('good');
            $table->json('accessories_out')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rental_handover_id', 'rental_contract_line_id']);
            $table->unique(['rental_handover_id', 'rentable_item_id']);
        });

        Schema::create('rental_return', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('rental_contract_id')->constrained('rental_contract')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customer')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->date('return_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->index(['status', 'return_date']);
            $table->index(['rental_contract_id', 'status']);
            $table->index(['branch_id', 'return_date']);
        });

        Schema::create('rental_return_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_return_id')->constrained('rental_return')->cascadeOnDelete();
            $table->foreignUuid('rental_contract_line_id')->constrained('rental_contract_line')->restrictOnDelete();
            $table->foreignUuid('rentable_item_id')->constrained('rentable_item')->restrictOnDelete();
            $table->string('condition_in')->default('good');
            $table->string('outcome')->default('returned');
            $table->bigInteger('estimated_damage_charge_minor')->default(0);
            $table->json('accessories_in')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->timestamps();

            $table->unique(['rental_return_id', 'rental_contract_line_id']);
            $table->unique(['rental_return_id', 'rentable_item_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rental_contract_status_event DROP CONSTRAINT IF EXISTS rental_contract_status_event_type_check');
            DB::statement("ALTER TABLE rental_contract_status_event ADD CONSTRAINT rental_contract_status_event_type_check CHECK (event_type IN ('created', 'submitted', 'approved', 'activated', 'completed', 'cancelled', 'details_updated'))");
            DB::statement("ALTER TABLE rental_handover ADD CONSTRAINT rental_handover_status_check CHECK (status IN ('draft', 'confirmed', 'cancelled'))");
            DB::statement('ALTER TABLE rental_handover ADD CONSTRAINT rental_handover_date_check CHECK (handover_date IS NOT NULL)');
            DB::statement("ALTER TABLE rental_handover_line ADD CONSTRAINT rental_handover_line_condition_check CHECK (condition_out IN ('good', 'fair', 'damaged', 'maintenance'))");
            DB::statement("ALTER TABLE rental_return ADD CONSTRAINT rental_return_status_check CHECK (status IN ('draft', 'submitted', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE rental_return ADD CONSTRAINT rental_return_date_check CHECK (return_date IS NOT NULL)');
            DB::statement("ALTER TABLE rental_return_line ADD CONSTRAINT rental_return_line_condition_check CHECK (condition_in IN ('good', 'fair', 'damaged', 'lost', 'maintenance'))");
            DB::statement("ALTER TABLE rental_return_line ADD CONSTRAINT rental_return_line_outcome_check CHECK (outcome IN ('returned', 'damaged', 'lost', 'maintenance'))");
            DB::statement('ALTER TABLE rental_return_line ADD CONSTRAINT rental_return_line_amounts_check CHECK (estimated_damage_charge_minor >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_return_line');
        Schema::dropIfExists('rental_return');
        Schema::dropIfExists('rental_handover_line');
        Schema::dropIfExists('rental_handover');

        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('rental_contract_status_event')) {
            DB::statement('ALTER TABLE rental_contract_status_event DROP CONSTRAINT IF EXISTS rental_contract_status_event_type_check');
            DB::statement("ALTER TABLE rental_contract_status_event ADD CONSTRAINT rental_contract_status_event_type_check CHECK (event_type IN ('created', 'submitted', 'approved', 'activated', 'cancelled', 'details_updated'))");
        }
    }
};
