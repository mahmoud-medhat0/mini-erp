<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_account_mapping', 'branch_id')) {
            Schema::table('accounting_account_mapping', function (Blueprint $table): void {
                $table->uuid('branch_id')->nullable()->after('key');
                $table->foreign('branch_id', 'account_mapping_branch_id_foreign')
                    ->references('id')
                    ->on('branch')
                    ->restrictOnDelete();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS accounting_account_mapping_key_unique');
        }

        DB::statement('DROP INDEX IF EXISTS accounting_account_mapping_global_key_unique');
        DB::statement('DROP INDEX IF EXISTS accounting_account_mapping_branch_key_unique');

        DB::statement('CREATE UNIQUE INDEX accounting_account_mapping_global_key_unique ON accounting_account_mapping ("key") WHERE branch_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX accounting_account_mapping_branch_key_unique ON accounting_account_mapping ("key", branch_id) WHERE branch_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS accounting_account_mapping_branch_key_unique');
        DB::statement('DROP INDEX IF EXISTS accounting_account_mapping_global_key_unique');

        if (Schema::hasColumn('accounting_account_mapping', 'branch_id')) {
            Schema::table('accounting_account_mapping', function (Blueprint $table): void {
                $table->dropForeign('account_mapping_branch_id_foreign');
                $table->dropColumn('branch_id');
            });
        }

        Schema::table('accounting_account_mapping', function (Blueprint $table): void {
            $table->unique('key', 'accounting_account_mapping_key_unique');
        });
    }
};
