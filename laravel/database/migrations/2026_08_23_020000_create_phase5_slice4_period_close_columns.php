<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_period', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_period', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('financial_period', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
            if (! Schema::hasColumn('financial_period', 'reopened_by')) {
                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('financial_period', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable();
            }
            if (! Schema::hasColumn('financial_period', 'close_note')) {
                $table->text('close_note')->nullable();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE financial_period 
                ADD CONSTRAINT chk_financial_period_status 
                CHECK (status IN ('open', 'closed', 'reopened'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE financial_period DROP CONSTRAINT IF EXISTS chk_financial_period_status');
        }

        Schema::table('financial_period', function (Blueprint $table) {
            if (Schema::hasColumn('financial_period', 'closed_by')) {
                $table->dropForeign(['closed_by']);
                $table->dropColumn('closed_by');
            }
            if (Schema::hasColumn('financial_period', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('financial_period', 'reopened_by')) {
                $table->dropForeign(['reopened_by']);
                $table->dropColumn('reopened_by');
            }
            if (Schema::hasColumn('financial_period', 'reopened_at')) {
                $table->dropColumn('reopened_at');
            }
            if (Schema::hasColumn('financial_period', 'close_note')) {
                $table->dropColumn('close_note');
            }
        });
    }
};
