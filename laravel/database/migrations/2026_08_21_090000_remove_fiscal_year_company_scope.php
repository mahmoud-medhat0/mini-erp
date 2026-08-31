<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_year')) {
            return;
        }

        $this->assertGlobalYearsCanBeUnique();

        if (Schema::hasColumn('fiscal_year', 'company_id')) {
            $this->dropForeignIfExists('fiscal_year', 'fiscal_year_company_id_foreign');
            $this->dropIndexIfExists('fiscal_year', 'fiscal_year_company_id_year_unique', 'unique');

            Schema::table('fiscal_year', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }

        if (! $this->hasIndex('fiscal_year', 'fiscal_year_year_unique')) {
            Schema::table('fiscal_year', function (Blueprint $table): void {
                $table->unique('year');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally one-way: FiscalYear is global to this ERP installation.
        // Rolling back would reintroduce unsupported Company/Tenant semantics.
    }

    private function assertGlobalYearsCanBeUnique(): void
    {
        $duplicateYears = DB::table('fiscal_year')
            ->select('year')
            ->groupBy('year')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('year')
            ->all();

        if ($duplicateYears === []) {
            return;
        }

        throw new RuntimeException(
            'Cannot remove fiscal_year.company_id because duplicate global fiscal years exist: '.
            implode(', ', $duplicateYears)
        );
    }

    private function dropForeignIfExists(string $table, string $foreignKey): void
    {
        if (! $this->hasForeignKey($table, $foreignKey)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($foreignKey): void {
            $table->dropForeign($foreignKey);
        });
    }

    private function dropIndexIfExists(string $table, string $index, string $type = 'index'): void
    {
        if (! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index, $type): void {
            match ($type) {
                'unique' => $table->dropUnique($index),
                default => $table->dropIndex($index),
            };
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($index);
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->pluck('name')
            ->contains($foreignKey);
    }
};
