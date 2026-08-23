<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->integer('useful_life_months')->default(60);
            $table->bigInteger('salvage_value_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fixed_asset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('asset_number')->unique();
            $table->json('name');
            $table->text('description')->nullable();
            $table->foreignUuid('fixed_asset_category_id')->constrained('fixed_asset_category')->restrictOnDelete();
            $table->string('currency', 3)->default('EGP');
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();
            $table->date('acquisition_date');
            $table->date('in_service_date');
            $table->bigInteger('cost_minor');
            $table->bigInteger('salvage_value_minor')->default(0);
            $table->integer('useful_life_months');
            $table->string('depreciation_method')->default('straight_line');
            $table->bigInteger('opening_accumulated_depreciation_minor')->default(0);
            $table->string('status')->default('draft');
            $table->string('serial_number')->nullable();
            $table->integer('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fixed_asset_category ADD CONSTRAINT chk_fixed_asset_category_useful_life CHECK (useful_life_months > 0)');
            DB::statement('ALTER TABLE fixed_asset_category ADD CONSTRAINT chk_fixed_asset_category_salvage_value CHECK (salvage_value_minor >= 0)');
            DB::statement('ALTER TABLE fixed_asset ADD CONSTRAINT chk_fixed_asset_cost_minor CHECK (cost_minor > 0)');
            DB::statement('ALTER TABLE fixed_asset ADD CONSTRAINT chk_fixed_asset_salvage_value CHECK (salvage_value_minor >= 0)');
            DB::statement('ALTER TABLE fixed_asset ADD CONSTRAINT chk_fixed_asset_useful_life CHECK (useful_life_months > 0)');
            DB::statement('ALTER TABLE fixed_asset ADD CONSTRAINT chk_fixed_asset_opening_accum_dep CHECK (opening_accumulated_depreciation_minor >= 0)');
            DB::statement("ALTER TABLE fixed_asset ADD CONSTRAINT chk_fixed_asset_depreciation_method CHECK (depreciation_method IN ('straight_line'))");
            DB::statement("ALTER TABLE fixed_asset ADD CONSTRAINT chk_fixed_asset_status CHECK (status IN ('draft', 'active', 'fully_depreciated', 'disposed'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_status');
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_depreciation_method');
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_opening_accum_dep');
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_useful_life');
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_salvage_value');
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_cost_minor');
            DB::statement('ALTER TABLE fixed_asset_category DROP CONSTRAINT IF EXISTS chk_fixed_asset_category_salvage_value');
            DB::statement('ALTER TABLE fixed_asset_category DROP CONSTRAINT IF EXISTS chk_fixed_asset_category_useful_life');
        }

        Schema::dropIfExists('fixed_asset');
        Schema::dropIfExists('fixed_asset_category');
    }
};
