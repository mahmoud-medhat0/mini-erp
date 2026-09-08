<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_08_23_110000_create_phase7_slice6_tax_period_tables` declared
 * `filed_by` / `generated_by` as `uuid`, but these reference `users.id`,
 * which is `bigint` everywhere else in the schema. That mismatch makes
 * TaxReturnService::generateDraftReturn()/fileReturn() fail on every call
 * with "invalid input syntax for type uuid" as soon as an integer actor id
 * is bound. This corrects the already-applied columns in place rather than
 * requiring a fresh migrate, since the source migration itself is also
 * fixed for any environment that migrates from scratch going forward.
 *
 * That source-migration fix means a *fresh* install (e.g. the test suite's
 * SQLite in-memory database, recreated from scratch on every run) already
 * creates these columns correctly, so this migration would otherwise try
 * to drop a foreign-keyed column that was never wrong there - which
 * SQLite's limited ALTER TABLE support rejects outright. Only Postgres
 * environments migrated before this fix landed still have the old `uuid`
 * columns on disk, so this is scoped to that driver rather than probing
 * column types (which needs doctrine/dbal and varies in how it reports
 * `uuid` across drivers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('tax_periods', function (Blueprint $table) {
            $table->dropColumn('filed_by');
        });
        Schema::table('tax_periods', function (Blueprint $table) {
            $table->foreignId('filed_by')->nullable()->after('filed_at')->constrained('users')->onDelete('set null');
        });

        Schema::table('tax_returns', function (Blueprint $table) {
            $table->dropColumn(['generated_by', 'filed_by']);
        });
        Schema::table('tax_returns', function (Blueprint $table) {
            $table->foreignId('generated_by')->nullable()->after('generated_at')->constrained('users')->onDelete('set null');
            $table->foreignId('filed_by')->nullable()->after('filed_at')->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('tax_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('filed_by');
        });
        Schema::table('tax_periods', function (Blueprint $table) {
            $table->uuid('filed_by')->nullable();
        });

        Schema::table('tax_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_by');
            $table->dropConstrainedForeignId('filed_by');
        });
        Schema::table('tax_returns', function (Blueprint $table) {
            $table->uuid('generated_by')->nullable();
            $table->uuid('filed_by')->nullable();
        });
    }
};
