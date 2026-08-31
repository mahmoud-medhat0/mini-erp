<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('exchange_rate')) {
            Schema::table('exchange_rate', function (Blueprint $table) {
                if (! Schema::hasColumn('exchange_rate', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('exchange_rate', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('exchange_rate')) {
            Schema::table('exchange_rate', function (Blueprint $table) {
                $table->dropColumn(['created_at', 'updated_at']);
            });
        }
    }
};
