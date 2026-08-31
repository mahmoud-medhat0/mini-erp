<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_location', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->json('name');
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::table('fixed_asset', function (Blueprint $table) {
            if (! Schema::hasColumn('fixed_asset', 'branch_id')) {
                $table->foreignUuid('branch_id')->nullable()->after('serial_number')->constrained('branch')->nullOnDelete();
                $table->index('branch_id');
            }

            if (! Schema::hasColumn('fixed_asset', 'fixed_asset_location_id')) {
                $table->foreignUuid('fixed_asset_location_id')->nullable()->after('branch_id')->constrained('fixed_asset_location')->nullOnDelete();
                $table->index('fixed_asset_location_id');
            }
        });

        Schema::create('fixed_asset_movement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_asset')->cascadeOnDelete();
            $table->date('movement_date');
            $table->foreignUuid('from_branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('to_branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('from_location_id')->nullable()->constrained('fixed_asset_location')->nullOnDelete();
            $table->foreignUuid('to_location_id')->nullable()->constrained('fixed_asset_location')->nullOnDelete();
            $table->json('from_snapshot_json')->nullable();
            $table->json('to_snapshot_json')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fixed_asset_id', 'movement_date']);
            $table->index('to_branch_id');
            $table->index('to_location_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fixed_asset_location ADD CONSTRAINT chk_fixed_asset_location_lock_version CHECK (lock_version >= 1)');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_fixed_asset_movement_mutation()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'fixed_asset_movement is append-only';
                END;
                $$ LANGUAGE plpgsql;
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER enforce_fixed_asset_movement_immutability
                BEFORE UPDATE OR DELETE ON fixed_asset_movement
                FOR EACH ROW EXECUTE FUNCTION prevent_fixed_asset_movement_mutation();
            SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER enforce_fixed_asset_movement_no_update BEFORE UPDATE ON fixed_asset_movement BEGIN SELECT RAISE(ABORT, 'fixed_asset_movement is append-only'); END;");
            DB::statement("CREATE TRIGGER enforce_fixed_asset_movement_no_delete BEFORE DELETE ON fixed_asset_movement BEGIN SELECT RAISE(ABORT, 'fixed_asset_movement is append-only'); END;");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS enforce_fixed_asset_movement_immutability ON fixed_asset_movement');
            DB::statement('DROP FUNCTION IF EXISTS prevent_fixed_asset_movement_mutation');
            DB::statement('ALTER TABLE fixed_asset_location DROP CONSTRAINT IF EXISTS chk_fixed_asset_location_lock_version');
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS enforce_fixed_asset_movement_no_update');
            DB::statement('DROP TRIGGER IF EXISTS enforce_fixed_asset_movement_no_delete');
        }

        Schema::dropIfExists('fixed_asset_movement');

        Schema::table('fixed_asset', function (Blueprint $table) {
            if (Schema::hasColumn('fixed_asset', 'fixed_asset_location_id')) {
                $table->dropConstrainedForeignId('fixed_asset_location_id');
            }

            if (Schema::hasColumn('fixed_asset', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        Schema::dropIfExists('fixed_asset_location');
    }
};
