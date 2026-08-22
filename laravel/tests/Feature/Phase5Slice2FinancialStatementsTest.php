<?php

namespace Tests\Feature;

use App\Application\Reports\BalanceSheetReportService;
use App\Application\Reports\IncomeStatementReportService;
use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\FinancialStatementLine;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\FinancialStatementLineSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase5Slice2FinancialStatementsTest extends TestCase
{
    use RefreshDatabase;

    private User $financialUser;

    private User $unprivilegedUser;

    private User $regularReportsUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(AccountCategorySeeder::class);
        $this->seed(AccountTypeSeeder::class);
        $this->seed(FinancialStatementLineSeeder::class);

        // Permissions
        Permission::findOrCreate('reports.view');
        Permission::findOrCreate('reports.export');
        Permission::findOrCreate('view_financials');

        $this->financialUser = User::factory()->create();
        $this->financialUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);

        $this->regularReportsUser = User::factory()->create();
        $this->regularReportsUser->givePermissionTo(['reports.view']);

        $this->unprivilegedUser = User::factory()->create();

        // Fiscal Year & Period
        $this->fiscalYear = FiscalYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ]);

        $this->period = FinancialPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'January 2026',
            'period_number' => 1,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);
    }

    public function test_balance_sheet_totals_and_equation(): void
    {
        $cashLine = FinancialStatementLine::where('code', 'ASSET_CURRENT')->firstOrFail();
        $equityLine = FinancialStatementLine::where('code', 'EQUITY')->firstOrFail();

        $cashAccount = Account::create([
            'code' => '1010-BS-TEST',
            'name' => ['en' => 'Cash Test', 'ar' => 'صندوق'],
            'type' => 'asset',
            'nature' => 'debit',
            'financial_statement_line_id' => $cashLine->id,
        ]);

        $equityAccount = Account::create([
            'code' => '3000-BS-TEST',
            'name' => ['en' => 'Capital Test', 'ar' => 'رأس المال'],
            'type' => 'equity',
            'nature' => 'credit',
            'financial_statement_line_id' => $equityLine->id,
        ]);

        $journal = JournalEntry::create([
            'entry_number' => 'JV-BS-001',
            'entry_date' => '2026-01-15',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $jLine1 = JournalLine::create([
            'journal_entry_id' => $journal->id,
            'line_no' => 1,
            'account_id' => $cashAccount->id,
            'debit_minor' => 100000,
            'credit_minor' => 0,
        ]);

        $jLine2 = JournalLine::create([
            'journal_entry_id' => $journal->id,
            'line_no' => 2,
            'account_id' => $equityAccount->id,
            'debit_minor' => 0,
            'credit_minor' => 100000,
        ]);

        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine1->id,
            'account_id' => $cashAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'debit_minor' => 100000,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'created_at' => '2026-01-15 10:00:00',
        ]);

        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine2->id,
            'account_id' => $equityAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'debit_minor' => 0,
            'credit_minor' => 100000,
            'currency' => 'EGP',
            'created_at' => '2026-01-15 10:00:00',
        ]);

        /** @var BalanceSheetReportService $service */
        $service = app(BalanceSheetReportService::class);
        $report = $service->generate('2026-01-31');

        $this->assertEquals(100000, $report['summary']['total_assets_minor']);
        $this->assertEquals(100000, $report['summary']['total_liabilities_and_equity_minor']);
        $this->assertTrue($report['summary']['is_balanced']);
        $this->assertEquals(0, $report['summary']['imbalance_minor']);
    }

    public function test_income_statement_calculations(): void
    {
        $revLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail();
        $cogsLine = FinancialStatementLine::where('code', 'COGS')->firstOrFail();
        $expLine = FinancialStatementLine::where('code', 'EXPENSE_OPERATING')->firstOrFail();

        $revAccount = Account::create([
            'code' => '4100-IS-TEST',
            'name' => ['en' => 'Sales Revenue'],
            'type' => 'revenue',
            'nature' => 'credit',
            'financial_statement_line_id' => $revLine->id,
        ]);

        $cogsAccount = Account::create([
            'code' => '5000-IS-TEST',
            'name' => ['en' => 'Cost of Goods Sold'],
            'type' => 'expense',
            'nature' => 'debit',
            'financial_statement_line_id' => $cogsLine->id,
        ]);

        $expAccount = Account::create([
            'code' => '5100-IS-TEST',
            'name' => ['en' => 'Rent Expense'],
            'type' => 'expense',
            'nature' => 'debit',
            'financial_statement_line_id' => $expLine->id,
        ]);

        $journal = JournalEntry::create([
            'entry_number' => 'JV-IS-001',
            'entry_date' => '2026-01-20',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $jLine1 = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 1, 'account_id' => $revAccount->id, 'debit_minor' => 0, 'credit_minor' => 50000]);
        $jLine2 = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 2, 'account_id' => $cogsAccount->id, 'debit_minor' => 20000, 'credit_minor' => 0]);
        $jLine3 = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 3, 'account_id' => $expAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0]);

        // Revenue: 50,000 minor
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine1->id,
            'account_id' => $revAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-20',
            'debit_minor' => 0,
            'credit_minor' => 50000,
            'currency' => 'EGP',
            'created_at' => '2026-01-20 10:00:00',
        ]);

        // COGS: 20,000 minor
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine2->id,
            'account_id' => $cogsAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-20',
            'debit_minor' => 20000,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'created_at' => '2026-01-20 10:00:00',
        ]);

        // Operating Expense: 10,000 minor
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine3->id,
            'account_id' => $expAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-20',
            'debit_minor' => 10000,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'created_at' => '2026-01-20 10:00:00',
        ]);

        /** @var IncomeStatementReportService $service */
        $service = app(IncomeStatementReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(50000, $report['summary']['total_revenue_minor']);
        $this->assertEquals(50000, $report['summary']['net_revenue_minor']);
        $this->assertEquals(20000, $report['summary']['total_cogs_minor']);
        $this->assertEquals(30000, $report['summary']['gross_profit_minor']);
        $this->assertEquals(10000, $report['summary']['total_operating_expenses_minor']);
        $this->assertEquals(20000, $report['summary']['net_income_minor']);
    }

    public function test_contra_revenue_reduces_net_revenue(): void
    {
        $revLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail();
        $contraLine = FinancialStatementLine::where('code', 'CONTRA_REVENUE')->firstOrFail();

        $revAccount = Account::create([
            'code' => '4100-CONTRA-TEST',
            'name' => ['en' => 'Gross Sales'],
            'type' => 'revenue',
            'nature' => 'credit',
            'financial_statement_line_id' => $revLine->id,
        ]);

        $contraAccount = Account::create([
            'code' => '4200-CONTRA-TEST',
            'name' => ['en' => 'Sales Returns'],
            'type' => 'contra_revenue',
            'nature' => 'debit',
            'financial_statement_line_id' => $contraLine->id,
        ]);

        $journal = JournalEntry::create([
            'entry_number' => 'JV-CONTRA-001',
            'entry_date' => '2026-01-22',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $jLine1 = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 1, 'account_id' => $revAccount->id, 'debit_minor' => 0, 'credit_minor' => 100000]);
        $jLine2 = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 2, 'account_id' => $contraAccount->id, 'debit_minor' => 15000, 'credit_minor' => 0]);

        // Gross Sales: 100,000
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine1->id,
            'account_id' => $revAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-22',
            'debit_minor' => 0,
            'credit_minor' => 100000,
            'currency' => 'EGP',
            'created_at' => '2026-01-22 10:00:00',
        ]);

        // Sales Returns: 15,000
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine2->id,
            'account_id' => $contraAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-22',
            'debit_minor' => 15000,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'created_at' => '2026-01-22 10:00:00',
        ]);

        /** @var IncomeStatementReportService $service */
        $service = app(IncomeStatementReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(100000, $report['summary']['total_revenue_minor']);
        $this->assertEquals(15000, $report['summary']['total_contra_revenue_minor']);
        $this->assertEquals(85000, $report['summary']['net_revenue_minor']);
    }

    public function test_unmapped_accounts_visibility_and_warning(): void
    {
        $unmappedAsset = Account::create([
            'code' => '1999-UNMAPPED',
            'name' => ['en' => 'Unmapped Asset'],
            'type' => 'asset',
            'nature' => 'debit',
            'financial_statement_line_id' => null,
        ]);

        $journal = JournalEntry::create([
            'entry_number' => 'JV-UNMAPPED-001',
            'entry_date' => '2026-01-25',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $jLine = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 1, 'account_id' => $unmappedAsset->id, 'debit_minor' => 5000, 'credit_minor' => 0]);

        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine->id,
            'account_id' => $unmappedAsset->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-25',
            'debit_minor' => 5000,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'created_at' => '2026-01-25 10:00:00',
        ]);

        /** @var BalanceSheetReportService $service */
        $service = app(BalanceSheetReportService::class);
        $report = $service->generate('2026-01-31');

        $this->assertTrue($report['has_unmapped_warning']);
        $this->assertNotEmpty($report['unmapped_accounts']);
        $this->assertEquals('1999-UNMAPPED', $report['unmapped_accounts'][0]['code']);
        $this->assertEquals(5000, $report['unmapped_accounts'][0]['net_minor']);
    }

    public function test_income_statement_uses_entry_date_not_creation_timestamp(): void
    {
        $revLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail();

        $revAccount = Account::create([
            'code' => '4100-ENTRY-DATE',
            'name' => ['en' => 'Entry Date Revenue'],
            'type' => 'revenue',
            'nature' => 'credit',
            'financial_statement_line_id' => $revLine->id,
        ]);

        $journal = JournalEntry::create([
            'entry_number' => 'JV-ENTRY-DATE-001',
            'entry_date' => '2026-01-31',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
            'posted_at' => '2026-02-05 10:00:00',
        ]);

        $line = JournalLine::create([
            'journal_entry_id' => $journal->id,
            'line_no' => 1,
            'account_id' => $revAccount->id,
            'debit_minor' => 0,
            'credit_minor' => 25000,
        ]);

        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $line->id,
            'account_id' => $revAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-31',
            'debit_minor' => 0,
            'credit_minor' => 25000,
            'currency' => 'EGP',
            'created_at' => '2026-02-05 10:00:00',
        ]);

        /** @var IncomeStatementReportService $service */
        $service = app(IncomeStatementReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(25000, $report['summary']['total_revenue_minor']);
        $this->assertEquals(25000, $report['summary']['net_income_minor']);
    }

    public function test_unmapped_accounts_without_movements_do_not_raise_warning(): void
    {
        Account::create([
            'code' => '1998-UNMAPPED-ZERO',
            'name' => ['en' => 'Unmapped Zero Movement Asset'],
            'type' => 'asset',
            'nature' => 'debit',
            'financial_statement_line_id' => null,
        ]);

        /** @var BalanceSheetReportService $service */
        $service = app(BalanceSheetReportService::class);
        $report = $service->generate('2026-01-31');

        $this->assertFalse($report['has_unmapped_warning']);
        $this->assertSame([], $report['unmapped_accounts']);
    }

    public function test_rbac_permissions_enforcement(): void
    {
        // 1. Unprivileged user -> 403 on Balance Sheet & Income Statement
        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/balance-sheet')
            ->assertStatus(403);

        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/income-statement')
            ->assertStatus(403);

        // 2. User with reports.view ONLY (no view_financials) -> 403
        $this->actingAs($this->regularReportsUser)
            ->get('/reports/balance-sheet')
            ->assertStatus(403);

        $this->actingAs($this->regularReportsUser)
            ->get('/reports/income-statement')
            ->assertStatus(403);

        // 3. Financial User (reports.view + view_financials) -> 200 OK with Inertia props
        $this->actingAs($this->financialUser)
            ->get('/reports/balance-sheet')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Reports/BalanceSheet')
                ->has('report')
                ->has('filters')
            );

        $this->actingAs($this->financialUser)
            ->get('/reports/income-statement')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Reports/IncomeStatement')
                ->has('report')
                ->has('periods')
                ->has('filters')
            );
    }

    public function test_export_permissions_enforcement(): void
    {
        // User without reports.export permission gets 403 on export route
        $userWithoutExport = User::factory()->create();
        $userWithoutExport->givePermissionTo(['reports.view', 'view_financials']);

        $this->actingAs($userWithoutExport)
            ->get('/reports/balance-sheet/export')
            ->assertStatus(403);

        $this->actingAs($userWithoutExport)
            ->get('/reports/income-statement/export')
            ->assertStatus(403);

        // Financial user with reports.export gets 200 Streamed Response CSV
        $this->actingAs($this->financialUser)
            ->get('/reports/balance-sheet/export')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->financialUser)
            ->get('/reports/income-statement/export')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
