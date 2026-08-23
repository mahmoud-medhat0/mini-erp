<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_asset', function (Blueprint $table): void {
            $table->string('capitalization_mode', 50)->nullable();
            $table->date('capitalization_date')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->timestamp('capitalized_at')->nullable();
            $table->unsignedBigInteger('capitalized_by')->nullable();

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entry')
                ->onDelete('restrict');

            $table->foreign('capitalized_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE fixed_asset
                ADD CONSTRAINT chk_fixed_asset_capitalization_mode
                CHECK (capitalization_mode IS NULL OR capitalization_mode IN ('opening_already_capitalized', 'manual_capitalization'));
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fixed_asset DROP CONSTRAINT IF EXISTS chk_fixed_asset_capitalization_mode;');
        }

        Schema::table('fixed_asset', function (Blueprint $table): void {
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['capitalized_by']);
            $table->dropColumn([
                'capitalization_mode',
                'capitalization_date',
                'journal_entry_id',
                'capitalized_at',
                'capitalized_by',
            ]);
        });
    }
};
