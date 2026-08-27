<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Application\Reports\BranchProfitabilityReportService;
use App\Models\Account;
use App\Models\Branch;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10GlBranchProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $reportOnlyUser;

    private Branch $northBranch;

    private Branch $southBranch;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $cashAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'accounting.view',
            'accounting.create',
            'accounting.post',
            'accounting.reverse',
            'reports.view',
            'reports.export',
            'reports.print',
            'view_financials',
        ]);

        $this->reportOnlyUser = User::factory()->create(['locale' => 'en']);
        $this->reportOnlyUser->givePermissionTo('reports.view');

        $this->northBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-PNL-NORTH',
            'name' => ['en' => 'North Profit Center', 'ar' => 'مركز ربح الشمال'],
            'is_active' => true,
        ]);

        $this->southBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-PNL-SOUTH',
            'name' => ['en' => 'South Profit Center', 'ar' => 'مركز ربح الجنوب'],
            'is_active' => true,
        ]);

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2041,
            'start_date' => '2041-01-01',
            'end_date' => '2041-12-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2041-01-01',
            'end_date' => '2041-01-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->cashAccount = $this->createAccount('19991', 'Branch Cash Clearing', 'asset', 'debit');
        $this->revenueAccount = $this->createAccount('49991', 'Branch Service Revenue', 'revenue', 'credit');
        $this->expenseAccount = $this->createAccount('59991', 'Branch Operating Expense', 'expense', 'debit');
    }

    public function test_accounting_ledger_tables_have_optional_branch_dimension_without_tenant_scope(): void
    {
        foreach (['journal_entry', 'journal_line', 'ledger_entry'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'branch_id'));
            $this->assertFalse(Schema::hasColumn($table, 'company_id'));
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'));
        }
    }

    public function test_manual_journal_posts_branch_dimension_to_immutable_ledger_and_reversal_preserves_it(): void
    {
        $entry = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2041-01-10',
            'financial_period_id' => $this->period->id,
            'branch_id' => $this->northBranch->id,
            'description' => 'North branch sale',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 100000],
        ], $this->user->id);

        $posted = app(PostingEngine::class)->post($entry, $this->user->id);

        $this->assertSame((string) $this->northBranch->id, (string) $posted->fresh()->branch_id);
        $this->assertSame(2, LedgerEntry::query()->where('journal_entry_id', $posted->id)->where('branch_id', $this->northBranch->id)->count());

        $reversal = app(ReversalService::class)->reverse($posted, $this->period->id, $this->user->id);

        $this->assertSame((string) $this->northBranch->id, (string) $reversal->fresh()->branch_id);
        $this->assertSame(2, LedgerEntry::query()->where('journal_entry_id', $reversal->id)->where('branch_id', $this->northBranch->id)->count());
    }

    public function test_line_branch_overrides_header_branch_when_posting_ledger_entries(): void
    {
        $entry = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2041-01-11',
            'financial_period_id' => $this->period->id,
            'branch_id' => $this->northBranch->id,
            'description' => 'Cross branch correction',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->cashAccount->id, 'branch_id' => $this->southBranch->id, 'debit_minor' => 25000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 25000],
        ], $this->user->id);

        $posted = app(PostingEngine::class)->post($entry, $this->user->id);

        $this->assertTrue(LedgerEntry::query()
            ->where('journal_entry_id', $posted->id)
            ->where('account_id', $this->cashAccount->id)
            ->where('branch_id', $this->southBranch->id)
            ->exists());

        $this->assertTrue(LedgerEntry::query()
            ->where('journal_entry_id', $posted->id)
            ->where('account_id', $this->revenueAccount->id)
            ->where('branch_id', $this->northBranch->id)
            ->exists());
    }

    public function test_branch_profitability_report_uses_ledger_branch_dimension_and_surfaces_unassigned_pnl(): void
    {
        $this->postBranchSaleAndExpense();
        $this->postUnassignedExpense();

        $report = app(BranchProfitabilityReportService::class)->generate(
            dateFrom: '2041-01-01',
            dateTo: '2041-01-31',
        );

        $north = collect($report['rows'])->firstWhere('branch_code', 'BR-PNL-NORTH');
        $unassigned = collect($report['rows'])->firstWhere('is_unassigned', true);

        $this->assertNotNull($north);
        $this->assertSame(100000, $north['net_revenue_minor']);
        $this->assertSame(30000, $north['operating_expense_minor']);
        $this->assertSame(70000, $north['net_income_minor']);
        $this->assertSame(7000, $north['profit_margin_bps']);

        $this->assertNotNull($unassigned);
        $this->assertSame(-10000, $unassigned['net_income_minor']);
        $this->assertTrue($report['readiness']['has_unassigned_pnl']);
        $this->assertSame(-10000, $report['readiness']['unassigned_net_income_minor']);
        $this->assertSame(60000, $report['summary']['net_income_minor']);
    }

    public function test_branch_profitability_route_requires_financial_permission_and_renders_props(): void
    {
        $route = Route::getRoutes()->getByName('reports.branch-profitability');
        $this->assertNotNull($route);
        $this->assertContains('can:reports.view', $route->gatherMiddleware());
        $this->assertContains('can:view_financials', $route->gatherMiddleware());

        $exportRoute = Route::getRoutes()->getByName('reports.branch-profitability.export');
        $this->assertNotNull($exportRoute);
        $this->assertContains('can:reports.view', $exportRoute->gatherMiddleware());
        $this->assertContains('permission.all:reports.export,view_financials', $exportRoute->gatherMiddleware());

        $this->actingAs($this->reportOnlyUser)
            ->get('/reports/branch-profitability')
            ->assertForbidden();

        $this->actingAs($this->user)
            ->get('/reports/branch-profitability?date_from=2041-01-01&date_to=2041-01-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/BranchProfitability')
                ->has('reportData.rows')
                ->has('reportData.summary')
                ->has('reportData.readiness')
                ->has('branches')
            );
    }

    public function test_branch_profitability_export_requires_export_permission_and_streams_csv(): void
    {
        $this->postBranchSaleAndExpense();

        $this->actingAs($this->reportOnlyUser)
            ->get('/reports/branch-profitability/export?date_from=2041-01-01&date_to=2041-01-31')
            ->assertForbidden();

        $response = $this->actingAs($this->user)
            ->get('/reports/branch-profitability/export?date_from=2041-01-01&date_to=2041-01-31')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('BRANCH PROFITABILITY REPORT', $csv);
        $this->assertStringContainsString('BR-PNL-NORTH', $csv);
        $this->assertStringContainsString('70000', $csv);
    }

    private function postBranchSaleAndExpense(): void
    {
        $sale = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2041-01-12',
            'financial_period_id' => $this->period->id,
            'branch_id' => $this->northBranch->id,
            'description' => 'North P&L sale',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 100000],
        ], $this->user->id);
        app(PostingEngine::class)->post($sale, $this->user->id);

        $expense = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2041-01-13',
            'financial_period_id' => $this->period->id,
            'branch_id' => $this->northBranch->id,
            'description' => 'North P&L expense',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->expenseAccount->id, 'debit_minor' => 30000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 30000],
        ], $this->user->id);
        app(PostingEngine::class)->post($expense, $this->user->id);
    }

    private function postUnassignedExpense(): void
    {
        $expense = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2041-01-14',
            'financial_period_id' => $this->period->id,
            'description' => 'Unassigned P&L expense',
            'currency' => 'EGP',
        ], [
            ['account_id' => $this->expenseAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
            ['account_id' => $this->cashAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
        ], $this->user->id);
        app(PostingEngine::class)->post($expense, $this->user->id);
    }

    private function createAccount(string $code, string $name, string $type, string $nature): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => $type,
            'nature' => $nature,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }
}
