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
        if (! Schema::hasColumn($tableNames['permissions'], 'module')) {
            Schema::table($tableNames['permissions'], function (Blueprint $table): void {
                $table->string('module')->nullable();
            });
        }

        if (! Schema::hasColumn($tableNames['permissions'], 'action')) {
            Schema::table($tableNames['permissions'], function (Blueprint $table): void {
                $table->string('action')->nullable();
            });
        }

        if (! $this->hasIndex($tableNames['permissions'], 'permissions_module_index')) {
            Schema::table($tableNames['permissions'], function (Blueprint $table): void {
                $table->index('module');
            });
        }

        if (! $this->hasIndex($tableNames['permissions'], 'permissions_module_action_unique')) {
            Schema::table($tableNames['permissions'], function (Blueprint $table): void {
                $table->unique(['module', 'action']);
            });
        }

        if (! Schema::hasColumn($tableNames['roles'], 'is_template')) {
            Schema::table($tableNames['roles'], function (Blueprint $table): void {
                $table->boolean('is_template')->default(false);
            });
        }

        foreach ([$tableNames['model_has_permissions'], $tableNames['model_has_roles']] as $tableName) {
            if (! Schema::hasColumn($tableName, 'scope_json')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->json('scope_json')->nullable();
                });
            }
        }

        if (! Schema::hasColumn($tableNames['role_has_permissions'], 'scope_json')) {
            Schema::table($tableNames['role_has_permissions'], function (Blueprint $table): void {
                $table->json('scope_json')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (Schema::hasColumn($tableNames['role_has_permissions'], 'scope_json')) {
            Schema::table($tableNames['role_has_permissions'], function (Blueprint $table): void {
                $table->dropColumn('scope_json');
            });
        }

        foreach ([$tableNames['model_has_permissions'], $tableNames['model_has_roles']] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'scope_json')) {
                    $table->dropColumn('scope_json');
                }
            });
        }

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($tableNames): void {
            if (Schema::hasColumn($tableNames['roles'], 'is_template')) {
                $table->dropColumn('is_template');
            }
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) use ($tableNames): void {
            if ($this->hasIndex($tableNames['permissions'], 'permissions_module_action_unique')) {
                $table->dropUnique('permissions_module_action_unique');
            }

            if ($this->hasIndex($tableNames['permissions'], 'permissions_module_index')) {
                $table->dropIndex('permissions_module_index');
            }

            if (Schema::hasColumn($tableNames['permissions'], 'module')) {
                $table->dropColumn('module');
            }

            if (Schema::hasColumn($tableNames['permissions'], 'action')) {
                $table->dropColumn('action');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($index);
    }
};
