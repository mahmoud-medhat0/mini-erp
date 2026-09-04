<?php

namespace Tests\Feature;

use App\Application\Reports\FixedAssetReportService;
use App\Models\FinancialPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\FixedAssetDisposal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6Slice7FixedAssetReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $reportViewer;

    private User $fixedAssetExportUser;

    private User $reportsExportUser;

    private User $fixedAssetViewOnlyUser;

    private User $unauthorizedUser;

    private FixedAsset $asset;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->reportViewer = User::factory()->create();
        $this->reportViewer->givePermissionTo(['reports.view', 'view_financials']);

        $this->fixedAssetExportUser = User::factory()->create();
        $this->fixedAssetExportUser->givePermissionTo(['reports.view', 'view_financials', 'fixedAssets.export']);

        $this->reportsExportUser = User::factory()->create();
        $this->reportsExportUser->givePermissionTo(['reports.view', 'view_financials', 'reports.export']);

        $this->fixedAssetViewOnlyUser = User::factory()->create();
        $this->fixedAssetViewOnlyUser->givePermissionTo(['reports.view', 'view_financials', 'fixedAssets.view']);

        $this->unauthorizedUser = User::factory()->create();

        $this->period = FinancialPeriod::query()->orderBy('start_date')->firstOrFail();

        $category = FixedAssetCategory::create([
            'code' => 'REPORT-ASSETS',
            'name' => ['en' => 'Report Assets', 'ar' => 'أصول التقارير'],
            'useful_life_months' => 12,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $this->asset = FixedAsset::create([
            'asset_number' => 'FA-2026-REP-001',
            'name' => ['en' => 'Report Laptop', 'ar' => 'حاسب تقارير'],
            'fixed_asset_category_id' => $category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 1200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 100000,
            'status' => 'active',
        ]);

        $run = FixedAssetDepreciationRun::create([
            'number' => 'FADR-REP-001',
            'financial_period_id' => $this->period->id,
            'run_date' => $this->period->end_date,
            'total_depreciation_minor' => 100000,
            'asset_count' => 1,
            'status' => 'posted',
        ]);

        FixedAssetDepreciationSchedule::create([
            'fixed_asset_id' => $this->asset->id,
            'period_number' => 1,
            'financial_period_id' => $this->period->id,
            'period_start_date' => $this->period->start_date,
            'period_end_date' => $this->period->end_date,
            'depreciation_minor' => 100000,
            'accumulated_depreciation_minor' => 200000,
            'net_book_value_minor' => 1000000,
            'status' => 'posted',
            'depreciation_run_id' => $run->id,
        ]);

        FixedAssetDisposal::create([
            'number' => 'FAD-REP-001',
            'fixed_asset_id' => $this->asset->id,
            'disposal_date' => $this->period->end_date,
            'financial_period_id' => $this->period->id,
            'disposal_type' => 'sale',
            'proceeds_minor' => 1100000,
            'net_book_value_minor' => 1000000,
            'gain_minor' => 100000,
            'loss_minor' => 0,
            'status' => 'posted',
        ]);
    }

    public function test_fixed_asset_report_pages_require_reports_view_and_financial_access(): void
    {
        $routes = [
            '/reports/fixed-asset-register?search=FA-2026-REP-001',
            '/reports/fixed-asset-net-book-values?search=FA-2026-REP-001',
            '/reports/fixed-asset-depreciation?search=FA-2026-REP-001',
            '/reports/fixed-asset-depreciation-runs?status=posted',
            '/reports/fixed-asset-disposals?search=FAD-REP-001',
        ];

        foreach ($routes as $route) {
            $this->actingAs($this->unauthorizedUser)->get($route)->assertStatus(403);
            $this->actingAs($this->reportViewer)->get($route)->assertOk();
        }
    }

    public function test_fixed_asset_report_pages_return_service_calculated_inertia_props(): void
    {
        $this->actingAs($this->reportViewer)
            ->get('/reports/fixed-asset-register?search=FA-2026-REP-001')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/FixedAssetRegisterReport')
                ->where('assets.data.0.asset_number', 'FA-2026-REP-001')
                ->where('assets.data.0.cost_minor', 1200000)
                ->where('assets.data.0.posted_accumulated_depreciation_minor', 100000)
                ->where('assets.data.0.total_accumulated_depreciation_minor', 200000)
                ->where('assets.data.0.net_book_value_minor', 1000000));

        $this->actingAs($this->reportViewer)
            ->get('/reports/fixed-asset-net-book-values?search=FA-2026-REP-001')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/FixedAssetNetBookValueReport')
                ->where('assets.data.0.net_book_value_minor', 1000000));

        $this->actingAs($this->reportViewer)
            ->get('/reports/fixed-asset-depreciation?search=FA-2026-REP-001')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/FixedAssetDepreciationReport')
                ->where('schedules.data.0.depreciation_minor', 100000)
                ->where('schedules.data.0.asset.asset_number', 'FA-2026-REP-001'));

        $this->actingAs($this->reportViewer)
            ->get('/reports/fixed-asset-depreciation-runs?status=posted')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/FixedAssetDepreciationRunReport')
                ->where('runs.data.0.number', 'FADR-REP-001')
                ->where('runs.data.0.total_depreciation_minor', 100000));

        $this->actingAs($this->reportViewer)
            ->get('/reports/fixed-asset-disposals?search=FAD-REP-001')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/FixedAssetDisposalReport')
                ->where('disposals.data.0.number', 'FAD-REP-001')
                ->where('disposals.data.0.gain_minor', 100000));
    }

    public function test_fixed_asset_export_requires_specific_export_permission(): void
    {
        $this->actingAs($this->fixedAssetViewOnlyUser)
            ->get('/reports/fixed-asset-register/export?search=FA-2026-REP-001')
            ->assertStatus(403);

        $this->actingAs($this->fixedAssetExportUser)
            ->get('/reports/fixed-asset-register/export?search=FA-2026-REP-001')
            ->assertOk();

        $this->actingAs($this->reportsExportUser)
            ->get('/reports/fixed-asset-register/export?search=FA-2026-REP-001')
            ->assertOk();
    }

    public function test_csv_exports_match_service_values_and_keep_minor_units(): void
    {
        $serviceRows = app(FixedAssetReportService::class)->allRegisterRows(['search' => 'FA-2026-REP-001']);

        $this->assertCount(1, $serviceRows);
        $this->assertSame(1200000, $serviceRows->first()['cost_minor']);
        $this->assertSame(1000000, $serviceRows->first()['net_book_value_minor']);

        $content = $this->actingAs($this->reportsExportUser)
            ->get('/reports/fixed-asset-register/export?search=FA-2026-REP-001')
            ->streamedContent();

        $this->assertStringContainsString('Cost Minor', $content);
        $this->assertStringContainsString('1200000', $content);
        $this->assertStringContainsString('1000000', $content);
        $this->assertStringNotContainsString('12000.00', $content);
        $this->assertStringNotContainsString('10000.00', $content);

        $this->actingAs($this->reportsExportUser)
            ->get('/reports/fixed-asset-net-book-values/export?search=FA-2026-REP-001')
            ->assertOk();

        $this->actingAs($this->reportsExportUser)
            ->get('/reports/fixed-asset-depreciation/export?search=FA-2026-REP-001')
            ->assertOk();

        $this->actingAs($this->reportsExportUser)
            ->get('/reports/fixed-asset-depreciation-runs/export?status=posted')
            ->assertOk();

        $this->actingAs($this->reportsExportUser)
            ->get('/reports/fixed-asset-disposals/export?search=FAD-REP-001')
            ->assertOk();
    }

    public function test_slice7_source_files_do_not_reintroduce_known_report_risks(): void
    {
        $fixedAssetSpecificFiles = [
            app_path('Application/Reports/FixedAssetReportService.php'),
            app_path('Http/Controllers/Reports/FixedAssetReportController.php'),
            resource_path('js/Pages/Reports/FixedAssetRegisterReport.tsx'),
            resource_path('js/Pages/Reports/FixedAssetNetBookValueReport.tsx'),
            resource_path('js/Pages/Reports/FixedAssetDepreciationReport.tsx'),
            resource_path('js/Pages/Reports/FixedAssetDepreciationRunReport.tsx'),
            resource_path('js/Pages/Reports/FixedAssetDisposalReport.tsx'),
        ];

        foreach ($fixedAssetSpecificFiles as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('/ 100', $contents);
            $this->assertStringNotContainsString('/100', $contents);
            $this->assertStringNotContainsString('fixedAssets.view', $contents);
            $this->assertDoesNotMatchRegularExpression('/locale\s*===|[\x{0600}-\x{06FF}]/u', $contents);
            $this->assertDoesNotMatchRegularExpression('/company_id|branch_id|tenant_id|currentCompany|currentBranch|custodian|employee|warehouse|location/', $contents);
        }

        // Index.tsx is a multi-domain reports hub linking to every report in the
        // app (including legitimate warehouse/product statements), so only the
        // fixed-asset-specific risks apply here, not the cross-domain keyword scan.
        $hubContents = file_get_contents(resource_path('js/Pages/Reports/Index.tsx'));
        $this->assertStringNotContainsString('/ 100', $hubContents);
        $this->assertStringNotContainsString('/100', $hubContents);
        $this->assertStringNotContainsString('fixedAssets.view', $hubContents);
        $this->assertDoesNotMatchRegularExpression('/locale\s*===|[\x{0600}-\x{06FF}]/u', $hubContents);

        $this->assertStringNotContainsString(
            'created_at',
            file_get_contents(app_path('Application/Reports/FixedAssetReportService.php'))
        );
    }

    public function test_schema_does_not_add_unsupported_company_tenant_or_custodian_scope_columns_for_reports(): void
    {
        foreach (['company_id', 'tenant_id', 'custodian_id', 'employee_id', 'warehouse_id', 'location_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('fixed_asset', $column));
            $this->assertFalse(Schema::hasColumn('fixed_asset_depreciation_schedule', $column));
            $this->assertFalse(Schema::hasColumn('fixed_asset_depreciation_run', $column));
            $this->assertFalse(Schema::hasColumn('fixed_asset_disposal', $column));
        }

        $this->assertTrue(Schema::hasColumn('fixed_asset', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('fixed_asset', 'fixed_asset_location_id'));
    }
}
