<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::transaction(function (): void {
            $legacy = DB::table('roles')
                ->where('name', 'COMPANY_ADMIN')
                ->where('guard_name', 'web')
                ->first();

            if (! $legacy) {
                return;
            }

            $replacement = DB::table('roles')
                ->where('name', 'ERP_ADMIN')
                ->where('guard_name', 'web')
                ->first();

            if (! $replacement) {
                DB::table('roles')
                    ->where('id', $legacy->id)
                    ->update(['name' => 'ERP_ADMIN', 'updated_at' => now()]);

                return;
            }

            foreach (DB::table('role_has_permissions')->where('role_id', $legacy->id)->get() as $permission) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permission->permission_id,
                    'role_id' => $replacement->id,
                    'scope_json' => $permission->scope_json ?? null,
                ]);
            }

            foreach (DB::table('model_has_roles')->where('role_id', $legacy->id)->get() as $assignment) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $replacement->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                    'scope_json' => $assignment->scope_json ?? null,
                ]);
            }

            DB::table('roles')->where('id', $legacy->id)->delete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::transaction(function (): void {
            $current = DB::table('roles')
                ->where('name', 'ERP_ADMIN')
                ->where('guard_name', 'web')
                ->first();

            $legacy = DB::table('roles')
                ->where('name', 'COMPANY_ADMIN')
                ->where('guard_name', 'web')
                ->first();

            if ($current && ! $legacy) {
                DB::table('roles')
                    ->where('id', $current->id)
                    ->update(['name' => 'COMPANY_ADMIN', 'updated_at' => now()]);
            }
        });
    }
};
