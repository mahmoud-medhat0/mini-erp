<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('number_sequence', function (Blueprint $table): void {
            $table->string('last_reset_period', 7)->nullable()->after('reset_policy');
        });

        // Previously next_value represented the last allocated value. From this
        // migration onward it consistently means the next value to allocate.
        DB::table('number_sequence')->update([
            'next_value' => DB::raw('CASE WHEN next_value < 1 THEN 1 ELSE next_value + 1 END'),
        ]);
    }

    public function down(): void
    {
        DB::table('number_sequence')->update([
            'next_value' => DB::raw('CASE WHEN next_value > 1 THEN next_value - 1 ELSE 0 END'),
        ]);

        Schema::table('number_sequence', function (Blueprint $table): void {
            $table->dropColumn('last_reset_period');
        });
    }
};
