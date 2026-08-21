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
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('operation');
            $table->char('key_hash', 64);
            $table->string('key_scope')->default('global');
            $table->foreignId('actor_id')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('response_type')->nullable();
            $table->string('response_id')->nullable();
            $table->json('response_json')->nullable();
            $table->text('error_code')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');

            $table->unique(['operation', 'key_hash', 'key_scope']);
            $table->index(['status', 'expires_at']);
            $table->index('expires_at');
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('company', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0);
        });

        Schema::table('branch', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0);
        });

        if (! $this->hasIndex('password_reset_tokens', 'password_reset_tokens_created_at_index')) {
            Schema::table('password_reset_tokens', function (Blueprint $table): void {
                $table->index('created_at');
            });
        }

        Schema::table('notification', function (Blueprint $table): void {
            $table->string('dedupe_key')->nullable()->after('target_ref');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_status_check CHECK (status IN ('pending', 'completed', 'failed'))");
            DB::statement('CREATE UNIQUE INDEX notification_dedupe_unique ON notification (company_id, user_id, dedupe_key) WHERE dedupe_key IS NOT NULL');

            return;
        }

        Schema::table('notification', function (Blueprint $table): void {
            $table->unique(['company_id', 'user_id', 'dedupe_key'], 'notification_dedupe_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notification')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS notification_dedupe_unique');
            } elseif ($this->hasIndex('notification', 'notification_dedupe_unique')) {
                Schema::table('notification', function (Blueprint $table): void {
                    $table->dropUnique('notification_dedupe_unique');
                });
            }

            Schema::table('notification', function (Blueprint $table): void {
                $table->dropColumn('dedupe_key');
            });
        }

        if (Schema::hasColumn('password_reset_tokens', 'created_at') && $this->hasIndex('password_reset_tokens', 'password_reset_tokens_created_at_index')) {
            Schema::table('password_reset_tokens', function (Blueprint $table): void {
                $table->dropIndex('password_reset_tokens_created_at_index');
            });
        }

        if (Schema::hasColumn('branch', 'lock_version')) {
            Schema::table('branch', function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }

        if (Schema::hasColumn('company', 'lock_version')) {
            Schema::table('company', function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }

        Schema::dropIfExists('idempotency_keys');
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($index);
    }
};
