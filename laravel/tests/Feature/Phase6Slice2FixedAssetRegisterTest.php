<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6Slice2FixedAssetRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $viewUser;

    private User $manageUser;

    private User $unauthorizedUser;

    private FixedAssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->viewUser = User::factory()->create();
        $this->viewUser->givePermissionTo(['fixedAssets.view', 'view_financials']);

        $this->manageUser = User::factory()->create();
        $this->manageUser->givePermissionTo([
            'fixedAssets.view',
            'fixedAssets.create',
            'fixedAssets.edit',
            'fixedAssets.delete',
            'accounting.mappings',
            'view_financials',
        ]);

        $this->unauthorizedUser = User::factory()->create();

        $this->category = FixedAssetCategory::create([
            'id' => (string) Str::uuid(),
            'code' => 'COMPUTERS',
            'name' => ['en' => 'Computers & Laptops', 'ar' => 'أجهزة الحاسب الآلي'],
            'useful_life_months' => 36,
            'salvage_value_minor' => 1000,
            'is_active' => true,
        ]);
    }

    public function test_schema_has_no_prohibited_tenant_company_custodian_or_location_columns(): void
    {
        $prohibitedColumns = [
            'company_id',
            'branch_id',
            'tenant_id',
            'custodian_id',
            'employee_id',
            'warehouse_id',
            'location_id',
            'supplier_bill_id',
            'purchase_order_id',
        ];

        foreach ($prohibitedColumns as $col) {
            $this->assertFalse(
                Schema::hasColumn('fixed_asset_category', $col),
                "fixed_asset_category must NOT contain column [{$col}]."
            );
            $this->assertFalse(
                Schema::hasColumn('fixed_asset', $col),
                "fixed_asset must NOT contain column [{$col}]."
            );
        }
    }

    public function test_category_crud_and_delete_protection(): void
    {
        $response = $this->actingAs($this->manageUser)->post('/fixed-asset-categories', [
            'code' => 'VEHICLES',
            'name' => ['en' => 'Motor Vehicles', 'ar' => 'وسائل النقل والسيارات'],
            'useful_life_months' => 60,
            'salvage_value_minor' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('fixed_asset_category', ['code' => 'VEHICLES']);

        /** @var FixedAssetCategory $vehCat */
        $vehCat = FixedAssetCategory::where('code', 'VEHICLES')->firstOrFail();

        // Create asset linked to category
        FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-99999',
            'name' => ['en' => 'Delivery Truck', 'ar' => 'شاحنة نقل'],
            'fixed_asset_category_id' => $vehCat->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 500000,
            'salvage_value_minor' => 5000,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        // Attempt delete category with linked asset
        $deleteResp = $this->actingAs($this->manageUser)->delete("/fixed-asset-categories/{$vehCat->id}");
        $deleteResp->assertSessionHasErrors(['category']);
        $this->assertDatabaseHas('fixed_asset_category', ['id' => $vehCat->id]);
    }

    public function test_fixed_asset_crud_and_auto_numbering(): void
    {
        $response = $this->actingAs($this->manageUser)->post('/fixed-assets', [
            'name' => ['en' => 'MacBook Pro M3', 'ar' => 'ماك بوك برو'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-15',
            'in_service_date' => '2026-01-15',
            'cost_minor' => 1200000,
            'salvage_value_minor' => 5000,
            'useful_life_months' => 36,
            'opening_accumulated_depreciation_minor' => 0,
            'serial_number' => 'SN-MBP-2026-001',
            'status' => 'draft',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('fixed_asset', [
            'fixed_asset_category_id' => $this->category->id,
            'cost_minor' => 1200000,
            'serial_number' => 'SN-MBP-2026-001',
            'depreciation_method' => 'straight_line',
            'status' => 'draft',
        ]);

        /** @var FixedAsset $asset */
        $asset = FixedAsset::where('serial_number', 'SN-MBP-2026-001')->firstOrFail();
        $this->assertStringStartsWith('FA-', $asset->asset_number);

        // Update asset
        $updateResp = $this->actingAs($this->manageUser)->put("/fixed-assets/{$asset->id}", [
            'cost_minor' => 1250000,
            'status' => 'active',
        ]);

        $updateResp->assertRedirect();
        $asset->refresh();
        $this->assertEquals(1250000, $asset->cost_minor);
        $this->assertEquals('active', $asset->status);
        $this->assertEquals(1, $asset->lock_version);
    }

    public function test_register_edit_cannot_manually_mark_asset_as_future_workflow_statuses(): void
    {
        /** @var FixedAsset $asset */
        $asset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-66666',
            'name' => ['en' => 'Draft Laptop', 'ar' => 'حاسب محمول مسودة'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->manageUser)->put("/fixed-assets/{$asset->id}", [
            'status' => 'disposed',
        ]);

        $response->assertSessionHasErrors(['status']);
        $this->assertDatabaseHas('fixed_asset', [
            'id' => $asset->id,
            'status' => 'draft',
        ]);
    }

    public function test_fixed_asset_creation_requires_existing_currency_code(): void
    {
        $response = $this->actingAs($this->manageUser)->post('/fixed-assets', [
            'asset_number' => 'FA-2026-BAD01',
            'name' => ['en' => 'Invalid Currency Asset', 'ar' => 'أصل بعملة غير صحيحة'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'ZZZ',
            'acquisition_date' => '2026-01-15',
            'in_service_date' => '2026-01-15',
            'cost_minor' => 1200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 36,
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors(['currency']);
        $this->assertDatabaseMissing('fixed_asset', [
            'asset_number' => 'FA-2026-BAD01',
        ]);
    }

    public function test_delete_only_allowed_for_draft_assets(): void
    {
        /** @var FixedAsset $activeAsset */
        $activeAsset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-88888',
            'name' => ['en' => 'Active Server', 'ar' => 'خادم فعال'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 300000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 24,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manageUser)->delete("/fixed-assets/{$activeAsset->id}");
        $response->assertSessionHasErrors(['asset']);
        $this->assertDatabaseHas('fixed_asset', ['id' => $activeAsset->id]);

        /** @var FixedAsset $draftAsset */
        $draftAsset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-77777',
            'name' => ['en' => 'Draft Desk', 'ar' => 'مكتب مسودة'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 10000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        $deleteDraftResp = $this->actingAs($this->manageUser)->delete("/fixed-assets/{$draftAsset->id}");
        $deleteDraftResp->assertRedirect('/fixed-assets');
        $this->assertDatabaseMissing('fixed_asset', ['id' => $draftAsset->id]);
    }

    public function test_fixed_asset_accounting_mappings_support_all_six_keys(): void
    {
        $mappingService = app(AccountingAccountMappingService::class);

        $keys = [
            'fixed_asset_cost',
            'accumulated_depreciation',
            'depreciation_expense',
            'fixed_asset_disposal_gain',
            'fixed_asset_disposal_loss',
            'fixed_asset_clearing',
        ];

        foreach ($keys as $key) {
            $mapping = AccountingAccountMapping::where('key', $key)->first();
            $this->assertNotNull($mapping, "Mapping for key [{$key}] must be seeded.");

            $acc = $mappingService->getAccount($key);
            $this->assertInstanceOf(Account::class, $acc);
            $this->assertTrue($acc->is_active);
        }
    }

    public function test_permission_gates_prevent_unauthorized_access(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get('/fixed-assets')
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->get('/fixed-asset-categories')
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->post('/fixed-assets', [])
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->post('/fixed-asset-categories', [])
            ->assertStatus(403);
    }
}
