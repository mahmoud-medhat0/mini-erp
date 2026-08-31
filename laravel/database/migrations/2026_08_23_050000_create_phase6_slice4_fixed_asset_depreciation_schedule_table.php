<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciation_schedule', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('fixed_asset_id');
            $table->integer('period_number');
            $table->uuid('financial_period_id');
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->bigInteger('depreciation_minor');
            $table->bigInteger('accumulated_depreciation_minor');
            $table->bigInteger('net_book_value_minor');
            $table->string('status', 50)->default('planned');
            $table->uuid('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamps();

            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_asset')
                ->onDelete('cascade');

            $table->foreign('financial_period_id')
                ->references('id')
                ->on('financial_period')
                ->onDelete('restrict');

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entry')
                ->onDelete('restrict');

            $table->foreign('posted_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['fixed_asset_id', 'period_number']);
            $table->unique(['fixed_asset_id', 'financial_period_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE fixed_asset_depreciation_schedule
                ADD CONSTRAINT chk_fads_status
                CHECK (status IN ('planned', 'posted', 'reversed', 'skipped'));
            ");
            DB::statement('
                ALTER TABLE fixed_asset_depreciation_schedule
                ADD CONSTRAINT chk_fads_amounts
                CHECK (depreciation_minor >= 0 AND accumulated_depreciation_minor >= 0 AND net_book_value_minor >= 0);
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciation_schedule');
    }
};
