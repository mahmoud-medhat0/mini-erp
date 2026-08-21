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
        $tableNames = config('permission.table_names');
        $modelColumn = config('permission.column_names.model_morph_key', 'model_id');
        $rolePivot = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $permissionPivot = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

        $this->dropCompanyScopeFromModelPivot(
            $tableNames['model_has_roles'],
            [$rolePivot, $modelColumn, 'model_type'],
        );

        $this->dropCompanyScopeFromModelPivot(
            $tableNames['model_has_permissions'],
            [$permissionPivot, $modelColumn, 'model_type'],
        );

        if (Schema::hasColumn($tableNames['roles'], 'company_id')) {
            Schema::table($tableNames['roles'], function (Blueprint $table): void {
                if ($this->hasIndex('roles', 'roles_company_id_name_guard_name_unique')) {
                    $table->dropUnique('roles_company_id_name_guard_name_unique');
                }

                if ($this->hasIndex('roles', 'roles_team_foreign_key_index')) {
                    $table->dropIndex('roles_team_foreign_key_index');
                }

                $table->dropColumn('company_id');
            });
        }

        if (! $this->hasIndex($tableNames['roles'], 'roles_name_guard_name_unique')) {
            Schema::table($tableNames['roles'], function (Blueprint $table): void {
                $table->unique(['name', 'guard_name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    /**
     * @param  list<string>  $primaryColumns
     */
    private function dropCompanyScopeFromModelPivot(string $tableName, array $primaryColumns): void
    {
        if (! Schema::hasColumn($tableName, 'company_id')) {
            return;
        }

        if (in_array('company_id', $this->primaryColumns($tableName), true)) {
            $this->replacePrimaryKey($tableName, $primaryColumns);
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $indexName = "{$tableName}_team_foreign_key_index";

            if ($this->hasIndex($tableName, $indexName)) {
                $table->dropIndex($indexName);
            }

            $table->dropColumn('company_id');
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function replacePrimaryKey(string $tableName, array $columns): void
    {
        $primaryName = $this->primaryName($tableName);

        if ($primaryName !== null) {
            Schema::table($tableName, function (Blueprint $table) use ($primaryName): void {
                $table->dropPrimary($primaryName);
            });
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            $table->primary($columns);
        });
    }

    /**
     * @return list<string>
     */
    private function primaryColumns(string $tableName): array
    {
        $primary = collect(Schema::getIndexes($tableName))->firstWhere('primary', true);

        return $primary['columns'] ?? [];
    }

    private function primaryName(string $tableName): ?string
    {
        $primary = collect(Schema::getIndexes($tableName))->firstWhere('primary', true);

        return $primary['name'] ?? null;
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return collect(Schema::getIndexes($tableName))
            ->pluck('name')
            ->contains($indexName);
    }
};
