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
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('password');
            $table->string('theme', 10)->default('system')->after('locale');
            $table->boolean('is_active')->default(true)->after('theme')->index();
            $table->boolean('mfa_enabled')->default(false)->after('is_active');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_check CHECK (locale IN ('en', 'ar'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_theme_check CHECK (theme IN ('light', 'dark', 'system'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_theme_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_locale_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['locale', 'theme', 'is_active', 'mfa_enabled']);
        });
    }
};
