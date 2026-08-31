<?php

namespace Tests\Feature;

use App\Application\Reports\BalanceSheetReportService;
use App\Application\Reports\CashFlowReportService;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase5Slice6FinalCloseOutTest extends TestCase
{
    use RefreshDatabase;

    private User $financialUser;

    private User $regularReportsUser;

    private User $unprivilegedUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $assetAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(AccountCategorySeeder::class);
        $this->seed(AccountTypeSeeder::class);
        $this->seed(FinancialStatementLineSeeder::class);

        Permission::findOrCreate('reports.view');
        Permission::findOrCreate('reports.export');
        Permission::findOrCreate('reports.print');
        Permission::findOrCreate('view_financials');

        $this->financialUser = User::factory()->create();
        $this->financialUser->givePermissionTo(['reports.view', 'reports.export', 'reports.print', 'view_financials']);

        $this->regularReportsUser = User::factory()->create();
        $this->regularReportsUser->givePermissionTo(['reports.view']);

        $this->unprivilegedUser = User::factory()->create();

        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->period = FinancialPeriod::create([
            'id' => (string) Str::uuid(),
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $caLine = FinancialStatementLine::where('code', 'ASSET_CURRENT')->firstOrFail();
        $revLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail();
        $expLine = FinancialStatementLine::where('code', 'EXPENSE_OPERATING')->firstOrFail();

        $this->assetAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1010-P5S6',
            'name' => ['en' => 'Cash Account P5S6', 'ar' => 'حساب صندوق'],
            'type' => 'asset',
            'nature' => 'debit',
            'financial_statement_line_id' => $caLine->id,
            'cash_flow_activity' => 'operating',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '4010-P5S6',
            'name' => ['en' => 'Sales Revenue P5S6', 'ar' => 'إيراد مبيعات'],
            'type' => 'revenue',
            'nature' => 'credit',
            'financial_statement_line_id' => $revLine->id,
            'cash_flow_activity' => 'operating',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $this->expenseAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '5010-P5S6',
            'name' => ['en' => 'Operating Expense P5S6', 'ar' => 'مصروف تشغيلي'],
            'type' => 'expense',
            'nature' => 'debit',
            'financial_statement_line_id' => $expLine->id,
            'cash_flow_activity' => 'operating',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        // Post a Journal Entry
        $entry = JournalEntry::create([
            'id' => (string) Str::uuid(),
            'number' => 'JV-2026-P5S6',
            'entry_date' => '2026-01-15',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
            'created_by' => $this->financialUser->id,
            'posted_by' => $this->financialUser->id,
            'posted_at' => now(),
        ]);

        $l1 = JournalLine::create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->id,
            'line_no' => 1,
            'account_id' => $this->assetAccount->id,
            'debit_minor' => 100000,
            'credit_minor' => 0,
        ]);

        $l2 = JournalLine::create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->id,
            'line_no' => 2,
            'account_id' => $this->revenueAccount->id,
            'debit_minor' => 0,
            'credit_minor' => 100000,
        ]);

        LedgerEntry::create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->id,
            'journal_line_id' => $l1->id,
            'account_id' => $this->assetAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'debit_minor' => 100000,
            'credit_minor' => 0,
        ]);

        LedgerEntry::create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->id,
            'journal_line_id' => $l2->id,
            'account_id' => $this->revenueAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'debit_minor' => 0,
            'credit_minor' => 100000,
        ]);
    }

    public function test_slice_6_fixtures_use_actual_accounting_schema_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('financial_period', 'month'));
        $this->assertFalse(Schema::hasColumn('financial_period', 'period_number'));
        $this->assertFalse(Schema::hasColumn('financial_period', 'year'));
        $this->assertTrue(Schema::hasColumn('journal_entry', 'number'));
        $this->assertFalse(Schema::hasColumn('journal_entry', 'entry_number'));
        $this->assertFalse(Schema::hasColumn('journal_entry', 'fiscal_year_id'));
        $this->assertTrue(Schema::hasColumn('account', 'is_active'));
        $this->assertFalse(Schema::hasColumn('account', 'status'));
    }

    public function test_unauthorized_user_cannot_export_csv_reports(): void
    {
        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/balance-sheet/export?as_of_date=2026-01-31')
            ->assertStatus(403);

        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/income-statement/export?from_date=2026-01-01&to_date=2026-01-31')
            ->assertStatus(403);

        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/cash-flow/export?from_date=2026-01-01&to_date=2026-01-31')
            ->assertStatus(403);

        // User with reports.view but without view_financials is also forbidden
        $this->actingAs($this->regularReportsUser)
            ->get('/reports/balance-sheet/export?as_of_date=2026-01-31')
            ->assertStatus(403);

        $this->actingAs($this->regularReportsUser)
            ->get('/reports/income-statement/export?from_date=2026-01-01&to_date=2026-01-31')
            ->assertStatus(403);

        $this->actingAs($this->regularReportsUser)
            ->get('/reports/cash-flow/export?from_date=2026-01-01&to_date=2026-01-31')
            ->assertStatus(403);
    }

    public function test_financial_user_can_export_csv_reports_with_matching_service_totals(): void
    {
        // 1. Balance Sheet CSV
        $bsService = app(BalanceSheetReportService::class);
        $bsData = $bsService->generate('2026-01-31');

        $bsResponse = $this->actingAs($this->financialUser)
            ->get('/reports/balance-sheet/export?as_of_date=2026-01-31');

        $bsResponse->assertStatus(200);
        $bsContent = $bsResponse->streamedContent();

        $this->assertStringContainsString('BALANCE SHEET REPORT', $bsContent);
        $this->assertStringContainsString((string) $bsData['summary']['total_assets_minor'], $bsContent);
        $this->assertStringContainsString((string) $bsData['summary']['current_period_net_income_minor'], $bsContent);

        // 2. Income Statement CSV
        $isService = app(IncomeStatementReportService::class);
        $isData = $isService->generate('2026-01-01', '2026-01-31');

        $isResponse = $this->actingAs($this->financialUser)
            ->get('/reports/income-statement/export?from_date=2026-01-01&to_date=2026-01-31');

        $isResponse->assertStatus(200);
        $isContent = $isResponse->streamedContent();

        $this->assertStringContainsString('INCOME STATEMENT REPORT', $isContent);
        $this->assertStringContainsString((string) $isData['summary']['net_income_minor'], $isContent);

        // 3. Cash Flow Statement CSV
        $cfService = app(CashFlowReportService::class);
        $cfData = $cfService->generate('2026-01-01', '2026-01-31');

        $cfResponse = $this->actingAs($this->financialUser)
            ->get('/reports/cash-flow/export?from_date=2026-01-01&to_date=2026-01-31');

        $cfResponse->assertStatus(200);
        $cfContent = $cfResponse->streamedContent();

        $this->assertStringContainsString('CASH FLOW STATEMENT REPORT', $cfContent);
        $this->assertStringContainsString((string) $cfData['net_cash_change_minor'], $cfContent);
    }

    public function test_reports_view_and_financials_permissions_on_inertia_pages(): void
    {
        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/balance-sheet')
            ->assertStatus(403);

        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/income-statement')
            ->assertStatus(403);

        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/cash-flow')
            ->assertStatus(403);

        $this->actingAs($this->financialUser)
            ->get('/reports/balance-sheet')
            ->assertStatus(200);

        $this->actingAs($this->financialUser)
            ->get('/reports/income-statement')
            ->assertStatus(200);

        $this->actingAs($this->financialUser)
            ->get('/reports/cash-flow')
            ->assertStatus(200);
    }
}
