<?php

namespace Tests\Feature;

use App\Application\Accounting\FinancialStatementMappingService;
use App\Application\Reports\CashFlowReportService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
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
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase5Slice3CashFlowStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $financialUser;

    private User $unprivilegedUser;

    private User $regularReportsUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $cashGl;

    private Account $bankGl;

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
        Permission::findOrCreate('accounting.mappings');

        $this->financialUser = User::factory()->create();
        $this->financialUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials', 'accounting.mappings']);

        $this->regularReportsUser = User::factory()->create();
        $this->regularReportsUser->givePermissionTo(['reports.view']);

        $this->unprivilegedUser = User::factory()->create();

        // Fiscal Year & Period
        $this->fiscalYear = FiscalYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->period = FinancialPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        // Cash & Bank GL Accounts
        $this->cashGl = Account::create([
            'code' => '1010-CASH',
            'name' => ['en' => 'Main Cash Box'],
            'type' => 'asset',
            'nature' => 'debit',
        ]);

        $this->bankGl = Account::create([
            'code' => '1020-BANK',
            'name' => ['en' => 'CIB Operating Bank Account'],
            'type' => 'asset',
            'nature' => 'debit',
        ]);

        CashAccount::create([
            'code' => 'CASH-01',
            'name' => ['en' => 'Main Cash Box'],
            'gl_account_id' => $this->cashGl->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        BankAccount::create([
            'code' => 'BANK-01',
            'name' => ['en' => 'CIB Account'],
            'bank_name' => ['en' => 'CIB'],
            'account_number' => '100020003000',
            'gl_account_id' => $this->bankGl->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
    }

    public function test_cash_equivalent_derivation_and_balances(): void
    {
        // 1. Opening balance entry before range (2025-12-31): Dr Cash 50,000
        $jPrev = JournalEntry::create(['number' => 'JV-PREV', 'entry_date' => '2025-12-31', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jlPrev = JournalLine::create(['journal_entry_id' => $jPrev->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 50000, 'credit_minor' => 0]);
        LedgerEntry::create([
            'journal_entry_id' => $jPrev->id,
            'journal_line_id' => $jlPrev->id,
            'account_id' => $this->cashGl->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2025-12-31',
            'debit_minor' => 50000,
            'credit_minor' => 0,
            'currency' => 'EGP',
        ]);

        // 2. In-period entry (2026-01-15): Dr Bank 30,000
        $jCur = JournalEntry::create(['number' => 'JV-CUR', 'entry_date' => '2026-01-15', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jlCur = JournalLine::create(['journal_entry_id' => $jCur->id, 'line_no' => 1, 'account_id' => $this->bankGl->id, 'debit_minor' => 30000, 'credit_minor' => 0]);
        LedgerEntry::create([
            'journal_entry_id' => $jCur->id,
            'journal_line_id' => $jlCur->id,
            'account_id' => $this->bankGl->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'debit_minor' => 30000,
            'credit_minor' => 0,
            'currency' => 'EGP',
        ]);

        /** @var CashFlowReportService $service */
        $service = app(CashFlowReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(50000, $report['opening_cash_minor']);
        $this->assertEquals(80000, $report['closing_cash_minor']);
        $this->assertEquals(30000, $report['period_cash_delta_minor']);
        $this->assertTrue($report['is_reconciled']);
    }

    public function test_operating_investing_financing_classification(): void
    {
        // Setup Non-Cash Accounts
        $salesLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail(); // operating
        $equipmentLine = FinancialStatementLine::where('code', 'ASSET_NON_CURRENT')->firstOrFail(); // investing
        $loanLine = FinancialStatementLine::where('code', 'LIABILITY_NON_CURRENT')->firstOrFail(); // financing

        $salesAcc = Account::create(['code' => '4100-SALES', 'name' => ['en' => 'Sales'], 'type' => 'revenue', 'nature' => 'credit', 'financial_statement_line_id' => $salesLine->id]);
        $equipAcc = Account::create(['code' => '1500-EQUIP', 'name' => ['en' => 'Equipment'], 'type' => 'asset', 'nature' => 'debit', 'financial_statement_line_id' => $equipmentLine->id]);
        $loanAcc = Account::create(['code' => '2500-LOAN', 'name' => ['en' => 'Bank Loan'], 'type' => 'liability', 'nature' => 'credit', 'financial_statement_line_id' => $loanLine->id]);

        // 1. Operating: Dr Cash 100,000 / Cr Sales 100,000
        $jOp = JournalEntry::create(['number' => 'JV-OP', 'entry_date' => '2026-01-10', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jlOp1 = JournalLine::create(['journal_entry_id' => $jOp->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 100000, 'credit_minor' => 0]);
        $jlOp2 = JournalLine::create(['journal_entry_id' => $jOp->id, 'line_no' => 2, 'account_id' => $salesAcc->id, 'debit_minor' => 0, 'credit_minor' => 100000]);
        LedgerEntry::create(['journal_entry_id' => $jOp->id, 'journal_line_id' => $jlOp1->id, 'account_id' => $this->cashGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-10', 'debit_minor' => 100000, 'credit_minor' => 0, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $jOp->id, 'journal_line_id' => $jlOp2->id, 'account_id' => $salesAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-10', 'debit_minor' => 0, 'credit_minor' => 100000, 'currency' => 'EGP']);

        // 2. Investing: Cr Cash 40,000 / Dr Equipment 40,000
        $jInv = JournalEntry::create(['number' => 'JV-INV', 'entry_date' => '2026-01-15', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jlInv1 = JournalLine::create(['journal_entry_id' => $jInv->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 0, 'credit_minor' => 40000]);
        $jlInv2 = JournalLine::create(['journal_entry_id' => $jInv->id, 'line_no' => 2, 'account_id' => $equipAcc->id, 'debit_minor' => 40000, 'credit_minor' => 0]);
        LedgerEntry::create(['journal_entry_id' => $jInv->id, 'journal_line_id' => $jlInv1->id, 'account_id' => $this->cashGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-15', 'debit_minor' => 0, 'credit_minor' => 40000, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $jInv->id, 'journal_line_id' => $jlInv2->id, 'account_id' => $equipAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-15', 'debit_minor' => 40000, 'credit_minor' => 0, 'currency' => 'EGP']);

        // 3. Financing: Dr Bank 50,000 / Cr Loan 50,000
        $jFin = JournalEntry::create(['number' => 'JV-FIN', 'entry_date' => '2026-01-20', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jlFin1 = JournalLine::create(['journal_entry_id' => $jFin->id, 'line_no' => 1, 'account_id' => $this->bankGl->id, 'debit_minor' => 50000, 'credit_minor' => 0]);
        $jlFin2 = JournalLine::create(['journal_entry_id' => $jFin->id, 'line_no' => 2, 'account_id' => $loanAcc->id, 'debit_minor' => 0, 'credit_minor' => 50000]);
        LedgerEntry::create(['journal_entry_id' => $jFin->id, 'journal_line_id' => $jlFin1->id, 'account_id' => $this->bankGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-20', 'debit_minor' => 50000, 'credit_minor' => 0, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $jFin->id, 'journal_line_id' => $jlFin2->id, 'account_id' => $loanAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-20', 'debit_minor' => 0, 'credit_minor' => 50000, 'currency' => 'EGP']);

        /** @var CashFlowReportService $service */
        $service = app(CashFlowReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(100000, $report['operating']['net_minor']);
        $this->assertEquals(-40000, $report['investing']['net_minor']);
        $this->assertEquals(50000, $report['financing']['net_minor']);
        $this->assertEquals(110000, $report['net_cash_change_minor']);
        $this->assertTrue($report['is_reconciled']);
    }

    public function test_internal_cash_transfer_reconciles_without_activity_totals(): void
    {
        // Transfer 20,000 from Bank to Cash Box (Dr Cash 20,000 / Cr Bank 20,000)
        $jXfer = JournalEntry::create(['number' => 'JV-XFER', 'entry_date' => '2026-01-18', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jl1 = JournalLine::create(['journal_entry_id' => $jXfer->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 20000, 'credit_minor' => 0]);
        $jl2 = JournalLine::create(['journal_entry_id' => $jXfer->id, 'line_no' => 2, 'account_id' => $this->bankGl->id, 'debit_minor' => 0, 'credit_minor' => 20000]);

        LedgerEntry::create(['journal_entry_id' => $jXfer->id, 'journal_line_id' => $jl1->id, 'account_id' => $this->cashGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-18', 'debit_minor' => 20000, 'credit_minor' => 0, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $jXfer->id, 'journal_line_id' => $jl2->id, 'account_id' => $this->bankGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-18', 'debit_minor' => 0, 'credit_minor' => 20000, 'currency' => 'EGP']);

        /** @var CashFlowReportService $service */
        $service = app(CashFlowReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(0, $report['operating']['net_minor']);
        $this->assertEquals(0, $report['investing']['net_minor']);
        $this->assertEquals(0, $report['financing']['net_minor']);
        $this->assertEquals(0, $report['net_cash_change_minor']);
        $this->assertTrue($report['is_reconciled']);
    }

    public function test_unclassified_journals_generate_warnings(): void
    {
        // Create unclassified non-cash account (no statement line, no account cash_flow_activity)
        $suspenseAcc = Account::create(['code' => '9999-SUSPENSE', 'name' => ['en' => 'Suspense Account'], 'type' => 'asset', 'nature' => 'debit', 'financial_statement_line_id' => null, 'cash_flow_activity' => null]);

        $jUnclass = JournalEntry::create(['number' => 'JV-UNCLASS', 'entry_date' => '2026-01-22', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $jl1 = JournalLine::create(['journal_entry_id' => $jUnclass->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 15000, 'credit_minor' => 0]);
        $jl2 = JournalLine::create(['journal_entry_id' => $jUnclass->id, 'line_no' => 2, 'account_id' => $suspenseAcc->id, 'debit_minor' => 0, 'credit_minor' => 15000]);

        LedgerEntry::create(['journal_entry_id' => $jUnclass->id, 'journal_line_id' => $jl1->id, 'account_id' => $this->cashGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-22', 'debit_minor' => 15000, 'credit_minor' => 0, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $jUnclass->id, 'journal_line_id' => $jl2->id, 'account_id' => $suspenseAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-22', 'debit_minor' => 0, 'credit_minor' => 15000, 'currency' => 'EGP']);

        /** @var CashFlowReportService $service */
        $service = app(CashFlowReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(15000, $report['unclassified']['net_minor']);
        $this->assertTrue($report['has_unclassified_warning']);
        $this->assertNotEmpty($report['unclassified_warnings']);
        $this->assertEquals('JV-UNCLASS', $report['unclassified_warnings'][0]['entry_number']);
        $this->assertEquals('unclassified_non_cash_accounts', $report['unclassified_warnings'][0]['reason_code']);
    }

    public function test_cash_flow_uses_entry_date_not_creation_timestamp(): void
    {
        $salesLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail();
        $salesAcc = Account::create([
            'code' => '4100-DATED',
            'name' => ['en' => 'Dated Sales'],
            'type' => 'revenue',
            'nature' => 'credit',
            'financial_statement_line_id' => $salesLine->id,
        ]);

        $journal = JournalEntry::create([
            'number' => 'JV-DATED',
            'entry_date' => '2026-01-25',
            'financial_period_id' => $this->period->id,
            'status' => 'posted',
        ]);
        $cashLine = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 22000, 'credit_minor' => 0]);
        $salesLineRow = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 2, 'account_id' => $salesAcc->id, 'debit_minor' => 0, 'credit_minor' => 22000]);

        LedgerEntry::create(['journal_entry_id' => $journal->id, 'journal_line_id' => $cashLine->id, 'account_id' => $this->cashGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-25', 'created_at' => '2026-02-05 09:00:00', 'debit_minor' => 22000, 'credit_minor' => 0, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $journal->id, 'journal_line_id' => $salesLineRow->id, 'account_id' => $salesAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-25', 'created_at' => '2026-02-05 09:00:00', 'debit_minor' => 0, 'credit_minor' => 22000, 'currency' => 'EGP']);

        /** @var CashFlowReportService $service */
        $service = app(CashFlowReportService::class);
        $januaryReport = $service->generate('2026-01-01', '2026-01-31');
        $februaryReport = $service->generate('2026-02-01', '2026-02-28');

        $this->assertEquals(22000, $januaryReport['operating']['net_minor']);
        $this->assertEquals(0, $februaryReport['operating']['net_minor']);
    }

    public function test_mixed_classification_cash_journals_are_unclassified(): void
    {
        $salesLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail();
        $loanLine = FinancialStatementLine::where('code', 'LIABILITY_NON_CURRENT')->firstOrFail();
        $salesAcc = Account::create(['code' => '4100-MIX', 'name' => ['en' => 'Mixed Sales'], 'type' => 'revenue', 'nature' => 'credit', 'financial_statement_line_id' => $salesLine->id]);
        $loanAcc = Account::create(['code' => '2500-MIX', 'name' => ['en' => 'Mixed Loan'], 'type' => 'liability', 'nature' => 'credit', 'financial_statement_line_id' => $loanLine->id]);

        $journal = JournalEntry::create(['number' => 'JV-MIXED', 'entry_date' => '2026-01-26', 'financial_period_id' => $this->period->id, 'status' => 'posted']);
        $cashLine = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 1, 'account_id' => $this->cashGl->id, 'debit_minor' => 100000, 'credit_minor' => 0]);
        $salesLineRow = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 2, 'account_id' => $salesAcc->id, 'debit_minor' => 0, 'credit_minor' => 40000]);
        $loanLineRow = JournalLine::create(['journal_entry_id' => $journal->id, 'line_no' => 3, 'account_id' => $loanAcc->id, 'debit_minor' => 0, 'credit_minor' => 60000]);

        LedgerEntry::create(['journal_entry_id' => $journal->id, 'journal_line_id' => $cashLine->id, 'account_id' => $this->cashGl->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-26', 'debit_minor' => 100000, 'credit_minor' => 0, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $journal->id, 'journal_line_id' => $salesLineRow->id, 'account_id' => $salesAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-26', 'debit_minor' => 0, 'credit_minor' => 40000, 'currency' => 'EGP']);
        LedgerEntry::create(['journal_entry_id' => $journal->id, 'journal_line_id' => $loanLineRow->id, 'account_id' => $loanAcc->id, 'financial_period_id' => $this->period->id, 'entry_date' => '2026-01-26', 'debit_minor' => 0, 'credit_minor' => 60000, 'currency' => 'EGP']);

        /** @var CashFlowReportService $service */
        $service = app(CashFlowReportService::class);
        $report = $service->generate('2026-01-01', '2026-01-31');

        $this->assertEquals(100000, $report['unclassified']['net_minor']);
        $this->assertEquals('mixed_cash_flow_activities', $report['unclassified_warnings'][0]['reason_code']);
        $this->assertEquals(0, $report['operating']['net_minor']);
        $this->assertEquals(0, $report['financing']['net_minor']);
    }

    public function test_account_cash_flow_override_requires_mappings_permission_and_non_cash_account(): void
    {
        $salesAcc = Account::create([
            'code' => '4100-OVERRIDE',
            'name' => ['en' => 'Override Sales'],
            'type' => 'revenue',
            'nature' => 'credit',
        ]);

        $this->actingAs($this->unprivilegedUser)
            ->post('/accounting/statement-mappings/account-cash-flow', [
                'account_id' => $salesAcc->id,
                'cash_flow_activity' => 'operating',
            ])
            ->assertStatus(403);

        $this->actingAs($this->financialUser)
            ->post('/accounting/statement-mappings/account-cash-flow', [
                'account_id' => $salesAcc->id,
                'cash_flow_activity' => 'investing',
            ])
            ->assertRedirect();

        $this->assertEquals('investing', $salesAcc->fresh()->cash_flow_activity);

        $this->expectException(ValidationException::class);
        app(FinancialStatementMappingService::class)
            ->updateAccountCashFlowActivity($this->cashGl->id, 'operating', $this->financialUser->id);
    }

    public function test_rbac_permissions_enforcement(): void
    {
        // 1. Unprivileged user -> 403 on Cash Flow report
        $this->actingAs($this->unprivilegedUser)
            ->get('/reports/cash-flow')
            ->assertStatus(403);

        // 2. User with reports.view ONLY (no view_financials) -> 403
        $this->actingAs($this->regularReportsUser)
            ->get('/reports/cash-flow')
            ->assertStatus(403);

        // 3. Financial User (reports.view + view_financials) -> 200 OK with Inertia props
        $this->actingAs($this->financialUser)
            ->get('/reports/cash-flow')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Reports/CashFlow')
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
            ->get('/reports/cash-flow/export')
            ->assertStatus(403);

        // Financial user with reports.export gets 200 Streamed Response CSV
        $this->actingAs($this->financialUser)
            ->get('/reports/cash-flow/export')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
