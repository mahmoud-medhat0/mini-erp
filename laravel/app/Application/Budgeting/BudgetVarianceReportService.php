<?php

namespace App\Application\Budgeting;

use App\Application\Reports\ReportCurrencyResolver;
use App\Models\Budget;
use App\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetVarianceReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /**
     * @return array{
     *     selected_budget: ?array{
     *         id: string,
     *         code: string,
     *         version_code: string,
     *         name: array<string, string>|string,
     *         description: ?string,
     *         status: string,
     *         default_currency: string,
     *         fiscal_year_id: string,
     *         fiscal_year: ?int
     *     },
     *     filters: array{
     *         budget_id: ?string,
     *         fiscal_year_id: ?string,
     *         period_id: ?string,
     *         from_date: ?string,
     *         to_date: ?string,
     *         account_id: ?string,
     *         project_id: ?string,
     *         cost_center_id: ?string,
     *         currency: ?string
     *     },
     *     periods: array<int, array{id: string, month: int, start_date: ?string, end_date: ?string}>,
     *     rows: array<int, array<string, mixed>>,
     *     summary_by_currency: array<string, array<string, mixed>>,
     *     warning_codes: array<int, string>,
     *     has_warnings: bool
     * }
     */
    public function generate(
        ?string $budgetId = null,
        ?string $fiscalYearId = null,
        ?string $periodId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $accountId = null,
        ?string $projectId = null,
        ?string $costCenterId = null,
        ?string $currency = null,
    ): array {
        $warningCodes = [];

        // 1. Budget Selection Rules
        $budget = null;

        if ($budgetId !== null && $budgetId !== '') {
            /** @var Budget|null $foundBudget */
            $foundBudget = Budget::query()->with(['fiscalYear.periods'])->where('id', $budgetId)->first();
            if (! $foundBudget) {
                $warningCodes[] = 'no_active_budget';
            } elseif (! in_array($foundBudget->status, ['active', 'approved'], true)) {
                $warningCodes[] = 'budget_not_comparable';
            } else {
                $budget = $foundBudget;
            }
        } elseif ($fiscalYearId !== null && $fiscalYearId !== '') {
            /** @var Budget|null $foundBudget */
            $foundBudget = Budget::query()
                ->with(['fiscalYear.periods'])
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('status', 'active')
                ->first();
            if (! $foundBudget) {
                $warningCodes[] = 'no_active_budget';
            } else {
                $budget = $foundBudget;
            }
        } else {
            /** @var Budget|null $foundBudget */
            $foundBudget = Budget::query()
                ->with(['fiscalYear.periods'])
                ->where('status', 'active')
                ->orderByDesc('activated_at')
                ->orderByDesc('created_at')
                ->first();
            if (! $foundBudget) {
                $warningCodes[] = 'no_active_budget';
            } else {
                $budget = $foundBudget;
            }
        }

        $filtersPayload = [
            'budget_id' => $budgetId,
            'fiscal_year_id' => $fiscalYearId,
            'period_id' => $periodId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account_id' => $accountId,
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'currency' => $currency,
        ];

        if (! $budget) {
            return [
                'selected_budget' => null,
                'filters' => $filtersPayload,
                'periods' => [],
                'rows' => [],
                'summary_by_currency' => [],
                'warning_codes' => array_values(array_unique($warningCodes)),
                'has_warnings' => count($warningCodes) > 0,
            ];
        }

        $selectedBudget = [
            'id' => (string) $budget->id,
            'code' => (string) $budget->code,
            'version_code' => (string) $budget->version_code,
            'name' => $budget->getTranslations('name'),
            'description' => $budget->description,
            'status' => (string) $budget->status,
            'default_currency' => (string) $budget->default_currency,
            'fiscal_year_id' => (string) $budget->fiscal_year_id,
            'fiscal_year' => $budget->fiscalYear?->year,
        ];

        // 2. Date and Period Rules
        $budgetFiscalYear = $budget->fiscalYear;
        $allFiscalYearPeriods = $budgetFiscalYear ? $budgetFiscalYear->periods->sortBy('month')->values() : collect();

        $scopedPeriodIds = [];
        $scopedPeriods = collect();
        $effectiveFromDate = null;
        $effectiveToDate = null;

        if ($periodId !== null && $periodId !== '') {
            /** @var FinancialPeriod|null $period */
            $period = FinancialPeriod::query()->where('id', $periodId)->first();
            if (! $period || (string) $period->fiscal_year_id !== (string) $budget->fiscal_year_id) {
                throw ValidationException::withMessages([
                    'period_id' => [__('The selected period does not belong to the selected budget fiscal year.')],
                ]);
            }
            $effectiveFromDate = $period->start_date ? Carbon::parse($period->start_date)->toDateString() : null;
            $effectiveToDate = $period->end_date ? Carbon::parse($period->end_date)->toDateString() : null;
            $scopedPeriodIds = [(string) $period->id];
            $scopedPeriods = collect([$period]);
        } elseif ($fromDate !== null || $toDate !== null) {
            $effectiveFromDate = $fromDate ? Carbon::parse($fromDate)->toDateString() : ($budgetFiscalYear?->start_date ? Carbon::parse($budgetFiscalYear->start_date)->toDateString() : '1900-01-01');
            $effectiveToDate = $toDate ? Carbon::parse($toDate)->toDateString() : ($budgetFiscalYear?->end_date ? Carbon::parse($budgetFiscalYear->end_date)->toDateString() : '2099-12-31');

            $scopedPeriods = $allFiscalYearPeriods->filter(function (FinancialPeriod $p) use ($effectiveFromDate, $effectiveToDate) {
                $pStart = Carbon::parse($p->start_date)->toDateString();
                $pEnd = Carbon::parse($p->end_date)->toDateString();

                return $pStart <= $effectiveToDate && $pEnd >= $effectiveFromDate;
            })->values();

            $scopedPeriodIds = $scopedPeriods->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $effectiveFromDate = $budgetFiscalYear?->start_date ? Carbon::parse($budgetFiscalYear->start_date)->toDateString() : null;
            $effectiveToDate = $budgetFiscalYear?->end_date ? Carbon::parse($budgetFiscalYear->end_date)->toDateString() : null;
            $scopedPeriods = $allFiscalYearPeriods;
            $scopedPeriodIds = $allFiscalYearPeriods->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        // 3. Query Budget Lines
        $budgetLines = DB::table('budget_line')
            ->join('financial_period', 'financial_period.id', '=', 'budget_line.financial_period_id')
            ->join('account', 'account.id', '=', 'budget_line.account_id')
            ->leftJoin('project', 'project.id', '=', 'budget_line.project_id')
            ->leftJoin('cost_center', 'cost_center.id', '=', 'budget_line.cost_center_id')
            ->where('budget_line.budget_id', $budget->id)
            ->whereIn('budget_line.financial_period_id', $scopedPeriodIds)
            ->when($accountId, fn ($q) => $q->where('budget_line.account_id', $accountId))
            ->when($projectId, fn ($q) => $q->where('budget_line.project_id', $projectId))
            ->when($costCenterId, fn ($q) => $q->where('budget_line.cost_center_id', $costCenterId))
            ->when($currency, fn ($q) => $q->where('budget_line.currency', $currency))
            ->select([
                'budget_line.financial_period_id',
                'budget_line.account_id',
                'budget_line.project_id',
                'budget_line.cost_center_id',
                'budget_line.currency',
                'financial_period.month as period_month',
                'financial_period.start_date as period_start_date',
                'financial_period.end_date as period_end_date',
                'financial_period.fiscal_year_id',
                'account.code as account_code',
                'account.name as account_name',
                'account.type as account_type',
                'account.nature as account_nature',
                'project.code as project_code',
                'project.name as project_name',
                'cost_center.code as cost_center_code',
                'cost_center.name as cost_center_name',
            ])
            ->selectRaw('COALESCE(SUM(budget_line.amount_minor), 0) as budget_minor')
            ->groupBy(
                'budget_line.financial_period_id',
                'budget_line.account_id',
                'budget_line.project_id',
                'budget_line.cost_center_id',
                'budget_line.currency',
                'financial_period.month',
                'financial_period.start_date',
                'financial_period.end_date',
                'financial_period.fiscal_year_id',
                'account.code',
                'account.name',
                'account.type',
                'account.nature',
                'project.code',
                'project.name',
                'cost_center.code',
                'cost_center.name'
            )
            ->get();

        // 4. Query Actuals from posted ledger entries only
        $actualsQuery = DB::table('ledger_entry')
            ->join('journal_entry', 'journal_entry.id', '=', 'ledger_entry.journal_entry_id')
            ->join('financial_period', 'financial_period.id', '=', 'ledger_entry.financial_period_id')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->leftJoin('project', 'project.id', '=', 'ledger_entry.project_id')
            ->leftJoin('cost_center', 'cost_center.id', '=', 'ledger_entry.cost_center_id')
            ->where('journal_entry.status', 'posted')
            ->where('financial_period.fiscal_year_id', $budget->fiscal_year_id)
            ->whereIn('ledger_entry.financial_period_id', $scopedPeriodIds)
            ->when($effectiveFromDate, fn ($q) => $q->where('ledger_entry.entry_date', '>=', $effectiveFromDate))
            ->when($effectiveToDate, fn ($q) => $q->where('ledger_entry.entry_date', '<=', $effectiveToDate))
            ->when($accountId, fn ($q) => $q->where('ledger_entry.account_id', $accountId))
            ->when($projectId, fn ($q) => $q->where('ledger_entry.project_id', $projectId))
            ->when($costCenterId, fn ($q) => $q->where('ledger_entry.cost_center_id', $costCenterId))
            ->when($currency, fn ($q) => $q->where('ledger_entry.currency', $currency))
            ->select([
                'ledger_entry.financial_period_id',
                'ledger_entry.account_id',
                'ledger_entry.project_id',
                'ledger_entry.cost_center_id',
                'ledger_entry.currency',
                'financial_period.month as period_month',
                'financial_period.start_date as period_start_date',
                'financial_period.end_date as period_end_date',
                'financial_period.fiscal_year_id',
                'account.code as account_code',
                'account.name as account_name',
                'account.type as account_type',
                'account.nature as account_nature',
                'project.code as project_code',
                'project.name as project_name',
                'cost_center.code as cost_center_code',
                'cost_center.name as cost_center_name',
            ])
            ->selectRaw('COUNT(ledger_entry.id) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
            ->groupBy(
                'ledger_entry.financial_period_id',
                'ledger_entry.account_id',
                'ledger_entry.project_id',
                'ledger_entry.cost_center_id',
                'ledger_entry.currency',
                'financial_period.month',
                'financial_period.start_date',
                'financial_period.end_date',
                'financial_period.fiscal_year_id',
                'account.code',
                'account.name',
                'account.type',
                'account.nature',
                'project.code',
                'project.name',
                'cost_center.code',
                'cost_center.name'
            )
            ->get();

        // 5. Merge tuples by exact key
        $makeKey = function ($pId, $accId, $prjId, $ccId, $curr): string {
            return implode('__', [
                (string) $pId,
                (string) $accId,
                (string) ($prjId ?? ''),
                (string) ($ccId ?? ''),
                (string) $curr,
            ]);
        };

        $tuples = [];

        foreach ($budgetLines as $bl) {
            $k = $makeKey($bl->financial_period_id, $bl->account_id, $bl->project_id, $bl->cost_center_id, $bl->currency);
            $tuples[$k] = [
                'financial_period_id' => (string) $bl->financial_period_id,
                'period_month' => (int) $bl->period_month,
                'period_start_date' => $bl->period_start_date ? Carbon::parse($bl->period_start_date)->toDateString() : null,
                'period_end_date' => $bl->period_end_date ? Carbon::parse($bl->period_end_date)->toDateString() : null,
                'fiscal_year_id' => (string) $bl->fiscal_year_id,
                'account_id' => (string) $bl->account_id,
                'account_code' => (string) $bl->account_code,
                'account_name' => is_string($bl->account_name) && str_starts_with($bl->account_name, '{') ? json_decode($bl->account_name, true) : $bl->account_name,
                'account_type' => (string) $bl->account_type,
                'account_nature' => (string) $bl->account_nature,
                'project_id' => $bl->project_id ? (string) $bl->project_id : null,
                'project_code' => $bl->project_code ? (string) $bl->project_code : null,
                'project_name' => $bl->project_name ? (is_string($bl->project_name) && str_starts_with($bl->project_name, '{') ? json_decode($bl->project_name, true) : $bl->project_name) : null,
                'cost_center_id' => $bl->cost_center_id ? (string) $bl->cost_center_id : null,
                'cost_center_code' => $bl->cost_center_code ? (string) $bl->cost_center_code : null,
                'cost_center_name' => $bl->cost_center_name ? (is_string($bl->cost_center_name) && str_starts_with($bl->cost_center_name, '{') ? json_decode($bl->cost_center_name, true) : $bl->cost_center_name) : null,
                'currency' => (string) $bl->currency,
                'has_budget' => true,
                'has_actual' => false,
                'budget_minor' => (int) $bl->budget_minor,
                'debit_minor' => 0,
                'credit_minor' => 0,
                'ledger_row_count' => 0,
            ];
        }

        foreach ($actualsQuery as $act) {
            $k = $makeKey($act->financial_period_id, $act->account_id, $act->project_id, $act->cost_center_id, $act->currency);
            if (! isset($tuples[$k])) {
                $tuples[$k] = [
                    'financial_period_id' => (string) $act->financial_period_id,
                    'period_month' => (int) $act->period_month,
                    'period_start_date' => $act->period_start_date ? Carbon::parse($act->period_start_date)->toDateString() : null,
                    'period_end_date' => $act->period_end_date ? Carbon::parse($act->period_end_date)->toDateString() : null,
                    'fiscal_year_id' => (string) $act->fiscal_year_id,
                    'account_id' => (string) $act->account_id,
                    'account_code' => (string) $act->account_code,
                    'account_name' => is_string($act->account_name) && str_starts_with($act->account_name, '{') ? json_decode($act->account_name, true) : $act->account_name,
                    'account_type' => (string) $act->account_type,
                    'account_nature' => (string) $act->account_nature,
                    'project_id' => $act->project_id ? (string) $act->project_id : null,
                    'project_code' => $act->project_code ? (string) $act->project_code : null,
                    'project_name' => $act->project_name ? (is_string($act->project_name) && str_starts_with($act->project_name, '{') ? json_decode($act->project_name, true) : $act->project_name) : null,
                    'cost_center_id' => $act->cost_center_id ? (string) $act->cost_center_id : null,
                    'cost_center_code' => $act->cost_center_code ? (string) $act->cost_center_code : null,
                    'cost_center_name' => $act->cost_center_name ? (is_string($act->cost_center_name) && str_starts_with($act->cost_center_name, '{') ? json_decode($act->cost_center_name, true) : $act->cost_center_name) : null,
                    'currency' => (string) $act->currency,
                    'has_budget' => false,
                    'has_actual' => true,
                    'budget_minor' => 0,
                    'debit_minor' => (int) $act->debit_minor,
                    'credit_minor' => (int) $act->credit_minor,
                    'ledger_row_count' => (int) $act->ledger_row_count,
                ];
            } else {
                $tuples[$k]['has_actual'] = true;
                $tuples[$k]['debit_minor'] = (int) $act->debit_minor;
                $tuples[$k]['credit_minor'] = (int) $act->credit_minor;
                $tuples[$k]['ledger_row_count'] = (int) $act->ledger_row_count;
            }
        }

        // 6. Calculate amounts, variances, basis points, and row types
        $rows = [];
        $hasUnbudgetedActuals = false;
        $hasBudgetWithoutActuals = false;
        $currenciesFound = [];

        foreach ($tuples as $item) {
            $budgetMinor = (int) $item['budget_minor'];
            $debitMinor = (int) $item['debit_minor'];
            $creditMinor = (int) $item['credit_minor'];
            $ledgerRowCount = (int) $item['ledger_row_count'];
            $nature = $item['account_nature'];

            // Normal balance calculation:
            // debit-nature: debit - credit
            // credit-nature: credit - debit
            $actualMinor = ($nature === 'credit') ? ($creditMinor - $debitMinor) : ($debitMinor - $creditMinor);

            // variance_minor = actual_minor - budget_minor
            $varianceMinor = $actualMinor - $budgetMinor;
            $varianceAbsMinor = abs($varianceMinor);

            // variance_percent_bps: null when budget is zero, otherwise integer basis points = half-up rounded abs(variance_minor) * 10000 / budget_minor
            $variancePercentBps = null;
            if ($budgetMinor > 0) {
                $variancePercentBps = (int) intdiv($varianceAbsMinor * 20000 + $budgetMinor, $budgetMinor * 2);
            }

            $rowType = 'matched';
            if ($item['has_budget'] && ! $item['has_actual']) {
                $rowType = 'budget_only';
                $hasBudgetWithoutActuals = true;
            } elseif (! $item['has_budget'] && $item['has_actual']) {
                $rowType = 'actual_only';
                $hasUnbudgetedActuals = true;
            } else {
                $rowType = 'matched';
            }

            $currenciesFound[$item['currency']] = true;

            $rows[] = [
                'financial_period_id' => $item['financial_period_id'],
                'period_month' => $item['period_month'],
                'period_start_date' => $item['period_start_date'],
                'period_end_date' => $item['period_end_date'],
                'fiscal_year_id' => $item['fiscal_year_id'],
                'account_id' => $item['account_id'],
                'account_code' => $item['account_code'],
                'account_name' => $item['account_name'],
                'account_type' => $item['account_type'],
                'account_nature' => $item['account_nature'],
                'project_id' => $item['project_id'],
                'project_code' => $item['project_code'],
                'project_name' => $item['project_name'],
                'cost_center_id' => $item['cost_center_id'],
                'cost_center_code' => $item['cost_center_code'],
                'cost_center_name' => $item['cost_center_name'],
                'currency' => $item['currency'],
                'budget_minor' => $budgetMinor,
                'actual_minor' => $actualMinor,
                'debit_minor' => $debitMinor,
                'credit_minor' => $creditMinor,
                'ledger_row_count' => $ledgerRowCount,
                'variance_minor' => $varianceMinor,
                'variance_abs_minor' => $varianceAbsMinor,
                'variance_percent_bps' => $variancePercentBps,
                'row_type' => $rowType,
            ];
        }

        // Sort rows deterministically: period_month asc, account_code asc, project_code asc, cost_center_code asc, currency asc
        usort($rows, function (array $a, array $b): int {
            if ($a['period_month'] !== $b['period_month']) {
                return $a['period_month'] <=> $b['period_month'];
            }
            if ($a['account_code'] !== $b['account_code']) {
                return strcmp((string) $a['account_code'], (string) $b['account_code']);
            }
            $pA = (string) ($a['project_code'] ?? '');
            $pB = (string) ($b['project_code'] ?? '');
            if ($pA !== $pB) {
                return strcmp($pA, $pB);
            }
            $ccA = (string) ($a['cost_center_code'] ?? '');
            $ccB = (string) ($b['cost_center_code'] ?? '');
            if ($ccA !== $ccB) {
                return strcmp($ccA, $ccB);
            }

            return strcmp((string) $a['currency'], (string) $b['currency']);
        });

        // 7. Summarize by currency
        $distinctCurrencies = array_keys($currenciesFound);
        sort($distinctCurrencies);

        if (empty($distinctCurrencies)) {
            $fallbackCurrency = $currency ?? ($budget->default_currency ?? $this->currencyResolver->resolve());
            $distinctCurrencies = [$fallbackCurrency];
        }

        $summaryByCurrency = [];
        foreach ($distinctCurrencies as $c) {
            $summaryByCurrency[$c] = [
                'currency' => $c,
                'budget_minor' => 0,
                'actual_minor' => 0,
                'debit_minor' => 0,
                'credit_minor' => 0,
                'variance_minor' => 0,
                'variance_abs_minor' => 0,
                'variance_percent_bps' => null,
                'row_count' => 0,
                'matched_count' => 0,
                'budget_only_count' => 0,
                'actual_only_count' => 0,
            ];
        }

        foreach ($rows as $r) {
            $c = (string) $r['currency'];
            if (! isset($summaryByCurrency[$c])) {
                $summaryByCurrency[$c] = [
                    'currency' => $c,
                    'budget_minor' => 0,
                    'actual_minor' => 0,
                    'debit_minor' => 0,
                    'credit_minor' => 0,
                    'variance_minor' => 0,
                    'variance_abs_minor' => 0,
                    'variance_percent_bps' => null,
                    'row_count' => 0,
                    'matched_count' => 0,
                    'budget_only_count' => 0,
                    'actual_only_count' => 0,
                ];
            }

            $summaryByCurrency[$c]['budget_minor'] += (int) $r['budget_minor'];
            $summaryByCurrency[$c]['actual_minor'] += (int) $r['actual_minor'];
            $summaryByCurrency[$c]['debit_minor'] += (int) $r['debit_minor'];
            $summaryByCurrency[$c]['credit_minor'] += (int) $r['credit_minor'];
            $summaryByCurrency[$c]['variance_minor'] += (int) $r['variance_minor'];
            $summaryByCurrency[$c]['row_count']++;

            if ($r['row_type'] === 'matched') {
                $summaryByCurrency[$c]['matched_count']++;
            } elseif ($r['row_type'] === 'budget_only') {
                $summaryByCurrency[$c]['budget_only_count']++;
            } elseif ($r['row_type'] === 'actual_only') {
                $summaryByCurrency[$c]['actual_only_count']++;
            }
        }

        foreach ($summaryByCurrency as $c => &$s) {
            $s['variance_abs_minor'] = abs($s['variance_minor']);
            if ($s['budget_minor'] > 0) {
                $s['variance_percent_bps'] = (int) intdiv($s['variance_abs_minor'] * 20000 + $s['budget_minor'], $s['budget_minor'] * 2);
            } else {
                $s['variance_percent_bps'] = null;
            }
        }
        unset($s);

        // 8. Machine-readable warning codes
        if (count($distinctCurrencies) > 1) {
            $warningCodes[] = 'mixed_currencies';
        }
        if ($hasUnbudgetedActuals) {
            $warningCodes[] = 'unbudgeted_actuals_present';
        }
        if ($hasBudgetWithoutActuals) {
            $warningCodes[] = 'budget_lines_without_actuals_present';
        }

        $warningCodes = array_values(array_unique($warningCodes));

        $periodsPayload = $scopedPeriods->map(fn (FinancialPeriod $p) => [
            'id' => (string) $p->id,
            'month' => (int) $p->month,
            'start_date' => $p->start_date ? Carbon::parse($p->start_date)->toDateString() : null,
            'end_date' => $p->end_date ? Carbon::parse($p->end_date)->toDateString() : null,
        ])->values()->all();

        return [
            'selected_budget' => $selectedBudget,
            'filters' => $filtersPayload,
            'periods' => $periodsPayload,
            'rows' => $rows,
            'summary_by_currency' => $summaryByCurrency,
            'warning_codes' => $warningCodes,
            'has_warnings' => count($warningCodes) > 0,
        ];
    }
}
