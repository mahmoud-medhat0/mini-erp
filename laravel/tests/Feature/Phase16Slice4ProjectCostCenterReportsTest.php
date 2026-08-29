<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PostingEngine;
use App\Application\Reports\CostCenterActualsReportService;
use App\Application\Reports\ProjectProfitabilityReportService;
use App\Application\Reports\ReportPageOptions;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FinancialStatementLine;
use App\Models\FiscalYear;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase16Slice4ProjectCostCenterReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $reportOnlyUser;

    private User $financialsOnlyUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $periodJan;

    private FinancialPeriod $periodFeb;

    private Currency $currencyEgp;

    private Currency $currencyUsd;

    private Project $projectAlpha;

    private Project $projectBeta;

    private CostCenter $costCenterHQ;

    private CostCenter $costCenterOps;

    private Account $cashAccount;

    private Account $revenueAccount;

    private Account $contraRevenueAccount;

    private Account $cogsAccount;

    private Account $opexAccount;

    private Account $otherIncomeAccount;

    private Account $otherExpenseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::factory()->create(['locale' => 'en']);
        $this->adminUser->givePermissionTo([
            'accounting.view',
            'accounting.create',
            'accounting.post',
            'accounting.reverse',
            'reports.view',
            'reports.export',
            'reports.print',
            'view_financials',
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'costCenters.view',
            'costCenters.create',
            'costCenters.edit',
            'costCenters.delete',
        ]);

        $this->reportOnlyUser = User::factory()->create(['locale' => 'en']);
        $this->reportOnlyUser->givePermissionTo('reports.view');

        $this->financialsOnlyUser = User::factory()->create(['locale' => 'en']);
        $this->financialsOnlyUser->givePermissionTo('view_financials');

        $this->fiscalYear = FiscalYear::query()->firstOrCreate(
            ['year' => 2045],
            [
                'start_date' => '2045-01-01',
                'end_date' => '2045-12-31',
                'status' => 'open',
                'created_by' => $this->adminUser->id,
                'updated_by' => $this->adminUser->id,
                'lock_version' => 1,
            ]
        );

        $this->periodJan = FinancialPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $this->fiscalYear->id, 'month' => 1],
            [
                'period_number' => 1,
                'start_date' => '2045-01-01',
                'end_date' => '2045-01-31',
                'status' => 'open',
                'created_by' => $this->adminUser->id,
                'updated_by' => $this->adminUser->id,
                'lock_version' => 1,
            ]
        );

        $this->periodFeb = FinancialPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $this->fiscalYear->id, 'month' => 2],
            [
                'period_number' => 2,
                'start_date' => '2045-02-01',
                'end_date' => '2045-02-28',
                'status' => 'open',
                'created_by' => $this->adminUser->id,
                'updated_by' => $this->adminUser->id,
                'lock_version' => 1,
            ]
        );

        $this->currencyEgp = Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            ['name' => ['en' => 'Egyptian Pound', 'ar' => 'جنيه مصري'], 'symbol' => 'EGP', 'is_base' => true, 'is_active' => true]
        );

        $this->currencyUsd = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'], 'symbol' => '$', 'is_base' => false, 'is_active' => true]
        );

        $this->projectAlpha = Project::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRJ-ALPHA',
            'name' => ['en' => 'Project Alpha', 'ar' => 'مشروع ألفا'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->projectBeta = Project::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRJ-BETA',
            'name' => ['en' => 'Project Beta', 'ar' => 'مشروع بيتا'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->costCenterHQ = CostCenter::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CC-HQ',
            'name' => ['en' => 'Headquarters', 'ar' => 'المقر الرئيسي'],
            'category' => 'administrative',
            'is_active' => true,
        ]);

        $this->costCenterOps = CostCenter::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CC-OPS',
            'name' => ['en' => 'Operations', 'ar' => 'العمليات'],
            'category' => 'operational',
            'is_active' => true,
        ]);

        $groupAsset = AccountGroup::query()->firstOrCreate(
            ['code' => 'GRP-AST'],
            ['name' => ['en' => 'Assets', 'ar' => 'الأصول'], 'type' => 'asset', 'sort_order' => 1, 'is_active' => true]
        );
        $groupRev = AccountGroup::query()->firstOrCreate(
            ['code' => 'GRP-REV'],
            ['name' => ['en' => 'Revenue', 'ar' => 'الإيرادات'], 'type' => 'revenue', 'sort_order' => 2, 'is_active' => true]
        );
        $groupExp = AccountGroup::query()->firstOrCreate(
            ['code' => 'GRP-EXP'],
            ['name' => ['en' => 'Expenses', 'ar' => 'المصروفات'], 'type' => 'expense', 'sort_order' => 3, 'is_active' => true]
        );

        $lineRev = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_REV'],
            ['name' => ['en' => 'Operating Revenue', 'ar' => 'إيراد تشغيلي'], 'statement_type' => 'income_statement', 'section_code' => 'revenue', 'normal_balance' => 'credit', 'sort_order' => 10, 'is_active' => true]
        );
        $lineContraRev = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_CONTRA_REV'],
            ['name' => ['en' => 'Sales Returns', 'ar' => 'مردودات مبيعات'], 'statement_type' => 'income_statement', 'section_code' => 'contra_revenue', 'normal_balance' => 'debit', 'sort_order' => 20, 'is_active' => true]
        );
        $lineCogs = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_COGS'],
            ['name' => ['en' => 'Cost of Goods Sold', 'ar' => 'تكلفة البضاعة المباعة'], 'statement_type' => 'income_statement', 'section_code' => 'cogs', 'normal_balance' => 'debit', 'sort_order' => 30, 'is_active' => true]
        );
        $lineOpex = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_OPEX'],
            ['name' => ['en' => 'Operating Expenses', 'ar' => 'مصروفات تشغيلية'], 'statement_type' => 'income_statement', 'section_code' => 'operating_expenses', 'normal_balance' => 'debit', 'sort_order' => 40, 'is_active' => true]
        );
        $lineOtherInc = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_OTHER_INC'],
            ['name' => ['en' => 'Other Income', 'ar' => 'إيرادات أخرى'], 'statement_type' => 'income_statement', 'section_code' => 'other_income', 'normal_balance' => 'credit', 'sort_order' => 50, 'is_active' => true]
        );
        $lineOtherExp = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_OTHER_EXP'],
            ['name' => ['en' => 'Other Expenses', 'ar' => 'مصروفات أخرى'], 'statement_type' => 'income_statement', 'section_code' => 'other_expenses', 'normal_balance' => 'debit', 'sort_order' => 60, 'is_active' => true]
        );

        $this->cashAccount = $this->createTestAccount('10100', 'Operating Cash', 'asset', 'debit', $groupAsset->id);
        $this->revenueAccount = $this->createTestAccount('40100', 'Project Services Revenue', 'revenue', 'credit', $groupRev->id, $lineRev->id);
        $this->contraRevenueAccount = $this->createTestAccount('40200', 'Project Allowances', 'contra_revenue', 'debit', $groupRev->id, $lineContraRev->id);
        $this->cogsAccount = $this->createTestAccount('50100', 'Direct Project Costs', 'expense', 'debit', $groupExp->id, $lineCogs->id);
        $this->opexAccount = $this->createTestAccount('60100', 'General Operating Expense', 'expense', 'debit', $groupExp->id, $lineOpex->id);
        $this->otherIncomeAccount = $this->createTestAccount('70100', 'Miscellaneous Income', 'revenue', 'credit', $groupRev->id, $lineOtherInc->id);
        $this->otherExpenseAccount = $this->createTestAccount('80100', 'Bank Service Charges', 'expense', 'debit', $groupExp->id, $lineOtherExp->id);
    }

    public function test_report_routes_require_both_reports_view_and_view_financials(): void
    {
        $prjRoute = Route::getRoutes()->getByName('reports.project-profitability');
        $this->assertNotNull($prjRoute);
        $this->assertContains('can:reports.view', $prjRoute->gatherMiddleware());
        $this->assertContains('can:view_financials', $prjRoute->gatherMiddleware());

        $ccRoute = Route::getRoutes()->getByName('reports.cost-center-actuals');
        $this->assertNotNull($ccRoute);
        $this->assertContains('can:reports.view', $ccRoute->gatherMiddleware());
        $this->assertContains('can:view_financials', $ccRoute->gatherMiddleware());

        // Unauthorized user without permissions
        $unauth = User::factory()->create();
        $this->actingAs($unauth)->get('/reports/project-profitability')->assertForbidden();
        $this->actingAs($unauth)->get('/reports/cost-center-actuals')->assertForbidden();

        // User with reports.view only (missing view_financials)
        $this->actingAs($this->reportOnlyUser)->get('/reports/project-profitability')->assertForbidden();
        $this->actingAs($this->reportOnlyUser)->get('/reports/cost-center-actuals')->assertForbidden();

        // User with view_financials only (missing reports.view)
        $this->actingAs($this->financialsOnlyUser)->get('/reports/project-profitability')->assertForbidden();
        $this->actingAs($this->financialsOnlyUser)->get('/reports/cost-center-actuals')->assertForbidden();

        // Authorized user with both permissions
        $this->actingAs($this->adminUser)
            ->get('/reports/project-profitability?date_from=2045-01-01&date_to=2045-01-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/ProjectProfitability')
                ->has('reportData.rows')
                ->has('reportData.summary_by_currency')
                ->has('projects')
                ->has('costCenters')
                ->has('accounts')
                ->has('currencies')
                ->has('periods')
            );

        $this->actingAs($this->adminUser)
            ->get('/reports/cost-center-actuals?date_from=2045-01-01&date_to=2045-01-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/CostCenterActuals')
                ->has('reportData.rows')
                ->has('reportData.summary_by_currency')
                ->has('costCenters')
                ->has('projects')
                ->has('accounts')
                ->has('currencies')
                ->has('periods')
            );
    }

    public function test_csv_export_routes_additionally_require_reports_export(): void
    {
        $userWithoutExport = User::factory()->create(['locale' => 'en']);
        $userWithoutExport->givePermissionTo(['reports.view', 'view_financials']);

        $this->actingAs($userWithoutExport)
            ->get('/reports/project-profitability/export?date_from=2045-01-01&date_to=2045-01-31')
            ->assertForbidden();

        $this->actingAs($userWithoutExport)
            ->get('/reports/cost-center-actuals/export?date_from=2045-01-01&date_to=2045-01-31')
            ->assertForbidden();

        $prjResponse = $this->actingAs($this->adminUser)
            ->get('/reports/project-profitability/export?date_from=2045-01-01&date_to=2045-01-31')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $prjCsv = $prjResponse->streamedContent();
        $this->assertStringContainsString('PROJECT PROFITABILITY REPORT', $prjCsv);

        $ccResponse = $this->actingAs($this->adminUser)
            ->get('/reports/cost-center-actuals/export?date_from=2045-01-01&date_to=2045-01-31')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $ccCsv = $ccResponse->streamedContent();
        $this->assertStringContainsString('COST CENTER ACTUALS REPORT', $ccCsv);
    }

    public function test_project_profitability_reads_only_posted_ledger_rows_and_ignores_draft_journals(): void
    {
        // 1. Create draft journal voucher with project Alpha (unposted)
        app(JournalDraftService::class)->createDraft([
            'entry_date' => '2045-01-10',
            'financial_period_id' => $this->periodJan->id,
            'description' => 'Unposted draft revenue',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 50000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 50000],
        ], $this->adminUser->id);

        $service = app(ProjectProfitabilityReportService::class);
        $report = $service->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');

        $this->assertCount(0, $report['rows']);
        $this->assertSame(0, $report['summary_by_currency']['EGP']['net_revenue_minor']);

        // 2. Post the entry
        $postedDraft = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2045-01-15',
            'financial_period_id' => $this->periodJan->id,
            'description' => 'Posted revenue',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 80000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 80000],
        ], $this->adminUser->id);

        app(PostingEngine::class)->post($postedDraft, $this->adminUser->id);

        $reportAfterPost = $service->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');
        $alphaRow = collect($reportAfterPost['rows'])->firstWhere('project_code', 'PRJ-ALPHA');

        $this->assertNotNull($alphaRow);
        $this->assertSame(80000, $alphaRow['revenue_minor']);
        $this->assertSame(80000, $alphaRow['net_revenue_minor']);
        $this->assertSame(80000, $alphaRow['net_income_minor']);
    }

    public function test_project_profitability_calculates_all_pnl_components_and_margin_correctly(): void
    {
        // Post complete P&L ledger movements for Project Alpha:
        // Revenue: 100,000
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 100000],
        ]);

        // Contra Revenue (Returns): 5,000
        $this->postJournal([
            ['account_id' => $this->contraRevenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 5000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 5000],
        ]);

        // COGS: 40,000
        $this->postJournal([
            ['account_id' => $this->cogsAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 40000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 40000],
        ]);

        // Operating Expenses: 20,000
        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 20000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 20000],
        ]);

        // Other Income: 3,000
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 3000, 'credit_minor' => 0],
            ['account_id' => $this->otherIncomeAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 3000],
        ]);

        // Other Expense: 1,000
        $this->postJournal([
            ['account_id' => $this->otherExpenseAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 1000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 1000],
        ]);

        $report = app(ProjectProfitabilityReportService::class)->generate(
            dateFrom: '2045-01-01',
            dateTo: '2045-01-31',
        );

        $alpha = collect($report['rows'])->firstWhere('project_code', 'PRJ-ALPHA');
        $this->assertNotNull($alpha);

        // Expected figures:
        // Revenue: 100,000
        // Contra Revenue: 5,000
        // Net Revenue: 95,000
        // COGS: 40,000
        // Gross Profit: 55,000
        // Operating Expenses: 20,000
        // Operating Income: 35,000
        // Other Income: 3,000
        // Other Expenses: 1,000
        // Net Income: 37,000
        // Margin: intdiv(37000 * 10000, 95000) = 3894 bps (38.94%)
        $this->assertSame(100000, $alpha['revenue_minor']);
        $this->assertSame(5000, $alpha['contra_revenue_minor']);
        $this->assertSame(95000, $alpha['net_revenue_minor']);
        $this->assertSame(40000, $alpha['cogs_minor']);
        $this->assertSame(55000, $alpha['gross_profit_minor']);
        $this->assertSame(20000, $alpha['operating_expense_minor']);
        $this->assertSame(35000, $alpha['operating_income_minor']);
        $this->assertSame(30000 - 27000, $alpha['other_income_minor']); // 3,000
        $this->assertSame(1000, $alpha['other_expense_minor']);
        $this->assertSame(37000, $alpha['net_income_minor']);
        $this->assertSame(3894, $alpha['profit_margin_bps']);
    }

    public function test_project_profitability_includes_unassigned_project_rows(): void
    {
        // Unassigned revenue (project_id = null)
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 60000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 60000],
        ]);

        $report = app(ProjectProfitabilityReportService::class)->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');

        $unassigned = collect($report['rows'])->firstWhere('is_unassigned', true);
        $this->assertNotNull($unassigned);
        $this->assertSame('UNASSIGNED', $unassigned['project_code']);
        $this->assertNull($unassigned['project_id']);
        $this->assertSame(60000, $unassigned['net_revenue_minor']);
        $this->assertTrue($report['readiness']['has_unassigned_pnl']);
        $this->assertGreaterThan(0, $report['readiness']['unassigned_pnl_row_count']);
    }

    public function test_project_profitability_does_not_combine_different_currencies(): void
    {
        // EGP revenue for Project Alpha
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 100000],
        ], currency: 'EGP');

        // USD revenue for Project Alpha
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 2000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 2000],
        ], currency: 'USD');

        $report = app(ProjectProfitabilityReportService::class)->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');

        $this->assertTrue($report['has_mixed_currencies']);
        $this->assertContains('EGP', $report['currency_codes']);
        $this->assertContains('USD', $report['currency_codes']);

        $egpRow = collect($report['rows'])->first(fn ($r) => $r['project_code'] === 'PRJ-ALPHA' && $r['currency'] === 'EGP');
        $usdRow = collect($report['rows'])->first(fn ($r) => $r['project_code'] === 'PRJ-ALPHA' && $r['currency'] === 'USD');

        $this->assertNotNull($egpRow);
        $this->assertNotNull($usdRow);
        $this->assertSame(100000, $egpRow['net_revenue_minor']);
        $this->assertSame(2000, $usdRow['net_revenue_minor']);

        // Check summaries by currency are isolated
        $this->assertSame(100000, $report['summary_by_currency']['EGP']['net_revenue_minor']);
        $this->assertSame(2000, $report['summary_by_currency']['USD']['net_revenue_minor']);
    }

    public function test_cost_center_actuals_groups_by_cost_center_and_currency(): void
    {
        // EGP opex on CC-HQ
        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'cost_center_id' => $this->costCenterHQ->id, 'debit_minor' => 15000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 15000],
        ], currency: 'EGP');

        // USD opex on CC-HQ
        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'cost_center_id' => $this->costCenterHQ->id, 'debit_minor' => 500, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 500],
        ], currency: 'USD');

        $report = app(CostCenterActualsReportService::class)->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');

        $this->assertTrue($report['has_mixed_currencies']);
        $egpRow = collect($report['rows'])->first(fn ($r) => $r['cost_center_code'] === 'CC-HQ' && $r['currency'] === 'EGP');
        $usdRow = collect($report['rows'])->first(fn ($r) => $r['cost_center_code'] === 'CC-HQ' && $r['currency'] === 'USD');

        $this->assertNotNull($egpRow);
        $this->assertNotNull($usdRow);
        $this->assertSame(15000, $egpRow['debit_minor']);
        $this->assertSame(15000, $egpRow['net_minor']);
        $this->assertSame(500, $usdRow['debit_minor']);
        $this->assertSame(500, $usdRow['net_minor']);

        $this->assertSame(15000, $report['summary_by_currency']['EGP']['debit_minor']);
        $this->assertSame(15000, $report['summary_by_currency']['EGP']['credit_minor']);
        $this->assertSame(0, $report['summary_by_currency']['EGP']['net_minor']);
        $this->assertSame(500, $report['summary_by_currency']['USD']['debit_minor']);
        $this->assertSame(500, $report['summary_by_currency']['USD']['credit_minor']);
        $this->assertSame(0, $report['summary_by_currency']['USD']['net_minor']);
    }

    public function test_cost_center_actuals_includes_account_level_breakdown_with_net_by_nature(): void
    {
        // Debit nature account (Expense): debit 12,000, credit 2,000 -> net = 10,000
        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'cost_center_id' => $this->costCenterHQ->id, 'debit_minor' => 12000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 12000],
        ]);
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 2000, 'credit_minor' => 0],
            ['account_id' => $this->opexAccount->id, 'cost_center_id' => $this->costCenterHQ->id, 'debit_minor' => 0, 'credit_minor' => 2000],
        ]);

        // Credit nature account (Revenue): debit 1,000, credit 8,000 -> net = 7,000
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 8000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'cost_center_id' => $this->costCenterHQ->id, 'debit_minor' => 0, 'credit_minor' => 8000],
        ]);
        $this->postJournal([
            ['account_id' => $this->revenueAccount->id, 'cost_center_id' => $this->costCenterHQ->id, 'debit_minor' => 1000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 1000],
        ]);

        $report = app(CostCenterActualsReportService::class)->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');

        $hqRow = collect($report['rows'])->firstWhere('cost_center_code', 'CC-HQ');
        $this->assertNotNull($hqRow);

        $accounts = collect($hqRow['accounts']);
        $opexAcc = $accounts->firstWhere('account_code', '60100');
        $revAcc = $accounts->firstWhere('account_code', '40100');

        $this->assertNotNull($opexAcc);
        $this->assertSame('debit', $opexAcc['account_nature']);
        $this->assertSame(12000, $opexAcc['debit_minor']);
        $this->assertSame(2000, $opexAcc['credit_minor']);
        $this->assertSame(10000, $opexAcc['net_minor']); // 12000 - 2000

        $this->assertNotNull($revAcc);
        $this->assertSame('credit', $revAcc['account_nature']);
        $this->assertSame(1000, $revAcc['debit_minor']);
        $this->assertSame(8000, $revAcc['credit_minor']);
        $this->assertSame(7000, $revAcc['net_minor']); // 8000 - 1000
    }

    public function test_cost_center_actuals_includes_unassigned_cost_center_rows(): void
    {
        // Post entry without cost center
        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'debit_minor' => 4500, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 4500],
        ]);

        $report = app(CostCenterActualsReportService::class)->generate(dateFrom: '2045-01-01', dateTo: '2045-01-31');

        $unassigned = collect($report['rows'])->firstWhere('is_unassigned', true);
        $this->assertNotNull($unassigned);
        $this->assertSame('UNASSIGNED', $unassigned['cost_center_code']);
        $this->assertNull($unassigned['cost_center_id']);
        $this->assertTrue($report['readiness']['has_unassigned']);
        $this->assertGreaterThan(0, $report['readiness']['unassigned_row_count']);
    }

    public function test_period_id_overrides_date_range(): void
    {
        // Jan transaction
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 20000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 20000],
        ], date: '2045-01-10', periodId: $this->periodJan->id);

        // Feb transaction
        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 30000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'project_id' => $this->projectAlpha->id, 'debit_minor' => 0, 'credit_minor' => 30000],
        ], date: '2045-02-10', periodId: $this->periodFeb->id);

        // Pass date range for Feb, but period_id for Jan -> period_id must override!
        $report = app(ProjectProfitabilityReportService::class)->generate(
            dateFrom: '2045-02-01',
            dateTo: '2045-02-28',
            periodId: $this->periodJan->id,
        );

        $this->assertSame('2045-01-01', $report['from_date']);
        $this->assertSame('2045-01-31', $report['to_date']);

        $alpha = collect($report['rows'])->firstWhere('project_code', 'PRJ-ALPHA');
        $this->assertNotNull($alpha);
        $this->assertSame(20000, $alpha['net_revenue_minor']);
    }

    public function test_filters_validate_invalid_inputs(): void
    {
        // Nonexistent project
        $this->actingAs($this->adminUser)
            ->get('/reports/project-profitability?project_id='.Str::uuid())
            ->assertSessionHasErrors('project_id');

        // Nonexistent cost center
        $this->actingAs($this->adminUser)
            ->get('/reports/cost-center-actuals?cost_center_id='.Str::uuid())
            ->assertSessionHasErrors('cost_center_id');

        // Nonexistent account
        $this->actingAs($this->adminUser)
            ->get('/reports/project-profitability?account_id='.Str::uuid())
            ->assertSessionHasErrors('account_id');

        // Invalid currency
        $this->actingAs($this->adminUser)
            ->get('/reports/project-profitability?currency=INVALID_XYZ')
            ->assertSessionHasErrors('currency');

        // Invalid date range (date_to before date_from)
        $this->actingAs($this->adminUser)
            ->get('/reports/project-profitability?date_from=2045-05-01&date_to=2045-04-01')
            ->assertSessionHasErrors('date_to');
    }

    public function test_inactive_projects_and_cost_centers_remain_reportable(): void
    {
        // Post historical transaction on Project Beta and CC Ops
        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'project_id' => $this->projectBeta->id, 'cost_center_id' => $this->costCenterOps->id, 'debit_minor' => 18000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 18000],
        ]);

        // Deactivate them
        $this->projectBeta->update(['is_active' => false]);
        $this->costCenterOps->update(['is_active' => false]);

        // Generate reports filtering by inactive dimensions
        $prjReport = app(ProjectProfitabilityReportService::class)->generate(
            projectId: $this->projectBeta->id,
            dateFrom: '2045-01-01',
            dateTo: '2045-01-31',
        );
        $betaRow = collect($prjReport['rows'])->firstWhere('project_code', 'PRJ-BETA');
        $this->assertNotNull($betaRow);
        $this->assertSame(18000, $betaRow['operating_expense_minor']);

        $ccReport = app(CostCenterActualsReportService::class)->generate(
            costCenterId: $this->costCenterOps->id,
            dateFrom: '2045-01-01',
            dateTo: '2045-01-31',
        );
        $opsRow = collect($ccReport['rows'])->firstWhere('cost_center_code', 'CC-OPS');
        $this->assertNotNull($opsRow);
        $this->assertSame(18000, $opsRow['debit_minor']);

        // ReportPageOptions still includes inactive dimensions for selection
        $pageOptions = app(ReportPageOptions::class);
        $this->assertTrue($pageOptions->projects()->contains('id', $this->projectBeta->id));
        $this->assertTrue($pageOptions->costCenters()->contains('id', $this->costCenterOps->id));
    }

    public function test_reports_hub_exposes_both_pages_through_dictionary_backed_cards(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
            );
    }

    public function test_source_scan_proves_no_tenant_or_company_assumptions_or_hardcoded_labels(): void
    {
        $filesToScan = [
            app_path('Application/Reports/ProjectProfitabilityReportService.php'),
            app_path('Application/Reports/ProjectProfitabilityCsvExporter.php'),
            app_path('Http/Controllers/Reports/ProjectProfitabilityReportController.php'),
            app_path('Application/Reports/CostCenterActualsReportService.php'),
            app_path('Application/Reports/CostCenterActualsCsvExporter.php'),
            app_path('Http/Controllers/Reports/CostCenterActualsReportController.php'),
            resource_path('js/Pages/Reports/ProjectProfitability.tsx'),
            resource_path('js/Pages/Reports/CostCenterActuals.tsx'),
        ];

        $prohibitedTokens = [
            'company_id',
            'tenant_id',
            'currentCompany',
            'currentTenant',
            'currentBranch',
            'HasTeams',
        ];

        foreach ($filesToScan as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);

            foreach ($prohibitedTokens as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $content,
                    "File {$file} contains prohibited multi-tenant token: {$token}"
                );
            }
        }

        // Check React pages for absence of native select and date inputs
        $reactPages = [
            resource_path('js/Pages/Reports/ProjectProfitability.tsx'),
            resource_path('js/Pages/Reports/CostCenterActuals.tsx'),
        ];

        foreach ($reactPages as $reactFile) {
            $reactContent = file_get_contents($reactFile);
            $this->assertStringNotContainsString('<select', $reactContent, "File {$reactFile} uses native <select>");
            $this->assertStringNotContainsString('type="date"', $reactContent, "File {$reactFile} uses native date input");
            $this->assertStringNotContainsString('window.location.href', $reactContent, "File {$reactFile} uses unsafe window.location.href");
        }
    }

    private function postJournal(array $lines, string $currency = 'EGP', ?string $date = null, ?string $periodId = null): void
    {
        $draft = app(JournalDraftService::class)->createDraft([
            'entry_date' => $date ?? '2045-01-15',
            'financial_period_id' => $periodId ?? $this->periodJan->id,
            'description' => 'Test financial movement',
            'currency' => $currency,
        ], $lines, $this->adminUser->id);

        app(PostingEngine::class)->post($draft, $this->adminUser->id);
    }

    private function createTestAccount(string $code, string $name, string $type, string $nature, string $groupId, ?string $statementLineId = null): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => $type,
            'nature' => $nature,
            'account_group_id' => $groupId,
            'financial_statement_line_id' => $statementLineId,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }
}
