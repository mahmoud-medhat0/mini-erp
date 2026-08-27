<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_contract', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('customer_id')->constrained('customer')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->date('contract_date');
            $table->date('start_date');
            $table->date('expected_end_date');
            $table->date('actual_end_date')->nullable();
            $table->string('currency', 3);
            $table->string('billing_cycle')->default('monthly');
            $table->bigInteger('estimated_rent_minor')->default(0);
            $table->bigInteger('deposit_minor')->default(0);
            $table->bigInteger('total_estimated_minor')->default(0);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'contract_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['branch_id', 'start_date']);
        });

        Schema::create('rental_contract_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_contract_id')->constrained('rental_contract')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignUuid('rentable_item_id')->constrained('rentable_item')->restrictOnDelete();
            $table->json('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('rate_type')->default('monthly');
            $table->bigInteger('rate_minor');
            $table->unsignedInteger('estimated_units')->default(1);
            $table->bigInteger('estimated_amount_minor')->default(0);
            $table->bigInteger('deposit_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rental_contract_id', 'rentable_item_id']);
            $table->index(['rentable_item_id', 'start_date', 'end_date']);
        });

        Schema::create('rental_contract_status_event', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_contract_id')->constrained('rental_contract')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('event_type');
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at')->useCurrent();
            $table->timestamps();

            $table->index(['rental_contract_id', 'at']);
            $table->index(['event_type', 'at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE rental_contract ADD CONSTRAINT rental_contract_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE rental_contract ADD CONSTRAINT rental_contract_billing_cycle_check CHECK (billing_cycle IN ('daily', 'weekly', 'monthly', 'fixed'))");
            DB::statement('ALTER TABLE rental_contract ADD CONSTRAINT rental_contract_dates_check CHECK (expected_end_date >= start_date AND (actual_end_date IS NULL OR actual_end_date >= start_date))');
            DB::statement('ALTER TABLE rental_contract ADD CONSTRAINT rental_contract_amounts_check CHECK (estimated_rent_minor >= 0 AND deposit_minor >= 0 AND total_estimated_minor >= 0)');
            DB::statement("ALTER TABLE rental_contract_line ADD CONSTRAINT rental_contract_line_rate_type_check CHECK (rate_type IN ('daily', 'weekly', 'monthly', 'fixed'))");
            DB::statement('ALTER TABLE rental_contract_line ADD CONSTRAINT rental_contract_line_dates_check CHECK (end_date >= start_date)');
            DB::statement('ALTER TABLE rental_contract_line ADD CONSTRAINT rental_contract_line_amounts_check CHECK (rate_minor >= 0 AND estimated_units > 0 AND estimated_amount_minor >= 0 AND deposit_minor >= 0)');
            DB::statement("ALTER TABLE rental_contract_status_event ADD CONSTRAINT rental_contract_status_event_type_check CHECK (event_type IN ('created', 'submitted', 'approved', 'activated', 'cancelled', 'details_updated'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_contract_status_event');
        Schema::dropIfExists('rental_contract_line');
        Schema::dropIfExists('rental_contract');
    }
};
