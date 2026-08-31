<?php

namespace Tests\Feature;

use App\Application\Accounting\TreasuryTransferService;
use App\Application\FixedAssets\FixedAssetMovementService;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Reports\BranchOperationalReportService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetLocation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10BranchOperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private Branch $northBranch;

    private Branch $southBranch;

    private Warehouse $northWarehouse;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo([
            'reports.view',
            'view_financials',
            'inventory.view',
            'fixedAssets.view',
            'fixedAssets.transfer',
            'cash.view',
            'cash.post',
            'banks.view',
            'banks.post',
        ]);

        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo('reports.view');

        $this->northBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-OPS-NORTH',
            'name' => ['en' => 'Operations North', 'ar' => 'تشغيل الشمال'],
            'is_active' => true,
        ]);

        $this->southBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-OPS-SOUTH',
            'name' => ['en' => 'Operations South', 'ar' => 'تشغيل الجنوب'],
            'is_active' => true,
        ]);

        $this->northWarehouse = Warehouse::query()->create([
            'code' => 'BR-OPS-N-WH',
            'name' => ['en' => 'North Operations Warehouse', 'ar' => 'مخزن تشغيل الشمال'],
            'branch_id' => $this->northBranch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
            'lock_version' => 1,
        ]);

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2037,
            'start_date' => '2037-01-01',
            'end_date' => '2037-12-31',
            'status' => 'open',
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
            'lock_version' => 1,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2037-01-01',
            'end_date' => '2037-01-31',
            'status' => 'open',
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
            'lock_version' => 1,
        ]);
    }

    public function test_branch_operational_report_summarizes_current_operational_coverage(): void
    {
        $this->seedStockReceipt();
        $this->seedPostedTreasuryTransfer();
        $this->seedMovedFixedAsset();

        $report = app(BranchOperationalReportService::class)->generate(
            dateFrom: '2037-01-01',
            dateTo: '2037-01-31',
        );

        $north = collect($report['rows'])->firstWhere('branch_code', 'BR-OPS-NORTH');
        $south = collect($report['rows'])->firstWhere('branch_code', 'BR-OPS-SOUTH');

        $this->assertNotNull($north);
        $this->assertNotNull($south);
        $this->assertSame(1, $north['warehouse_count']);
        $this->assertSame(1, $north['stock_balance_rows']);
        $this->assertSame(5000000, $north['stock_quantity_e6']);
        $this->assertSame(5000, $north['stock_valuation_minor']);
        $this->assertSame(-25000, $north['cash_balance_minor']);
        $this->assertSame(25000, $north['treasury_out_minor']);

        $this->assertSame(25000, $south['bank_balance_minor']);
        $this->assertSame(25000, $south['treasury_in_minor']);
        $this->assertSame(1, $south['fixed_asset_count']);
        $this->assertSame(90000, $south['fixed_asset_cost_minor']);
        $this->assertSame(1, $south['asset_movement_in_count']);
        $this->assertSame(1, $north['asset_movement_out_count']);

        $this->assertSame('enabled_via_optional_gl_branch_dimension', $report['readiness']['branch_profitability_status']);
        $this->assertContains('EGP', $report['readiness']['currency_codes']);
    }

    public function test_branch_operational_report_route_requires_financial_report_permission_and_renders_props(): void
    {
        $route = Route::getRoutes()->getByName('reports.branch-operations');
        $this->assertNotNull($route);
        $this->assertContains('can:reports.view', $route->gatherMiddleware());
        $this->assertContains('can:view_financials', $route->gatherMiddleware());

        $this->actingAs($this->viewer)
            ->get('/reports/branch-operations')
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get('/reports/branch-operations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/BranchOperations')
                ->has('reportData.rows')
                ->has('reportData.summary')
                ->has('reportData.readiness')
                ->has('branches')
                ->where('reportData.readiness.branch_profitability_status', 'enabled_via_optional_gl_branch_dimension'));
    }

    public function test_branch_operational_report_adds_no_tenant_or_company_scope(): void
    {
        foreach (['warehouse', 'stock_balance', 'cash_account', 'bank_account', 'fixed_asset', 'treasury_transfer'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "{$table} must not contain tenant_id.");
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "{$table} must not contain company_id.");
        }

        $serviceSource = file_get_contents(app_path('Application/Reports/BranchOperationalReportService.php'));
        $pageSource = file_get_contents(resource_path('js/Pages/Reports/BranchOperations.tsx'));

        $this->assertStringNotContainsString('currentCompany', $serviceSource.$pageSource);
        $this->assertStringNotContainsString('currentBranch', $serviceSource.$pageSource);
        $this->assertStringNotContainsString('Spatie Teams', $serviceSource.$pageSource);
    }

    private function seedStockReceipt(): void
    {
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $category = ProductCategory::query()->firstOrFail();

        $product = Product::query()->create([
            'code' => 'BR-OPS-STOCK',
            'name' => ['en' => 'Branch Operations Stock', 'ar' => 'مخزون تشغيل الفروع'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'product_category_id' => $category->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
            'lock_version' => 1,
        ]);

        app(MovingWeightedAverageInventoryService::class)->recordReceipt(
            sourceType: 'branch_ops_fixture',
            sourceId: (string) Str::uuid(),
            sourceLineId: (string) Str::uuid(),
            movementDate: '2037-01-05',
            productId: $product->id,
            unitOfMeasureId: $uom->id,
            currency: 'EGP',
            quantityE6: 5000000,
            unitCostMinor: 1000,
            fiscalYearId: $this->fiscalYear->id,
            financialPeriodId: $this->period->id,
            actorId: $this->manager->id,
            warehouseId: $this->northWarehouse->id,
        );
    }

    private function seedPostedTreasuryTransfer(): void
    {
        $cashGl = $this->assetAccount('BR-OPS-CASH-GL', 'Branch Operations Cash GL');
        $bankGl = $this->assetAccount('BR-OPS-BANK-GL', 'Branch Operations Bank GL');

        $cash = CashAccount::query()->create([
            'code' => 'BR-OPS-CASH',
            'name' => ['en' => 'Branch Operations Cash', 'ar' => 'خزينة تشغيل الفروع'],
            'branch_id' => $this->northBranch->id,
            'gl_account_id' => $cashGl->id,
            'currency' => 'EGP',
            'is_active' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
            'lock_version' => 1,
        ]);

        $bank = BankAccount::query()->create([
            'code' => 'BR-OPS-BANK',
            'name' => ['en' => 'Branch Operations Bank', 'ar' => 'بنك تشغيل الفروع'],
            'bank_name' => ['en' => 'Operations Bank', 'ar' => 'بنك التشغيل'],
            'branch_id' => $this->southBranch->id,
            'gl_account_id' => $bankGl->id,
            'currency' => 'EGP',
            'is_active' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
            'lock_version' => 1,
        ]);

        $transfer = app(TreasuryTransferService::class)->create([
            'transfer_date' => '2037-01-10',
            'source_type' => 'cash',
            'source_cash_account_id' => $cash->id,
            'destination_type' => 'bank',
            'destination_bank_account_id' => $bank->id,
            'currency' => 'EGP',
            'amount_minor' => 25000,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
        ], $this->manager->id);

        app(TreasuryTransferService::class)->post($transfer->id, $this->manager->id);
    }

    private function seedMovedFixedAsset(): void
    {
        $category = FixedAssetCategory::query()->create([
            'code' => 'BR-OPS-FA-CAT',
            'name' => ['en' => 'Branch Operations Assets', 'ar' => 'أصول تشغيل الفروع'],
            'useful_life_months' => 36,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $sourceLocation = FixedAssetLocation::query()->create([
            'code' => 'BR-OPS-FA-N',
            'name' => ['en' => 'North Asset Room', 'ar' => 'غرفة أصول الشمال'],
            'branch_id' => $this->northBranch->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $destinationLocation = FixedAssetLocation::query()->create([
            'code' => 'BR-OPS-FA-S',
            'name' => ['en' => 'South Asset Room', 'ar' => 'غرفة أصول الجنوب'],
            'branch_id' => $this->southBranch->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $asset = FixedAsset::query()->create([
            'asset_number' => 'FA-BR-OPS-001',
            'name' => ['en' => 'Branch Operations Laptop', 'ar' => 'حاسب تشغيل الفروع'],
            'fixed_asset_category_id' => $category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2037-01-01',
            'in_service_date' => '2037-01-01',
            'cost_minor' => 90000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
            'branch_id' => $this->northBranch->id,
            'fixed_asset_location_id' => $sourceLocation->id,
            'lock_version' => 1,
        ]);

        app(FixedAssetMovementService::class)->move($asset->id, [
            'movement_date' => '2037-01-12',
            'to_location_id' => $destinationLocation->id,
            'reason' => 'Operational relocation',
        ], $this->manager->id);
    }

    private function assetAccount(string $code, string $name): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);
    }
}
