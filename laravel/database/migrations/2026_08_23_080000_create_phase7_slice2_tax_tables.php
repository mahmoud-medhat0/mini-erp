<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->string('tax_type')->default('vat');
            $table->string('calculation_mode')->default('exclusive');
            $table->string('recoverability_mode')->default('full');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tax_code_id')->constrained('tax_codes')->cascadeOnDelete();
            $table->integer('rate_bps');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT chk_tc_tax_type CHECK (tax_type IN ('vat'))");
            DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT chk_tc_calc_mode CHECK (calculation_mode IN ('exclusive', 'inclusive', 'exempt'))");
            DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT chk_tc_rec_mode CHECK (recoverability_mode IN ('full', 'none'))");
            DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT chk_tr_rate_bps CHECK (rate_bps >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_codes');
    }
};
