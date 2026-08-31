<?php

namespace Tests\Feature;

use App\Application\FixedAssets\FixedAssetMovementService;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetLocation;
use App\Models\FixedAssetMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10FixedAssetMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private User $unauthorizedUser;

    private FixedAssetCategory $category;

    private Branch $sourceBranch;

    private Branch $destinationBranch;

    private FixedAssetLocation $sourceLocation;

    private FixedAssetLocation $destinationLocation;

    private FixedAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo([
            'fixedAssets.view',
            'fixedAssets.create',
            'fixedAssets.edit',
            'fixedAssets.delete',
            'fixedAssets.transfer',
            'view_financials',
        ]);

        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo(['fixedAssets.view', 'view_financials']);

        $this->unauthorizedUser = User::factory()->create();

        $this->sourceBranch = Branch::create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-FA-SRC',
            'name' => ['en' => 'Asset Source Branch', 'ar' => 'فرع أصل مصدر'],
            'is_active' => true,
        ]);

        $this->destinationBranch = Branch::create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-FA-DST',
            'name' => ['en' => 'Asset Destination Branch', 'ar' => 'فرع أصل مستلم'],
            'is_active' => true,
        ]);

        $this->sourceLocation = FixedAssetLocation::create([
            'code' => 'FA-LOC-SRC',
            'name' => ['en' => 'Source Office', 'ar' => 'مكتب المصدر'],
            'branch_id' => $this->sourceBranch->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->destinationLocation = FixedAssetLocation::create([
            'code' => 'FA-LOC-DST',
            'name' => ['en' => 'Destination Office', 'ar' => 'مكتب المستلم'],
            'branch_id' => $this->destinationBranch->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->category = FixedAssetCategory::create([
            'code' => 'FA-MOVE',
            'name' => ['en' => 'Movable Assets', 'ar' => 'أصول قابلة للنقل'],
            'useful_life_months' => 36,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $this->asset = FixedAsset::create([
            'asset_number' => 'FA-2026-MOVE-001',
            'name' => ['en' => 'Moved Laptop', 'ar' => 'حاسب منقول'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 100000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
            'branch_id' => $this->sourceBranch->id,
            'fixed_asset_location_id' => $this->sourceLocation->id,
            'lock_version' => 1,
        ]);
    }

    public function test_schema_supports_fixed_asset_operational_location_without_company_or_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasTable('fixed_asset_location'));
        $this->assertTrue(Schema::hasTable('fixed_asset_movement'));
        $this->assertTrue(Schema::hasColumn('fixed_asset', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('fixed_asset', 'fixed_asset_location_id'));

        foreach (['fixed_asset', 'fixed_asset_location', 'fixed_asset_movement'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "{$table} must not contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "{$table} must not contain tenant_id.");
        }
    }

    public function test_fixed_asset_movement_updates_current_position_and_preserves_history(): void
    {
        app(FixedAssetMovementService::class)->move($this->asset->id, [
            'movement_date' => '2026-02-01',
            'to_location_id' => $this->destinationLocation->id,
            'reason' => 'Operations relocation',
            'notes' => 'Moved after branch opening.',
        ], $this->manager->id);

        $this->asset->refresh();
        $this->assertSame((string) $this->destinationBranch->id, (string) $this->asset->branch_id);
        $this->assertSame((string) $this->destinationLocation->id, (string) $this->asset->fixed_asset_location_id);
        $this->assertSame(2, $this->asset->lock_version);

        $movement = FixedAssetMovement::query()->where('fixed_asset_id', $this->asset->id)->firstOrFail();
        $this->assertStringStartsWith('FAM-2026-', $movement->number);
        $this->assertSame((string) $this->sourceBranch->id, (string) $movement->from_branch_id);
        $this->assertSame((string) $this->destinationBranch->id, (string) $movement->to_branch_id);
        $this->assertSame((string) $this->sourceLocation->id, (string) $movement->from_location_id);
        $this->assertSame((string) $this->destinationLocation->id, (string) $movement->to_location_id);
        $this->assertSame('Operations relocation', $movement->reason);
        $this->assertSame('BR-FA-SRC', $movement->from_snapshot_json['branch']['code']);
        $this->assertSame('FA-LOC-DST', $movement->to_snapshot_json['location']['code']);
    }

    public function test_movement_rejects_cross_branch_location_mismatch_and_disposed_assets(): void
    {
        try {
            app(FixedAssetMovementService::class)->move($this->asset->id, [
                'movement_date' => '2026-02-01',
                'to_branch_id' => $this->sourceBranch->id,
                'to_location_id' => $this->destinationLocation->id,
            ], $this->manager->id);
            $this->fail('Cross-branch location mismatch was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_location_id', $exception->errors());
        }

        $this->asset->forceFill(['status' => 'disposed'])->save();

        try {
            app(FixedAssetMovementService::class)->move($this->asset->id, [
                'movement_date' => '2026-02-02',
                'to_location_id' => $this->destinationLocation->id,
            ], $this->manager->id);
            $this->fail('Disposed asset movement was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('asset', $exception->errors());
        }
    }

    public function test_fixed_asset_movement_table_is_database_append_only(): void
    {
        app(FixedAssetMovementService::class)->move($this->asset->id, [
            'movement_date' => '2026-02-01',
            'to_location_id' => $this->destinationLocation->id,
        ], $this->manager->id);

        $movement = FixedAssetMovement::query()->firstOrFail();

        $this->expectException(\Throwable::class);
        DB::table('fixed_asset_movement')->where('id', $movement->id)->update(['reason' => 'tampered']);
    }

    public function test_pages_and_routes_are_permission_protected_and_render_movement_data(): void
    {
        $route = Route::getRoutes()->getByName('fixed-assets.movements.store');
        $this->assertNotNull($route);
        $this->assertContains('can:fixedAssets.transfer', $route->gatherMiddleware());

        $this->actingAs($this->viewer)
            ->get('/fixed-asset-locations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FixedAssets/Locations')
                ->has('locations')
                ->has('branches'));

        app(FixedAssetMovementService::class)->move($this->asset->id, [
            'movement_date' => '2026-02-01',
            'to_location_id' => $this->destinationLocation->id,
        ], $this->manager->id);

        $this->actingAs($this->viewer)
            ->get("/fixed-assets/{$this->asset->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FixedAssets/Show')
                ->where('asset.branch.code', 'BR-FA-DST')
                ->where('asset.location.code', 'FA-LOC-DST')
                ->where('asset.movements.0.to_location.code', 'FA-LOC-DST'));
    }
}
