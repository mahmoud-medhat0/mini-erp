<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_disposal', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 50)->unique();
            $table->uuid('fixed_asset_id');
            $table->date('disposal_date');
            $table->uuid('financial_period_id');
            $table->string('disposal_type', 50);
            $table->bigInteger('proceeds_minor')->default(0);
            $table->bigInteger('net_book_value_minor')->default(0);
            $table->bigInteger('gain_minor')->default(0);
            $table->bigInteger('loss_minor')->default(0);
            $table->string('status', 50)->default('posted');
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('reversal_journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->integer('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_asset')
                ->onDelete('restrict');

            $table->foreign('financial_period_id')
                ->references('id')
                ->on('financial_period')
                ->onDelete('restrict');

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entry')
                ->onDelete('restrict');

            $table->foreign('reversal_journal_entry_id')
                ->references('id')
                ->on('journal_entry')
                ->onDelete('set null');

            $table->foreign('posted_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE fixed_asset_disposal
                ADD CONSTRAINT chk_fad_status
                CHECK (status IN ('posted', 'reversed'));
            ");

            DB::statement("
                ALTER TABLE fixed_asset_disposal
                ADD CONSTRAINT chk_fad_type
                CHECK (disposal_type IN ('sale', 'scrap', 'retirement'));
            ");

            DB::statement('
                ALTER TABLE fixed_asset_disposal
                ADD CONSTRAINT chk_fad_amounts
                CHECK (proceeds_minor >= 0 AND net_book_value_minor >= 0 AND gain_minor >= 0 AND loss_minor >= 0);
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_disposal');
    }
};
