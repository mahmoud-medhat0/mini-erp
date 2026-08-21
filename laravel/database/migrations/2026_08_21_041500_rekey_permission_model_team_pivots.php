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
        $columnNames = config('permission.column_names');
        $tableNames = config('permission.table_names');
        $teamColumn = $columnNames['team_foreign_key'] ?? 'company_id';
        $modelColumn = $columnNames['model_morph_key'] ?? 'model_id';
        $rolePivot = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';

        $this->ensurePrimaryKey(
            $tableNames['model_has_roles'],
            [$teamColumn, $rolePivot, $modelColumn, 'model_type'],
            'model_has_roles_role_model_type_primary',
        );

        $this->ensurePrimaryKey(
            $tableNames['model_has_permissions'],
            [$teamColumn, $permissionPivot, $modelColumn, 'model_type'],
            'model_has_permissions_permission_model_type_primary',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnNames = config('permission.column_names');
        $tableNames = config('permission.table_names');
        $teamColumn = $columnNames['team_foreign_key'] ?? 'company_id';
        $modelColumn = $columnNames['model_morph_key'] ?? 'model_id';
        $rolePivot = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (in_array($teamColumn, $this->primaryColumns($tableNames['model_has_roles']), true)) {
            $this->replacePrimaryKey(
                $tableNames['model_has_roles'],
                [$rolePivot, $modelColumn, 'model_type'],
                'model_has_roles_role_model_type_primary',
            );
        }

        if (in_array($teamColumn, $this->primaryColumns($tableNames['model_has_permissions']), true)) {
            $this->replacePrimaryKey(
                $tableNames['model_has_permissions'],
                [$permissionPivot, $modelColumn, 'model_type'],
                'model_has_permissions_permission_model_type_primary',
            );
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensurePrimaryKey(string $table, array $columns, string $name): void
    {
        if ($this->primaryColumns($table) === $columns) {
            return;
        }

        $this->replacePrimaryKey($table, $columns, $name);
    }

    /**
     * @param  list<string>  $columns
     */
    private function replacePrimaryKey(string $table, array $columns, string $name): void
    {
        $primaryName = $this->primaryName($table);

        if ($primaryName !== null) {
            Schema::table($table, function (Blueprint $table) use ($primaryName): void {
                $table->dropPrimary($primaryName);
            });
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->primary($columns, $name);
        });
    }

    /**
     * @return list<string>
     */
    private function primaryColumns(string $table): array
    {
        $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);

        return $primary['columns'] ?? [];
    }

    private function primaryName(string $table): ?string
    {
        $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);

        return $primary['name'] ?? null;
    }
};
