<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentable_item', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('item_source')->default('standalone');
            $table->foreignUuid('product_id')->nullable()->constrained('product')->restrictOnDelete();
            $table->foreignUuid('fixed_asset_id')->nullable()->constrained('fixed_asset')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouse')->nullOnDelete();
            $table->string('status')->default('available');
            $table->string('condition_status')->default('good');
            $table->string('currency', 3);
            $table->string('serial_number')->nullable();
            $table->bigInteger('replacement_value_minor')->default(0);
            $table->bigInteger('daily_rate_minor')->nullable();
            $table->bigInteger('monthly_rate_minor')->nullable();
            $table->bigInteger('deposit_minor')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'is_active']);
            $table->index(['branch_id', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index(['item_source', 'status']);
        });

        Schema::create('rentable_item_status_event', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rentable_item_id')->constrained('rentable_item')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('event_type');
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at')->useCurrent();
            $table->timestamps();

            $table->index(['rentable_item_id', 'at']);
            $table->index(['event_type', 'at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE rentable_item ADD CONSTRAINT rentable_item_source_check CHECK (item_source IN ('standalone', 'product', 'fixed_asset'))");
            DB::statement("ALTER TABLE rentable_item ADD CONSTRAINT rentable_item_status_check CHECK (status IN ('available', 'reserved', 'allocated', 'rented', 'return_pending', 'returned', 'damaged', 'lost', 'maintenance', 'retired', 'inactive'))");
            DB::statement("ALTER TABLE rentable_item ADD CONSTRAINT rentable_item_condition_check CHECK (condition_status IN ('good', 'fair', 'damaged', 'lost', 'maintenance', 'retired'))");
            DB::statement('ALTER TABLE rentable_item ADD CONSTRAINT rentable_item_amounts_check CHECK (replacement_value_minor >= 0 AND (daily_rate_minor IS NULL OR daily_rate_minor >= 0) AND (monthly_rate_minor IS NULL OR monthly_rate_minor >= 0) AND (deposit_minor IS NULL OR deposit_minor >= 0))');
            DB::statement("ALTER TABLE rentable_item ADD CONSTRAINT rentable_item_source_reference_check CHECK (
                (item_source = 'standalone' AND product_id IS NULL AND fixed_asset_id IS NULL)
                OR (item_source = 'product' AND product_id IS NOT NULL AND fixed_asset_id IS NULL)
                OR (item_source = 'fixed_asset' AND fixed_asset_id IS NOT NULL AND product_id IS NULL)
            )");
            DB::statement("ALTER TABLE rentable_item_status_event ADD CONSTRAINT rentable_item_status_event_type_check CHECK (event_type IN ('created', 'status_changed', 'details_updated'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rentable_item_status_event');
        Schema::dropIfExists('rentable_item');
    }
};
