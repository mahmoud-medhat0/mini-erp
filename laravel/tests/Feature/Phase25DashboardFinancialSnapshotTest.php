<?php

namespace Tests\Feature;

use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ArAgingReportService;
use App\Application\Reports\IncomeStatementReportService;
use App\Application\Support\BaseCurrencyResolver;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\RentalContract;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AccountantAcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Support\AccountantWorkflowScenario;
use Tests\TestCase;

/**
 * Phase 25 - Financial Dashboard. Verifies the dashboard exposes real financial
 * KPIs (not just system-health counters) to users with `view_financials`, hides
 * them from users without it, and that every figure it shows is exactly what the
 * underlying report services (Income Statement, AR/AP Aging) and posted ledger
 * data independently compute for the same window.
 */
class Phase25DashboardFinancialSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_hides_financial_snapshot_without_view_financials_permission(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('dashboard.view', 'web');
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('financial', null)
                ->etc());
    }

    public function test_dashboard_financial_snapshot_matches_underlying_report_services_for_a_view_financials_user(): void
    {
        $this->seed(AccountantAcceptanceSeeder::class);

        $accountant = User::query()->where('email', 'accept.accountant@example.com')->first()
            ?? User::query()->firstOrFail();

        AccountantWorkflowScenario::run($accountant);

        $response = $this->actingAs($accountant)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $financial = $props['financial'];

        $this->assertIsArray($financial);

        $baseCurrency = app(BaseCurrencyResolver::class)->resolve();
        $this->assertSame($baseCurrency, $financial['currency']);
        $this->assertSame(Carbon::now()->format('Y-m-d'), $financial['asOfDate']);
        $this->assertSame(Carbon::now()->startOfMonth()->format('Y-m-d'), $financial['periodFrom']);

        $incomeStatement = app(IncomeStatementReportService::class)->generate(
            $financial['periodFrom'],
            $financial['asOfDate'],
        );
        $summary = $incomeStatement['summary'];

        $this->assertSame((int) $summary['net_revenue_minor'], $financial['revenueMinor']);
        $this->assertSame(
            (int) $summary['total_cogs_minor'] + (int) $summary['total_operating_expenses_minor'],
            $financial['expensesMinor'],
        );
        $this->assertSame((int) $summary['gross_profit_minor'], $financial['grossProfitMinor']);
        $this->assertSame((int) $summary['net_income_minor'], $financial['netProfitMinor']);

        $arTotals = app(ArAgingReportService::class)->generate($financial['asOfDate'], null, $baseCurrency)['grand_totals'];
        $apTotals = app(ApAgingReportService::class)->generate($financial['asOfDate'], null, $baseCurrency)['grand_totals'];

        $this->assertSame((int) $arTotals['total'], $financial['receivablesOutstandingMinor']);
        $this->assertSame((int) $arTotals['total'] - (int) $arTotals['current'], $financial['receivablesOverdueMinor']);
        $this->assertSame((int) $apTotals['total'], $financial['payablesOutstandingMinor']);
        $this->assertSame((int) $apTotals['total'] - (int) $apTotals['current'], $financial['payablesOverdueMinor']);

        $cashAccountIds = CashAccount::query()->where('is_active', true)->where('currency', $baseCurrency)->pluck('gl_account_id');
        $expectedCash = $this->glBalance($cashAccountIds);
        $this->assertSame($expectedCash, $financial['cashBalanceMinor']);

        $bankAccountIds = BankAccount::query()->where('is_active', true)->where('currency', $baseCurrency)->pluck('gl_account_id');
        $expectedBank = $this->glBalance($bankAccountIds);
        $this->assertSame($expectedBank, $financial['bankBalanceMinor']);

        $expectedInventory = (int) DB::table('stock_balance')->where('currency', $baseCurrency)->sum('valuation_amount_minor');
        $this->assertSame($expectedInventory, $financial['inventoryValueMinor']);

        $expectedNbv = $this->fixedAssetsNetBookValue($baseCurrency);
        $this->assertSame($expectedNbv, $financial['fixedAssetsNetBookValueMinor']);

        $expectedActiveRentals = RentalContract::query()->where('status', 'active')->count();
        $this->assertSame($expectedActiveRentals, $financial['activeRentalContracts']);

        // AccountantWorkflowScenario dates its postings from the seeded open
        // financial period's start date, not wall-clock "now", so "this month"
        // revenue can legitimately be zero in a test run. Confirm the scenario
        // did post real data using the date-independent counters instead.
        $this->assertGreaterThan(0, $props['counts']['postedJournals']);
        $this->assertGreaterThan(0, $props['counts']['ledgerEntries']);
    }

    /**
     * @param  Collection<int, string>  $accountIds
     */
    private function glBalance($accountIds): int
    {
        if ($accountIds->isEmpty()) {
            return 0;
        }

        $totals = DB::table('ledger_entry')
            ->whereIn('account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) as total_credit')
            ->first();

        return (int) ($totals->total_debit ?? 0) - (int) ($totals->total_credit ?? 0);
    }

    private function fixedAssetsNetBookValue(string $baseCurrency): int
    {
        $postedDepreciation = DB::table('fixed_asset_depreciation_schedule')
            ->select('fixed_asset_id')
            ->selectRaw('COALESCE(SUM(depreciation_minor), 0) as posted_minor')
            ->where('status', 'posted')
            ->groupBy('fixed_asset_id');

        $total = DB::table('fixed_asset')
            ->leftJoinSub($postedDepreciation, 'posted_depreciation', 'posted_depreciation.fixed_asset_id', '=', 'fixed_asset.id')
            ->where('fixed_asset.status', '!=', 'disposed')
            ->where('fixed_asset.currency', $baseCurrency)
            ->selectRaw('COALESCE(SUM(CASE WHEN fixed_asset.cost_minor - fixed_asset.opening_accumulated_depreciation_minor - COALESCE(posted_depreciation.posted_minor, 0) > 0 THEN fixed_asset.cost_minor - fixed_asset.opening_accumulated_depreciation_minor - COALESCE(posted_depreciation.posted_minor, 0) ELSE 0 END), 0) as total_nbv')
            ->value('total_nbv');

        return (int) $total;
    }
}
