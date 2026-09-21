<?php

namespace App\Application\Dashboard;

use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ArAgingReportService;
use App\Application\Reports\IncomeStatementReportService;
use App\Application\Support\BaseCurrencyResolver;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\RentalContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 25 - reuses the existing Income Statement / AR Aging / AP Aging report
 * services and direct reads of already-posted operational balances (cash,
 * bank, stock, fixed assets, rentals). No new tables, no new GL posting:
 * this is a read-only aggregation layer over data the system already computes.
 */
class DashboardFinancialSnapshotService
{
    public function __construct(
        private readonly IncomeStatementReportService $incomeStatementReportService,
        private readonly ArAgingReportService $arAgingReportService,
        private readonly ApAgingReportService $apAgingReportService,
        private readonly BaseCurrencyResolver $baseCurrencyResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $today = Carbon::now();
        $todayStr = $today->format('Y-m-d');
        $monthStart = $today->copy()->startOfMonth()->format('Y-m-d');
        $baseCurrency = $this->baseCurrencyResolver->resolve();

        $incomeStatement = $this->incomeStatementReportService->generate($monthStart, $todayStr);
        $summary = $incomeStatement['summary'];

        $arTotals = $this->arAgingReportService->generate($todayStr, null, $baseCurrency)['grand_totals'];
        $apTotals = $this->apAgingReportService->generate($todayStr, null, $baseCurrency)['grand_totals'];

        return [
            'currency' => $baseCurrency,
            'asOfDate' => $todayStr,
            'periodFrom' => $monthStart,
            'revenueMinor' => (int) $summary['net_revenue_minor'],
            'expensesMinor' => (int) $summary['total_cogs_minor'] + (int) $summary['total_operating_expenses_minor'],
            'grossProfitMinor' => (int) $summary['gross_profit_minor'],
            'netProfitMinor' => (int) $summary['net_income_minor'],
            'cashBalanceMinor' => $this->glBalanceForAccounts(
                CashAccount::query()->where('is_active', true)->where('currency', $baseCurrency)->pluck('gl_account_id')
            ),
            'bankBalanceMinor' => $this->glBalanceForAccounts(
                BankAccount::query()->where('is_active', true)->where('currency', $baseCurrency)->pluck('gl_account_id')
            ),
            'receivablesOutstandingMinor' => (int) $arTotals['total'],
            'receivablesOverdueMinor' => (int) $arTotals['total'] - (int) $arTotals['current'],
            'payablesOutstandingMinor' => (int) $apTotals['total'],
            'payablesOverdueMinor' => (int) $apTotals['total'] - (int) $apTotals['current'],
            'inventoryValueMinor' => (int) DB::table('stock_balance')
                ->where('currency', $baseCurrency)
                ->sum('valuation_amount_minor'),
            'fixedAssetsNetBookValueMinor' => $this->fixedAssetsNetBookValue($baseCurrency),
            'activeRentalContracts' => RentalContract::query()->where('status', 'active')->count(),
        ];
    }

    /**
     * @param  Collection<int, string>  $accountIds
     */
    private function glBalanceForAccounts($accountIds): int
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
