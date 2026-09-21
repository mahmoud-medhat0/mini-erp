<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 32.2 - Advances as an explicit classification
     * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §11.2, decision B): a plain
     * boolean set by the user at entry time rather than inferred from an
     * unallocated balance, so the classification is explicit and does not
     * change silently as later allocations are applied.
     */
    public function up(): void
    {
        Schema::table('customer_receipt', function (Blueprint $table): void {
            $table->boolean('is_advance')->default(false)->after('description');
        });

        Schema::table('supplier_payment', function (Blueprint $table): void {
            $table->boolean('is_advance')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipt', function (Blueprint $table): void {
            $table->dropColumn('is_advance');
        });

        Schema::table('supplier_payment', function (Blueprint $table): void {
            $table->dropColumn('is_advance');
        });
    }
};
