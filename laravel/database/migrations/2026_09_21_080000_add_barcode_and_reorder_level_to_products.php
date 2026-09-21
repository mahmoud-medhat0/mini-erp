<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 32.3 - Barcode and reorder level on products
     * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §11.3): barcode is unique when
     * present (nullable, so products without one are unaffected) to prevent
     * a future scanning mix-up; reorder_level backs a manual "below minimum
     * stock" report rather than a real-time notification, per the
     * decision pack's bounded first slice.
     */
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table): void {
            $table->string('barcode', 64)->nullable()->unique()->after('code');
            $table->integer('reorder_level')->nullable()->after('is_purchase_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn(['barcode', 'reorder_level']);
        });
    }
};
