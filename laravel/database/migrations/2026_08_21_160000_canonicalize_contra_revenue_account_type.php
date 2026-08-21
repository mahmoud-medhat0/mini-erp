<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('account_type')) {
            return;
        }

        $canonicalCode = 'CONTRA_REVENUE';
        $legacyCode = 'REVENUE_CONTRA';

        $canonical = DB::table('account_type')->where('code', $canonicalCode)->first();
        $legacy = DB::table('account_type')->where('code', $legacyCode)->first();

        if (! $canonical && $legacy) {
            $update = [
                'code' => $canonicalCode,
                'normal_balance' => 'debit',
                'statement_type' => 'income_statement',
                'category' => 'contra_revenue',
                'is_contra' => true,
                'sort_order' => 66,
                'is_system' => true,
            ];

            if (Schema::hasColumn('account_type', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('account_type')
                ->where('id', $legacy->id)
                ->update($update);

            $canonical = DB::table('account_type')->where('code', $canonicalCode)->first();
            $legacy = null;
        }

        if ($canonical && $legacy) {
            $this->moveAccountTypeReferences($legacy->id, $canonical->id);

            DB::table('account_type')
                ->where('id', $legacy->id)
                ->delete();
        }

        $this->linkCanonicalTypeToCategory($canonicalCode);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data cleanup is intentionally not reversed; the canonical code is CONTRA_REVENUE.
    }

    private function moveAccountTypeReferences(string $fromId, string $toId): void
    {
        foreach (['account_group', 'account'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'account_type_id')) {
                continue;
            }

            DB::table($table)
                ->where('account_type_id', $fromId)
                ->update(['account_type_id' => $toId]);
        }
    }

    private function linkCanonicalTypeToCategory(string $canonicalCode): void
    {
        if (
            ! Schema::hasTable('account_category')
            || ! Schema::hasColumn('account_type', 'account_category_id')
        ) {
            return;
        }

        $category = DB::table('account_category')->where('code', $canonicalCode)->first();
        $canonical = DB::table('account_type')->where('code', $canonicalCode)->first();

        if (! $category || ! $canonical) {
            return;
        }

        $update = [
            'account_category_id' => $category->id,
            'category' => 'contra_revenue',
            'normal_balance' => 'debit',
            'statement_type' => 'income_statement',
            'is_contra' => true,
        ];

        if (Schema::hasColumn('account_type', 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::table('account_type')
            ->where('id', $canonical->id)
            ->update($update);
    }
};
