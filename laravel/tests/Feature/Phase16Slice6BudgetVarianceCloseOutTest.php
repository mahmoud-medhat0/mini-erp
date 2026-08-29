<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PostingEngine;
use App\Application\Budgeting\BudgetVarianceReportService;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FinancialStatementLine;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase16Slice6BudgetVarianceCloseOutTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $budgetingOnlyUser;

    private User $reportsOnlyUser;

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

    private Account $opexAccount;

    private Account $cogsAccount;

    private Budget $activeBudget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::factory()->create(['locale' => 'en']);
        $this->adminUser->givePermissionTo([
            'budgeting.view',
            'budgeting.create',
            'budgeting.edit',
            'budgeting.delete',
            'budgeting.approve',
            'budgeting.export',
            'reports.view',
            'reports.export',
            'reports.print',
            'view_financials',
            'accounting.view',
            'accounting.create',
            'accounting.post',
            'accounting.reverse',
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'costCenters.view',
            'costCenters.create',
            'costCenters.edit',
            'costCenters.delete',
        ]);

        $this->budgetingOnlyUser = User::factory()->create(['locale' => 'en']);
        $this->budgetingOnlyUser->givePermissionTo('budgeting.view');

        $this->reportsOnlyUser = User::factory()->create(['locale' => 'en']);
        $this->reportsOnlyUser->givePermissionTo('reports.view');

        $this->financialsOnlyUser = User::factory()->create(['locale' => 'en']);
        $this->financialsOnlyUser->givePermissionTo('view_financials');

        $this->fiscalYear = FiscalYear::query()->firstOrCreate(
            ['year' => 2026],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'status' => 'open',
            ]
        );

        $this->periodJan = FinancialPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $this->fiscalYear->id, 'month' => 1],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'status' => 'open',
            ]
        );

        $this->periodFeb = FinancialPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $this->fiscalYear->id, 'month' => 2],
            [
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-28',
                'status' => 'open',
            ]
        );

        $this->currencyEgp = Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            ['name' => ['en' => 'Egyptian Pound', 'ar' => 'جنيه مصري'], 'symbol' => 'E£', 'decimals' => 2, 'is_active' => true]
        );

        $this->currencyUsd = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'], 'symbol' => '$', 'decimals' => 2, 'is_active' => true]
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
            'category' => 'operations',
            'is_active' => true,
        ]);

        $groupAsset = AccountGroup::query()->firstOrCreate(
            ['code' => 'GRP-AST-2026'],
            ['name' => ['en' => 'Assets', 'ar' => 'الأصول'], 'type' => 'asset', 'sort_order' => 1, 'is_active' => true]
        );
        $groupRev = AccountGroup::query()->firstOrCreate(
            ['code' => 'GRP-REV-2026'],
            ['name' => ['en' => 'Revenue', 'ar' => 'الإيرادات'], 'type' => 'revenue', 'sort_order' => 2, 'is_active' => true]
        );
        $groupExp = AccountGroup::query()->firstOrCreate(
            ['code' => 'GRP-EXP-2026'],
            ['name' => ['en' => 'Expenses', 'ar' => 'المصروفات'], 'type' => 'expense', 'sort_order' => 3, 'is_active' => true]
        );

        $lineRev = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_REV_2026'],
            ['name' => ['en' => 'Operating Revenue', 'ar' => 'إيراد تشغيلي'], 'statement_type' => 'income_statement', 'section_code' => 'revenue', 'normal_balance' => 'credit', 'sort_order' => 10, 'is_active' => true]
        );
        $lineOpex = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_OPEX_2026'],
            ['name' => ['en' => 'Operating Expenses', 'ar' => 'مصروفات تشغيلية'], 'statement_type' => 'income_statement', 'section_code' => 'operating_expense', 'normal_balance' => 'debit', 'sort_order' => 40, 'is_active' => true]
        );
        $lineCogs = FinancialStatementLine::query()->firstOrCreate(
            ['code' => 'IS_COGS_2026'],
            ['name' => ['en' => 'Cost of Goods Sold', 'ar' => 'تكلفة البضاعة المباعة'], 'statement_type' => 'income_statement', 'section_code' => 'cogs', 'normal_balance' => 'debit', 'sort_order' => 30, 'is_active' => true]
        );

        $this->cashAccount = $this->createTestAccount('10100', 'Operating Bank', 'asset', 'debit', $groupAsset->id);
        $this->revenueAccount = $this->createTestAccount('40100', 'Sales Revenue', 'revenue', 'credit', $groupRev->id, $lineRev->id);
        $this->opexAccount = $this->createTestAccount('60100', 'Office Rent Expense', 'expense', 'debit', $groupExp->id, $lineOpex->id);
        $this->cogsAccount = $this->createTestAccount('50100', 'Direct Materials Expense', 'expense', 'debit', $groupExp->id, $lineCogs->id);

        $this->activeBudget = Budget::query()->create([
            'id' => (string) Str::uuid(),
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-2026-ACTIVE',
            'version_code' => 'V1',
            'name' => ['en' => '2026 Operating Budget', 'ar' => 'موازنة 2026 التشغيلية'],
            'description' => 'Active operating budget for 2026',
            'status' => 'active',
            'default_currency' => 'EGP',
            'activated_by' => $this->adminUser->id,
            'activated_at' => now(),
            'created_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);
    }

    public function test_guest_cannot_view_or_export_budget_variance(): void
    {
        $this->get('/budgeting/variance')->assertRedirect('/login');
        $this->get('/budgeting/variance/export')->assertRedirect('/login');
    }

    public function test_user_without_budgeting_or_reports_or_financials_permissions_is_forbidden(): void
    {
        $unauth = User::factory()->create();
        $this->actingAs($unauth)->get('/budgeting/variance')->assertForbidden();
        $this->actingAs($unauth)->get('/budgeting/variance/export')->assertForbidden();

        $this->actingAs($this->budgetingOnlyUser)->get('/budgeting/variance')->assertForbidden();
        $this->actingAs($this->reportsOnlyUser)->get('/budgeting/variance')->assertForbidden();
        $this->actingAs($this->financialsOnlyUser)->get('/budgeting/variance')->assertForbidden();

        // User with budgeting.view + reports.view but missing view_financials
        $partial = User::factory()->create();
        $partial->givePermissionTo(['budgeting.view', 'reports.view']);
        $this->actingAs($partial)->get('/budgeting/variance')->assertForbidden();

        // User with budgeting.view + view_financials but missing reports.view
        $partial2 = User::factory()->create();
        $partial2->givePermissionTo(['budgeting.view', 'view_financials']);
        $this->actingAs($partial2)->get('/budgeting/variance')->assertForbidden();
    }

    public function test_authorized_user_can_view_budget_variance_page_with_active_budget(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/budgeting/variance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budgeting/Variance')
                ->has('report')
                ->has('filters')
                ->has('options.budgets')
                ->has('options.fiscalYears')
                ->has('options.financialPeriods')
                ->has('options.accounts')
                ->has('options.projects')
                ->has('options.costCenters')
                ->has('options.currencies')
            );
    }

    public function test_defaults_to_active_budget_for_current_scope(): void
    {
        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate();

        $this->assertNotNull($result['selected_budget']);
        $this->assertSame((string) $this->activeBudget->id, $result['selected_budget']['id']);
        $this->assertSame('BDG-2026-ACTIVE', $result['selected_budget']['code']);
    }

    public function test_explicit_budget_id_loads_selected_approved_or_active_budget(): void
    {
        $approvedBudget = Budget::query()->create([
            'id' => (string) Str::uuid(),
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-2026-APPROVED',
            'version_code' => 'V2',
            'name' => ['en' => 'Approved V2 Budget', 'ar' => 'موازنة معتمدة V2'],
            'status' => 'approved',
            'default_currency' => 'EGP',
            'approved_by' => $this->adminUser->id,
            'approved_at' => now(),
            'created_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $approvedBudget->id);

        $this->assertNotNull($result['selected_budget']);
        $this->assertSame((string) $approvedBudget->id, $result['selected_budget']['id']);
        $this->assertSame('BDG-2026-APPROVED', $result['selected_budget']['code']);
        $this->assertSame('approved', $result['selected_budget']['status']);
    }

    public function test_draft_or_archived_or_cancelled_budget_is_rejected_or_marked_uncomparable(): void
    {
        $draftBudget = Budget::query()->create([
            'id' => (string) Str::uuid(),
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-2026-DRAFT',
            'version_code' => 'V3',
            'name' => ['en' => 'Draft Budget', 'ar' => 'موازنة مسودة'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'created_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $draftBudget->id);

        $this->assertNull($result['selected_budget']);
        $this->assertContains('budget_not_comparable', $result['warning_codes']);
        $this->assertEmpty($result['rows']);
    }

    public function test_budget_vs_actual_matches_exact_period_account_project_cost_center_currency_tuple(): void
    {
        // 1. Budget Line for 500,000 minor ($5,000.00)
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => $this->projectAlpha->id,
            'cost_center_id' => $this->costCenterHQ->id,
            'currency' => 'EGP',
            'amount_minor' => 500000,
            'created_by' => $this->adminUser->id,
        ]);

        // 2. Posted Journal Entry matching exact tuple for 450,000 minor ($4,500.00)
        $this->postJournal([
            [
                'account_id' => $this->opexAccount->id,
                'debit_minor' => 450000,
                'credit_minor' => 0,
                'project_id' => $this->projectAlpha->id,
                'cost_center_id' => $this->costCenterHQ->id,
            ],
            [
                'account_id' => $this->cashAccount->id,
                'debit_minor' => 0,
                'credit_minor' => 450000,
                'project_id' => null,
                'cost_center_id' => null,
            ],
        ], 'EGP', '2026-01-15', $this->periodJan->id);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(
            budgetId: (string) $this->activeBudget->id,
            periodId: (string) $this->periodJan->id,
            accountId: (string) $this->opexAccount->id,
        );

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];

        $this->assertSame((string) $this->periodJan->id, $row['financial_period_id']);
        $this->assertSame((string) $this->opexAccount->id, $row['account_id']);
        $this->assertSame((string) $this->projectAlpha->id, $row['project_id']);
        $this->assertSame((string) $this->costCenterHQ->id, $row['cost_center_id']);
        $this->assertSame('EGP', $row['currency']);
        $this->assertSame(500000, $row['budget_minor']);
        $this->assertSame(450000, $row['actual_minor']);
        $this->assertSame(-50000, $row['variance_minor']);
        $this->assertSame(50000, $row['variance_abs_minor']);
        $this->assertSame(1000, $row['variance_percent_bps']); // 50,000 / 500,000 = 10.00% = 1000 bps
        $this->assertSame('matched', $row['row_type']);
        $this->assertSame(1, $row['ledger_row_count']);
    }

    public function test_posted_ledger_actuals_are_included_and_draft_or_unposted_journals_are_excluded(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => $this->projectAlpha->id,
            'cost_center_id' => $this->costCenterHQ->id,
            'currency' => 'EGP',
            'amount_minor' => 300000,
            'created_by' => $this->adminUser->id,
        ]);

        // Create draft journal only (unposted)
        app(JournalDraftService::class)->createDraft([
            'entry_date' => '2026-01-20',
            'financial_period_id' => $this->periodJan->id,
            'description' => 'Unposted draft journal',
            'currency' => 'EGP',
        ], [
            [
                'account_id' => $this->opexAccount->id,
                'debit_minor' => 200000,
                'credit_minor' => 0,
                'project_id' => $this->projectAlpha->id,
                'cost_center_id' => $this->costCenterHQ->id,
            ],
            [
                'account_id' => $this->cashAccount->id,
                'debit_minor' => 0,
                'credit_minor' => 200000,
                'project_id' => null,
                'cost_center_id' => null,
            ],
        ], $this->adminUser->id);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(
            budgetId: (string) $this->activeBudget->id,
            periodId: (string) $this->periodJan->id,
            accountId: (string) $this->opexAccount->id,
        );

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $this->assertSame(300000, $row['budget_minor']);
        $this->assertSame(0, $row['actual_minor']); // Draft not included
        $this->assertSame('budget_only', $row['row_type']);
        $this->assertSame(0, $row['ledger_row_count']);
    }

    public function test_normal_balance_math_computes_actuals_correctly_for_debit_and_credit_accounts(): void
    {
        // 1. Debit account (Expense): debit = 100,000, credit = 10,000 -> actual = 90,000
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => null,
            'cost_center_id' => null,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 100000],
        ], 'EGP', '2026-01-10', $this->periodJan->id);

        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
            ['account_id' => $this->opexAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
        ], 'EGP', '2026-01-12', $this->periodJan->id);

        // 2. Credit account (Revenue): credit = 250,000, debit = 20,000 -> actual = 230,000
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->revenueAccount->id,
            'project_id' => null,
            'cost_center_id' => null,
            'currency' => 'EGP',
            'amount_minor' => 200000,
            'created_by' => $this->adminUser->id,
        ]);

        $this->postJournal([
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 250000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 250000],
        ], 'EGP', '2026-01-15', $this->periodJan->id);

        $this->postJournal([
            ['account_id' => $this->revenueAccount->id, 'debit_minor' => 20000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 20000],
        ], 'EGP', '2026-01-16', $this->periodJan->id);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, periodId: (string) $this->periodJan->id);

        // Filter for cashAccount actual_only if any, check opex and revenue rows
        $opexRow = collect($result['rows'])->firstWhere('account_id', (string) $this->opexAccount->id);
        $this->assertNotNull($opexRow);
        $this->assertSame(90000, $opexRow['actual_minor']); // 100,000 Dr - 10,000 Cr
        $this->assertSame(-10000, $opexRow['variance_minor']);

        $revRow = collect($result['rows'])->firstWhere('account_id', (string) $this->revenueAccount->id);
        $this->assertNotNull($revRow);
        $this->assertSame(230000, $revRow['actual_minor']); // 250,000 Cr - 20,000 Dr
        $this->assertSame(30000, $revRow['variance_minor']);
    }

    public function test_variance_and_percentage_bps_are_exact_integers(): void
    {
        // Budget = 100,000, Actual = 125,000 -> Variance = +25,000, BPS = 2500 (25.00%)
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => null,
            'cost_center_id' => null,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        $this->postJournal([
            ['account_id' => $this->opexAccount->id, 'debit_minor' => 125000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 125000],
        ], 'EGP', '2026-01-10', $this->periodJan->id);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, periodId: (string) $this->periodJan->id, accountId: (string) $this->opexAccount->id);

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $this->assertSame(25000, $row['variance_minor']);
        $this->assertSame(25000, $row['variance_abs_minor']);
        $this->assertSame(2500, $row['variance_percent_bps']);
    }

    public function test_budget_lines_without_actuals_are_reported_with_zero_actuals_and_flagged(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->cogsAccount->id,
            'project_id' => $this->projectBeta->id,
            'cost_center_id' => $this->costCenterOps->id,
            'currency' => 'EGP',
            'amount_minor' => 800000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, periodId: (string) $this->periodJan->id);

        $row = collect($result['rows'])->firstWhere('account_id', (string) $this->cogsAccount->id);
        $this->assertNotNull($row);
        $this->assertSame(800000, $row['budget_minor']);
        $this->assertSame(0, $row['actual_minor']);
        $this->assertSame(-800000, $row['variance_minor']);
        $this->assertSame(10000, $row['variance_percent_bps']); // 100.00%
        $this->assertSame('budget_only', $row['row_type']);
        $this->assertContains('budget_lines_without_actuals_present', $result['warning_codes']);
    }

    public function test_unbudgeted_actuals_are_reported_with_zero_budget_and_flagged(): void
    {
        // No budget line created for cogsAccount
        $this->postJournal([
            [
                'account_id' => $this->cogsAccount->id,
                'debit_minor' => 350000,
                'credit_minor' => 0,
                'project_id' => $this->projectBeta->id,
                'cost_center_id' => $this->costCenterOps->id,
            ],
            [
                'account_id' => $this->cashAccount->id,
                'debit_minor' => 0,
                'credit_minor' => 350000,
            ],
        ], 'EGP', '2026-01-18', $this->periodJan->id);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, periodId: (string) $this->periodJan->id);

        $row = collect($result['rows'])->firstWhere('account_id', (string) $this->cogsAccount->id);
        $this->assertNotNull($row);
        $this->assertSame(0, $row['budget_minor']);
        $this->assertSame(350000, $row['actual_minor']);
        $this->assertSame(350000, $row['variance_minor']);
        $this->assertNull($row['variance_percent_bps']); // Null when budget is 0
        $this->assertSame('actual_only', $row['row_type']);
        $this->assertContains('unbudgeted_actuals_present', $result['warning_codes']);
    }

    public function test_filter_by_financial_period_scopes_lines_and_actuals(): void
    {
        // Period Jan line
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        // Period Feb line
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodFeb->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 120000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(
            budgetId: (string) $this->activeBudget->id,
            periodId: (string) $this->periodJan->id,
            accountId: (string) $this->opexAccount->id,
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame((string) $this->periodJan->id, $result['rows'][0]['financial_period_id']);
        $this->assertSame(100000, $result['rows'][0]['budget_minor']);
    }

    public function test_filter_by_date_range_scopes_budget_periods_and_ledger_dates(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodFeb->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 120000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(
            budgetId: (string) $this->activeBudget->id,
            fromDate: '2026-02-01',
            toDate: '2026-02-28',
            accountId: (string) $this->opexAccount->id,
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame((string) $this->periodFeb->id, $result['rows'][0]['financial_period_id']);
        $this->assertSame(120000, $result['rows'][0]['budget_minor']);
    }

    public function test_filter_by_account_restricts_rows_correctly(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->revenueAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 500000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, accountId: (string) $this->revenueAccount->id);

        $this->assertCount(1, $result['rows']);
        $this->assertSame((string) $this->revenueAccount->id, $result['rows'][0]['account_id']);
        $this->assertSame(500000, $result['rows'][0]['budget_minor']);
    }

    public function test_filter_by_project_restricts_rows_correctly(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => $this->projectAlpha->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => $this->projectBeta->id,
            'currency' => 'EGP',
            'amount_minor' => 200000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, projectId: (string) $this->projectBeta->id);

        $this->assertCount(1, $result['rows']);
        $this->assertSame((string) $this->projectBeta->id, $result['rows'][0]['project_id']);
        $this->assertSame(200000, $result['rows'][0]['budget_minor']);
    }

    public function test_filter_by_cost_center_restricts_rows_correctly(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'cost_center_id' => $this->costCenterHQ->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'cost_center_id' => $this->costCenterOps->id,
            'currency' => 'EGP',
            'amount_minor' => 300000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $result = $service->generate(budgetId: (string) $this->activeBudget->id, costCenterId: (string) $this->costCenterHQ->id);

        $this->assertCount(1, $result['rows']);
        $this->assertSame((string) $this->costCenterHQ->id, $result['rows'][0]['cost_center_id']);
        $this->assertSame(100000, $result['rows'][0]['budget_minor']);
    }

    public function test_filter_by_currency_isolates_totals_and_warns_on_mixed_currency_scopes(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'currency' => 'USD',
            'amount_minor' => 2000,
            'created_by' => $this->adminUser->id,
        ]);

        $service = app(BudgetVarianceReportService::class);
        $resultMixed = $service->generate(budgetId: (string) $this->activeBudget->id);

        $this->assertContains('mixed_currencies', $resultMixed['warning_codes']);
        $this->assertArrayHasKey('EGP', $resultMixed['summary_by_currency']);
        $this->assertArrayHasKey('USD', $resultMixed['summary_by_currency']);
        $this->assertSame(100000, $resultMixed['summary_by_currency']['EGP']['budget_minor']);
        $this->assertSame(2000, $resultMixed['summary_by_currency']['USD']['budget_minor']);

        // Filter by currency EGP
        $resultEgp = $service->generate(
            budgetId: (string) $this->activeBudget->id,
            accountId: (string) $this->opexAccount->id,
            currency: 'EGP',
        );
        $this->assertNotContains('mixed_currencies', $resultEgp['warning_codes']);
        $this->assertCount(1, $resultEgp['rows']);
        $this->assertSame('EGP', $resultEgp['rows'][0]['currency']);
    }

    public function test_zero_gl_mutation_assertion_after_running_budget_variance_reports(): void
    {
        // Establish initial database state
        $initialJournals = JournalEntry::query()->count();
        $initialLedger = LedgerEntry::query()->count();
        $initialBudgets = Budget::query()->count();
        $initialBudgetLines = BudgetLine::query()->count();
        $initialAccounts = Account::query()->count();
        $initialPeriods = FinancialPeriod::query()->count();

        // Run multiple reports and CSV export
        $service = app(BudgetVarianceReportService::class);
        $service->generate();
        $service->generate(budgetId: (string) $this->activeBudget->id);
        $service->generate(fromDate: '2026-01-01', toDate: '2026-12-31');

        $this->actingAs($this->adminUser)->get('/budgeting/variance')->assertOk();
        $this->actingAs($this->adminUser)->get('/budgeting/variance/export')->assertOk();

        // Assert zero mutation
        $this->assertSame($initialJournals, JournalEntry::query()->count());
        $this->assertSame($initialLedger, LedgerEntry::query()->count());
        $this->assertSame($initialBudgets, Budget::query()->count());
        $this->assertSame($initialBudgetLines, BudgetLine::query()->count());
        $this->assertSame($initialAccounts, Account::query()->count());
        $this->assertSame($initialPeriods, FinancialPeriod::query()->count());
    }

    public function test_inertia_page_receives_expected_props_and_options(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/budgeting/variance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budgeting/Variance')
                ->has('report.selected_budget')
                ->has('report.filters')
                ->has('report.periods')
                ->has('report.rows')
                ->has('report.summary_by_currency')
                ->has('report.warning_codes')
                ->has('report.has_warnings')
                ->has('filters')
                ->has('options.budgets')
                ->has('options.fiscalYears')
                ->has('options.financialPeriods')
                ->has('options.accounts')
                ->has('options.projects')
                ->has('options.costCenters')
                ->has('options.currencies')
            );
    }

    public function test_csv_export_streams_exact_minor_units_and_bps_headers(): void
    {
        BudgetLine::query()->create([
            'id' => (string) Str::uuid(),
            'budget_id' => $this->activeBudget->id,
            'financial_period_id' => $this->periodJan->id,
            'account_id' => $this->opexAccount->id,
            'project_id' => $this->projectAlpha->id,
            'cost_center_id' => $this->costCenterHQ->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/budgeting/variance/export?budget_id='.$this->activeBudget->id);

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'text/csv'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('budget_code,budget_version,fiscal_year,period_label,account_code,account_name,project,cost_center,currency,budget_minor,actual_minor,variance_minor,variance_percent_bps,row_type', $content);
        $this->assertStringContainsString('BDG-2026-ACTIVE', $content);
        $this->assertStringContainsString('100000', $content);
        $this->assertStringContainsString('budget_only', $content);
    }

    public function test_ui_components_pass_static_analysis_and_avoid_prohibited_patterns(): void
    {
        $variancePath = resource_path('js/Pages/Budgeting/Variance.tsx');
        $this->assertFileExists($variancePath);

        $content = (string) file_get_contents($variancePath);

        $this->assertStringNotContainsString('<select', $content, 'Variance.tsx must not contain native <select>');
        $this->assertStringNotContainsString('<option', $content, 'Variance.tsx must not contain native <option>');
        $this->assertStringNotContainsString('type="date"', $content, 'Variance.tsx must not contain native type="date"');
        $this->assertStringNotContainsString('dangerouslySetInnerHTML', $content, 'Variance.tsx must not contain dangerouslySetInnerHTML');
        $this->assertStringNotContainsString('window.location.href', $content, 'Variance.tsx must not contain window.location.href');
        $this->assertStringNotContainsString('toFixed(', $content, 'Variance.tsx must not use floating-point formatting for basis-point percentages');
        $this->assertStringNotContainsString('/accounting/general-ledger', $content, 'Variance.tsx must link to the existing General Ledger route');
        $this->assertStringContainsString('/accounting/ledger?account_id=', $content);
    }

    public function test_strict_anti_tenancy_rules_are_preserved_across_phase_16_codebase(): void
    {
        $filesToScan = [
            app_path('Application/Budgeting/BudgetVarianceReportService.php'),
            app_path('Application/Budgeting/BudgetVarianceCsvExporter.php'),
            app_path('Application/Budgeting/BudgetVariancePageData.php'),
            app_path('Http/Controllers/Budgeting/BudgetVarianceController.php'),
            resource_path('js/Pages/Budgeting/Variance.tsx'),
        ];

        $prohibitedPatterns = [
            'company_id',
            'tenant_id',
            'currentCompany',
            'currentTenant',
            'currentBranch',
            'branch_id',
            'branch budget',
            'Spatie\Multitenancy',
            'Multitenancy',
        ];

        foreach ($filesToScan as $file) {
            $this->assertFileExists($file);
            $content = (string) file_get_contents($file);
            foreach ($prohibitedPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $content,
                    "File {$file} contains forbidden multi-tenancy token: {$pattern}"
                );
            }
        }
    }

    private function postJournal(array $lines, string $currency = 'EGP', ?string $date = null, ?string $periodId = null): void
    {
        $draft = app(JournalDraftService::class)->createDraft([
            'entry_date' => $date ?? '2026-01-15',
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
