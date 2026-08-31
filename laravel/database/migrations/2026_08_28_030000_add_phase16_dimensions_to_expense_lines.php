<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expense_line', 'project_id')) {
            Schema::table('expense_line', function (Blueprint $table): void {
                $table->uuid('project_id')->nullable()->after('expense_account_id');
                $table->foreign('project_id', 'expense_line_project_id_foreign')
                    ->references('id')
                    ->on('project')
                    ->restrictOnDelete();
                $table->index('project_id', 'expense_line_project_id_index');
                $table->index(['expense_id', 'project_id'], 'expense_line_expense_id_project_id_index');
            });
        }

        if (! Schema::hasColumn('expense_line', 'cost_center_id')) {
            Schema::table('expense_line', function (Blueprint $table): void {
                $table->uuid('cost_center_id')->nullable()->after('project_id');
                $table->foreign('cost_center_id', 'expense_line_cost_center_id_foreign')
                    ->references('id')
                    ->on('cost_center')
                    ->restrictOnDelete();
                $table->index('cost_center_id', 'expense_line_cost_center_id_index');
                $table->index(['expense_id', 'cost_center_id'], 'expense_line_expense_id_cost_center_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expense_line', 'cost_center_id')) {
            Schema::table('expense_line', function (Blueprint $table): void {
                $table->dropIndex('expense_line_expense_id_cost_center_id_index');
                $table->dropIndex('expense_line_cost_center_id_index');
                $table->dropForeign('expense_line_cost_center_id_foreign');
                $table->dropColumn('cost_center_id');
            });
        }

        if (Schema::hasColumn('expense_line', 'project_id')) {
            Schema::table('expense_line', function (Blueprint $table): void {
                $table->dropIndex('expense_line_expense_id_project_id_index');
                $table->dropIndex('expense_line_project_id_index');
                $table->dropForeign('expense_line_project_id_foreign');
                $table->dropColumn('project_id');
            });
        }
    }
};
