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
        Schema::dropIfExists('company_user');

        $this->removeBranchCompanyAssumption();
        $this->removeNumberSequenceCompanyAndBranchAssumptions();
        $this->removeAuditCompanyAndBranchAssumptions();
        $this->removeAttachmentCompanyAssumption();
        $this->removeNotificationCompanyAssumption();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally one-way: rolling back would reintroduce unsupported
        // Company/Branch/User relationship assumptions.
    }

    private function removeBranchCompanyAssumption(): void
    {
        if (! Schema::hasTable('branch') || ! Schema::hasColumn('branch', 'company_id')) {
            return;
        }

        $this->dropForeignIfExists('branch', 'branch_company_id_foreign');
        $this->dropIndexIfExists('branch', 'branch_company_id_code_unique', 'unique');
        $this->dropIndexIfExists('branch', 'branch_company_id_index');

        Schema::table('branch', function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });
    }

    private function removeNumberSequenceCompanyAndBranchAssumptions(): void
    {
        if (! Schema::hasTable('number_sequence')) {
            return;
        }

        if (Schema::hasColumn('number_sequence', 'company_id')) {
            $this->dropForeignIfExists('number_sequence', 'number_sequence_company_id_foreign');
            $this->dropIndexIfExists('number_sequence', 'number_sequence_company_id_key_unique', 'unique');

            Schema::table('number_sequence', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('number_sequence', 'include_branch')) {
            Schema::table('number_sequence', function (Blueprint $table): void {
                $table->dropColumn('include_branch');
            });
        }

        if (! $this->hasIndex('number_sequence', 'number_sequence_key_unique')) {
            Schema::table('number_sequence', function (Blueprint $table): void {
                $table->unique('key');
            });
        }
    }

    private function removeAuditCompanyAndBranchAssumptions(): void
    {
        if (! Schema::hasTable('audit_log')) {
            return;
        }

        if (Schema::hasColumn('audit_log', 'company_id')) {
            $this->dropForeignIfExists('audit_log', 'audit_log_company_id_foreign');
            $this->dropIndexIfExists('audit_log', 'audit_log_company_id_entity_type_entity_id_index');
            $this->dropIndexIfExists('audit_log', 'audit_log_company_id_at_index');

            Schema::table('audit_log', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('audit_log', 'branch_id')) {
            $this->dropForeignIfExists('audit_log', 'audit_log_branch_id_foreign');

            Schema::table('audit_log', function (Blueprint $table): void {
                $table->dropColumn('branch_id');
            });
        }

        if (! $this->hasIndex('audit_log', 'audit_log_entity_type_entity_id_index')) {
            Schema::table('audit_log', function (Blueprint $table): void {
                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! $this->hasIndex('audit_log', 'audit_log_actor_id_at_index')) {
            Schema::table('audit_log', function (Blueprint $table): void {
                $table->index(['actor_id', 'at']);
            });
        }
    }

    private function removeAttachmentCompanyAssumption(): void
    {
        if (! Schema::hasTable('attachment') || ! Schema::hasColumn('attachment', 'company_id')) {
            return;
        }

        $this->dropForeignIfExists('attachment', 'attachment_company_id_foreign');
        $this->dropIndexIfExists('attachment', 'attachment_company_id_entity_type_entity_id_index');

        Schema::table('attachment', function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });

        if (! $this->hasIndex('attachment', 'attachment_entity_type_entity_id_index')) {
            Schema::table('attachment', function (Blueprint $table): void {
                $table->index(['entity_type', 'entity_id']);
            });
        }
    }

    private function removeNotificationCompanyAssumption(): void
    {
        if (! Schema::hasTable('notification')) {
            return;
        }

        $this->dropIndexIfExists('notification', 'notification_dedupe_unique', 'unique');

        if (Schema::hasColumn('notification', 'company_id')) {
            $this->dropForeignIfExists('notification', 'notification_company_id_foreign');
            $this->dropIndexIfExists('notification', 'notification_company_id_user_id_read_index');

            Schema::table('notification', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }

        if (! $this->hasIndex('notification', 'notification_user_id_read_index')) {
            Schema::table('notification', function (Blueprint $table): void {
                $table->index(['user_id', 'read']);
            });
        }

        if (Schema::hasColumn('notification', 'dedupe_key') && ! $this->hasIndex('notification', 'notification_user_dedupe_unique')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE UNIQUE INDEX notification_user_dedupe_unique ON notification (user_id, dedupe_key) WHERE dedupe_key IS NOT NULL');

                return;
            }

            Schema::table('notification', function (Blueprint $table): void {
                $table->unique(['user_id', 'dedupe_key'], 'notification_user_dedupe_unique');
            });
        }
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

        if (DB::connection()->getDriverName() === 'pgsql' && $index === 'notification_dedupe_unique') {
            DB::statement('DROP INDEX IF EXISTS notification_dedupe_unique');

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
