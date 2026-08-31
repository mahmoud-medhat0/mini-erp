<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('budget')
            ->select('fiscal_year_id')
            ->where('status', 'active')
            ->groupBy('fiscal_year_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('fiscal_year_id')
            ->all();

        if ($duplicates !== []) {
            throw new RuntimeException('Cannot enforce one active budget per fiscal year while duplicate active budgets exist.');
        }

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX budget_one_active_per_fiscal_year_unique ON budget (fiscal_year_id) WHERE status = 'active'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS budget_one_active_per_fiscal_year_unique');
        }
    }
};
