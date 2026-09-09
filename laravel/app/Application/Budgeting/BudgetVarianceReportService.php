<?php

namespace App\Application\Budgeting;

use App\Application\Reports\ReportCurrencyResolver;
use App\Models\Budget;
use App\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;
use Yajra\DataTables\Facades\DataTables;

class BudgetVarianceReportService
{
    private const SORT_COLUMNS = [
        'period_month' => 'budget_variance.period_month',
        'account_code' => 'budget_variance.account_code',
        'account_name' => 'budget_variance.account_name',
        'project_code' => 'budget_variance.project_code',
        'cost_center_code' => 'budget_variance.cost_center_code',
        'currency' => 'budget_variance.currency',
        'budget_minor' => 'budget_variance.budget_minor',
        'actual_minor' => 'budget_variance.actual_minor',
        'variance_minor' => 'budget_variance.variance_minor',
        'variance_percent_bps' => 'budget_variance.variance_percent_bps',
        'row_type' => 'budget_variance.row_type',
        'ledger_row_count' => 'budget_variance.ledger_row_count',
    ];

    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /**
     * `.name` columns are translatable `json` on Postgres, which has no
     * equality operator for `json` (only `jsonb`) and so cannot be used in
     * GROUP BY - cast to `::text` there. SQLite has no such distinct json
     * type (it's stored as plain TEXT already) and does not understand the
     * `::text` cast syntax, so only apply it on pgsql.
     */
    private function groupableTextColumn(string $qualifiedColumn): Expression|string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? DB::raw("{$qualifiedColumn}::text")
            : $qualifiedColumn;
    }

    private function groupableTextSelect(string $qualifiedColumn, string $alias): Expression|string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? DB::raw("{$qualifiedColumn}::text as {$alias}")
            : "{$qualifiedColumn} as {$alias}";
    }

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
            // Group only by each joined table's own primary key (plus the plain
            // scalar columns already unique per group) - `account.name` /
            // `project.name` / `cost_center.name` are translatable `json`
            // columns, and Postgres has no equality operator for `json`
            // (only `jsonb`), so listing them directly in GROUP BY fails
            // outright. They're functionally dependent on their table's `id`,
            // which Postgres already accepts here to allow selecting them
            // without grouping by them.
            ->groupBy(
                'budget_line.financial_period_id',
                'budget_line.account_id',
                'budget_line.project_id',
                'budget_line.cost_center_id',
                'budget_line.currency',
                'financial_period.id',
                'financial_period.month',
                'financial_period.start_date',
                'financial_period.end_date',
                'financial_period.fiscal_year_id',
                'account.id',
                'account.code',
                'account.type',
                'account.nature',
                'project.id',
                'project.code',
                'cost_center.id',
                'cost_center.code'
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
            // Same fix as the budget-lines query above: group by each joined
            // table's primary key instead of its translatable `json` name
            // column, which Postgres cannot compare for equality/grouping.
            ->groupBy(
                'ledger_entry.financial_period_id',
                'ledger_entry.account_id',
                'ledger_entry.project_id',
                'ledger_entry.cost_center_id',
                'ledger_entry.currency',
                'financial_period.id',
                'financial_period.month',
                'financial_period.start_date',
                'financial_period.end_date',
                'financial_period.fiscal_year_id',
                'account.id',
                'account.code',
                'account.type',
                'account.nature',
                'project.id',
                'project.code',
                'cost_center.id',
                'cost_center.code'
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

    /**
     * Build the bounded page payload. Summary values are aggregated from the
     * complete filtered SQL result and never from the current DataTables page.
     *
     * @return array<string, mixed>
     */
    public function metadata(
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
        $context = $this->queryContext(
            $budgetId,
            $fiscalYearId,
            $periodId,
            $fromDate,
            $toDate,
            $accountId,
            $projectId,
            $costCenterId,
            $currency,
        );

        if (! $context['budget']) {
            return [
                'selected_budget' => null,
                'filters' => $context['filters'],
                'periods' => [],
                'summary_by_currency' => [],
                'warning_codes' => $context['warning_codes'],
                'has_warnings' => count($context['warning_codes']) > 0,
            ];
        }

        $summaryByCurrency = $this->summaryByCurrency($context);
        $warningCodes = $context['warning_codes'];

        if (count($summaryByCurrency) > 1) {
            $warningCodes[] = 'mixed_currencies';
        }

        foreach ($summaryByCurrency as $summary) {
            if ($summary['actual_only_count'] > 0) {
                $warningCodes[] = 'unbudgeted_actuals_present';
            }
            if ($summary['budget_only_count'] > 0) {
                $warningCodes[] = 'budget_lines_without_actuals_present';
            }
        }

        $warningCodes = array_values(array_unique($warningCodes));

        return [
            'selected_budget' => $context['selected_budget'],
            'filters' => $context['filters'],
            'periods' => $context['periods'],
            'summary_by_currency' => $summaryByCurrency,
            'warning_codes' => $warningCodes,
            'has_warnings' => count($warningCodes) > 0,
        ];
    }

    public function datatable(
        ?string $budgetId = null,
        ?string $fiscalYearId = null,
        ?string $periodId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $accountId = null,
        ?string $projectId = null,
        ?string $costCenterId = null,
        ?string $currency = null,
    ): JsonResponse {
        $context = $this->queryContext(
            $budgetId,
            $fiscalYearId,
            $periodId,
            $fromDate,
            $toDate,
            $accountId,
            $projectId,
            $costCenterId,
            $currency,
        );

        if (! $context['budget']) {
            return DataTables::collection(collect())->toJson();
        }

        $query = DB::query()
            ->fromSub($this->varianceRowsQuery($context), 'budget_variance')
            ->select('budget_variance.*');

        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $pattern = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($pattern): void {
                    $nested->whereRaw('CAST(budget_variance.period_month AS TEXT) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(budget_variance.account_code) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(CAST(budget_variance.account_name AS TEXT)) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(budget_variance.account_type) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(budget_variance.account_nature) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(budget_variance.project_code, \'\')) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(CAST(COALESCE(budget_variance.project_name, \'\') AS TEXT)) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(budget_variance.cost_center_code, \'\')) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(CAST(COALESCE(budget_variance.cost_center_name, \'\') AS TEXT)) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(budget_variance.currency) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(budget_variance.row_type) LIKE ?', [$pattern]);
                });
            })
            ->order(function (Builder $builder): void {
                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset(self::SORT_COLUMNS[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    $builder->orderBy(self::SORT_COLUMNS[$data], $direction);
                }

                $builder->orderBy('budget_variance.period_month')
                    ->orderBy('budget_variance.account_code')
                    ->orderBy('budget_variance.project_code')
                    ->orderBy('budget_variance.cost_center_code')
                    ->orderBy('budget_variance.currency');
            })
            ->editColumn('account_name', fn (stdClass $row): array|string => $this->decodeTranslations($row->account_name))
            ->editColumn('project_name', fn (stdClass $row): array|string|null => $this->decodeNullableTranslations($row->project_name))
            ->editColumn('cost_center_name', fn (stdClass $row): array|string|null => $this->decodeNullableTranslations($row->cost_center_name))
            // Yajra's default escape='*' HTML-escapes every column, including
            // recursing into these decoded {en, ar} translation arrays -
            // marking them raw prevents e.g. "Sales Returns & Allowances"
            // rendering as "Sales Returns &amp; Allowances". Safe here: the
            // frontend slot renderers (datatables.net-react) render these as
            // plain React text (no dangerouslySetInnerHTML anywhere in the
            // codebase), so React's own escaping still protects against XSS
            // regardless of this flag.
            ->rawColumns(['account_name', 'account_name.en', 'account_name.ar', 'project_name', 'project_name.en', 'project_name.ar', 'cost_center_name', 'cost_center_name.en', 'cost_center_name.ar'])
            ->editColumn('period_month', fn (stdClass $row): int => (int) $row->period_month)
            ->editColumn('budget_minor', fn (stdClass $row): int => (int) $row->budget_minor)
            ->editColumn('actual_minor', fn (stdClass $row): int => (int) $row->actual_minor)
            ->editColumn('debit_minor', fn (stdClass $row): int => (int) $row->debit_minor)
            ->editColumn('credit_minor', fn (stdClass $row): int => (int) $row->credit_minor)
            ->editColumn('ledger_row_count', fn (stdClass $row): int => (int) $row->ledger_row_count)
            ->editColumn('variance_minor', fn (stdClass $row): int => (int) $row->variance_minor)
            ->editColumn('variance_abs_minor', fn (stdClass $row): int => (int) $row->variance_abs_minor)
            ->editColumn('variance_percent_bps', fn (stdClass $row): ?int => $row->variance_percent_bps === null ? null : (int) $row->variance_percent_bps)
            ->toJson();
    }

    /** @return array<string, mixed> */
    private function queryContext(
        ?string $budgetId,
        ?string $fiscalYearId,
        ?string $periodId,
        ?string $fromDate,
        ?string $toDate,
        ?string $accountId,
        ?string $projectId,
        ?string $costCenterId,
        ?string $currency,
    ): array {
        $warningCodes = [];
        $budget = null;

        if ($budgetId !== null && $budgetId !== '') {
            $foundBudget = Budget::query()->with(['fiscalYear.periods'])->where('id', $budgetId)->first();
            if (! $foundBudget) {
                $warningCodes[] = 'no_active_budget';
            } elseif (! in_array($foundBudget->status, ['active', 'approved'], true)) {
                $warningCodes[] = 'budget_not_comparable';
            } else {
                $budget = $foundBudget;
            }
        } elseif ($fiscalYearId !== null && $fiscalYearId !== '') {
            $budget = Budget::query()
                ->with(['fiscalYear.periods'])
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('status', 'active')
                ->first();

            if (! $budget) {
                $warningCodes[] = 'no_active_budget';
            }
        } else {
            $budget = Budget::query()
                ->with(['fiscalYear.periods'])
                ->where('status', 'active')
                ->orderByDesc('activated_at')
                ->orderByDesc('created_at')
                ->first();

            if (! $budget) {
                $warningCodes[] = 'no_active_budget';
            }
        }

        $filters = [
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
                'budget' => null,
                'selected_budget' => null,
                'filters' => $filters,
                'periods' => [],
                'scoped_period_ids' => [],
                'effective_from_date' => null,
                'effective_to_date' => null,
                'account_id' => $accountId,
                'project_id' => $projectId,
                'cost_center_id' => $costCenterId,
                'currency' => $currency,
                'warning_codes' => array_values(array_unique($warningCodes)),
            ];
        }

        $allPeriods = $budget->fiscalYear?->periods->sortBy('month')->values() ?? collect();
        $scopedPeriods = collect();
        $effectiveFromDate = null;
        $effectiveToDate = null;

        if ($periodId !== null && $periodId !== '') {
            $period = FinancialPeriod::query()->where('id', $periodId)->first();
            if (! $period || (string) $period->fiscal_year_id !== (string) $budget->fiscal_year_id) {
                throw ValidationException::withMessages([
                    'period_id' => [__('The selected period does not belong to the selected budget fiscal year.')],
                ]);
            }

            $effectiveFromDate = $period->start_date ? Carbon::parse($period->start_date)->toDateString() : null;
            $effectiveToDate = $period->end_date ? Carbon::parse($period->end_date)->toDateString() : null;
            $scopedPeriods = collect([$period]);
        } elseif ($fromDate !== null || $toDate !== null) {
            $effectiveFromDate = $fromDate
                ? Carbon::parse($fromDate)->toDateString()
                : ($budget->fiscalYear?->start_date ? Carbon::parse($budget->fiscalYear->start_date)->toDateString() : '1900-01-01');
            $effectiveToDate = $toDate
                ? Carbon::parse($toDate)->toDateString()
                : ($budget->fiscalYear?->end_date ? Carbon::parse($budget->fiscalYear->end_date)->toDateString() : '2099-12-31');
            $scopedPeriods = $allPeriods->filter(function (FinancialPeriod $period) use ($effectiveFromDate, $effectiveToDate): bool {
                $periodStart = Carbon::parse($period->start_date)->toDateString();
                $periodEnd = Carbon::parse($period->end_date)->toDateString();

                return $periodStart <= $effectiveToDate && $periodEnd >= $effectiveFromDate;
            })->values();
        } else {
            $effectiveFromDate = $budget->fiscalYear?->start_date
                ? Carbon::parse($budget->fiscalYear->start_date)->toDateString()
                : null;
            $effectiveToDate = $budget->fiscalYear?->end_date
                ? Carbon::parse($budget->fiscalYear->end_date)->toDateString()
                : null;
            $scopedPeriods = $allPeriods;
        }

        return [
            'budget' => $budget,
            'selected_budget' => [
                'id' => (string) $budget->id,
                'code' => (string) $budget->code,
                'version_code' => (string) $budget->version_code,
                'name' => $budget->getTranslations('name'),
                'description' => $budget->description,
                'status' => (string) $budget->status,
                'default_currency' => (string) $budget->default_currency,
                'fiscal_year_id' => (string) $budget->fiscal_year_id,
                'fiscal_year' => $budget->fiscalYear?->year,
            ],
            'filters' => $filters,
            'periods' => $scopedPeriods->map(fn (FinancialPeriod $period): array => [
                'id' => (string) $period->id,
                'month' => (int) $period->month,
                'start_date' => $period->start_date ? Carbon::parse($period->start_date)->toDateString() : null,
                'end_date' => $period->end_date ? Carbon::parse($period->end_date)->toDateString() : null,
            ])->values()->all(),
            'scoped_period_ids' => $scopedPeriods->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            'effective_from_date' => $effectiveFromDate,
            'effective_to_date' => $effectiveToDate,
            'account_id' => $accountId,
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'currency' => $currency,
            'warning_codes' => array_values(array_unique($warningCodes)),
        ];
    }

    /** @param array<string, mixed> $context */
    private function varianceRowsQuery(array $context): Builder
    {
        /** @var Budget $budget */
        $budget = $context['budget'];
        $periodIds = $context['scoped_period_ids'];

        $budgetRows = DB::table('budget_line as variance_budget_line')
            ->join('financial_period as variance_budget_period', 'variance_budget_period.id', '=', 'variance_budget_line.financial_period_id')
            ->join('account as variance_budget_account', 'variance_budget_account.id', '=', 'variance_budget_line.account_id')
            ->leftJoin('project as variance_budget_project', 'variance_budget_project.id', '=', 'variance_budget_line.project_id')
            ->leftJoin('cost_center as variance_budget_cost_center', 'variance_budget_cost_center.id', '=', 'variance_budget_line.cost_center_id')
            ->where('variance_budget_line.budget_id', $budget->id)
            ->whereIn('variance_budget_line.financial_period_id', $periodIds)
            ->when($context['account_id'], fn (Builder $query, string $id) => $query->where('variance_budget_line.account_id', $id))
            ->when($context['project_id'], fn (Builder $query, string $id) => $query->where('variance_budget_line.project_id', $id))
            ->when($context['cost_center_id'], fn (Builder $query, string $id) => $query->where('variance_budget_line.cost_center_id', $id))
            ->when($context['currency'], fn (Builder $query, string $code) => $query->where('variance_budget_line.currency', $code))
            ->select([
                'variance_budget_line.financial_period_id',
                'variance_budget_line.account_id',
                'variance_budget_line.project_id',
                'variance_budget_line.cost_center_id',
                'variance_budget_line.currency',
                'variance_budget_period.month as period_month',
                'variance_budget_period.start_date as period_start_date',
                'variance_budget_period.end_date as period_end_date',
                'variance_budget_period.fiscal_year_id',
                'variance_budget_account.code as account_code',
                $this->groupableTextSelect('variance_budget_account.name', 'account_name'),
                'variance_budget_account.type as account_type',
                'variance_budget_account.nature as account_nature',
                'variance_budget_project.code as project_code',
                $this->groupableTextSelect('variance_budget_project.name', 'project_name'),
                'variance_budget_cost_center.code as cost_center_code',
                $this->groupableTextSelect('variance_budget_cost_center.name', 'cost_center_name'),
            ])
            ->selectRaw('1 AS has_budget')
            ->selectRaw('0 AS has_actual')
            ->selectRaw('COALESCE(SUM(variance_budget_line.amount_minor), 0) AS budget_minor')
            ->selectRaw('0 AS debit_minor')
            ->selectRaw('0 AS credit_minor')
            ->selectRaw('0 AS ledger_row_count')
            // `.name` columns are translatable `json`, which Postgres cannot
            // compare for GROUP BY equality (only `jsonb` supports that) -
            // cast to `::text` for grouping purposes only. The SELECT above
            // still returns the untouched json column value (Postgres/PDO
            // always serializes json to a string over the wire regardless),
            // so decoding it downstream is unaffected.
            ->groupBy([
                'variance_budget_line.financial_period_id',
                'variance_budget_line.account_id',
                'variance_budget_line.project_id',
                'variance_budget_line.cost_center_id',
                'variance_budget_line.currency',
                'variance_budget_period.month',
                'variance_budget_period.start_date',
                'variance_budget_period.end_date',
                'variance_budget_period.fiscal_year_id',
                'variance_budget_account.code',
                $this->groupableTextColumn('variance_budget_account.name'),
                'variance_budget_account.type',
                'variance_budget_account.nature',
                'variance_budget_project.code',
                $this->groupableTextColumn('variance_budget_project.name'),
                'variance_budget_cost_center.code',
                $this->groupableTextColumn('variance_budget_cost_center.name'),
            ]);

        $actualRows = DB::table('ledger_entry as variance_ledger_entry')
            ->join('journal_entry as variance_journal_entry', 'variance_journal_entry.id', '=', 'variance_ledger_entry.journal_entry_id')
            ->join('financial_period as variance_actual_period', 'variance_actual_period.id', '=', 'variance_ledger_entry.financial_period_id')
            ->join('account as variance_actual_account', 'variance_actual_account.id', '=', 'variance_ledger_entry.account_id')
            ->leftJoin('project as variance_actual_project', 'variance_actual_project.id', '=', 'variance_ledger_entry.project_id')
            ->leftJoin('cost_center as variance_actual_cost_center', 'variance_actual_cost_center.id', '=', 'variance_ledger_entry.cost_center_id')
            ->where('variance_journal_entry.status', 'posted')
            ->where('variance_actual_period.fiscal_year_id', $budget->fiscal_year_id)
            ->whereIn('variance_ledger_entry.financial_period_id', $periodIds)
            ->when($context['effective_from_date'], fn (Builder $query, string $date) => $query->where('variance_ledger_entry.entry_date', '>=', $date))
            ->when($context['effective_to_date'], fn (Builder $query, string $date) => $query->where('variance_ledger_entry.entry_date', '<=', $date))
            ->when($context['account_id'], fn (Builder $query, string $id) => $query->where('variance_ledger_entry.account_id', $id))
            ->when($context['project_id'], fn (Builder $query, string $id) => $query->where('variance_ledger_entry.project_id', $id))
            ->when($context['cost_center_id'], fn (Builder $query, string $id) => $query->where('variance_ledger_entry.cost_center_id', $id))
            ->when($context['currency'], fn (Builder $query, string $code) => $query->where('variance_ledger_entry.currency', $code))
            ->select([
                'variance_ledger_entry.financial_period_id',
                'variance_ledger_entry.account_id',
                'variance_ledger_entry.project_id',
                'variance_ledger_entry.cost_center_id',
                'variance_ledger_entry.currency',
                'variance_actual_period.month as period_month',
                'variance_actual_period.start_date as period_start_date',
                'variance_actual_period.end_date as period_end_date',
                'variance_actual_period.fiscal_year_id',
                'variance_actual_account.code as account_code',
                $this->groupableTextSelect('variance_actual_account.name', 'account_name'),
                'variance_actual_account.type as account_type',
                'variance_actual_account.nature as account_nature',
                'variance_actual_project.code as project_code',
                $this->groupableTextSelect('variance_actual_project.name', 'project_name'),
                'variance_actual_cost_center.code as cost_center_code',
                $this->groupableTextSelect('variance_actual_cost_center.name', 'cost_center_name'),
            ])
            ->selectRaw('0 AS has_budget')
            ->selectRaw('1 AS has_actual')
            ->selectRaw('0 AS budget_minor')
            ->selectRaw('COALESCE(SUM(variance_ledger_entry.debit_minor), 0) AS debit_minor')
            ->selectRaw('COALESCE(SUM(variance_ledger_entry.credit_minor), 0) AS credit_minor')
            ->selectRaw('COUNT(variance_ledger_entry.id) AS ledger_row_count')
            // See the matching comment on $budgetRows above: `.name` columns
            // are translatable `json` and must be cast to `::text` to be
            // usable in a Postgres GROUP BY.
            ->groupBy([
                'variance_ledger_entry.financial_period_id',
                'variance_ledger_entry.account_id',
                'variance_ledger_entry.project_id',
                'variance_ledger_entry.cost_center_id',
                'variance_ledger_entry.currency',
                'variance_actual_period.month',
                'variance_actual_period.start_date',
                'variance_actual_period.end_date',
                'variance_actual_period.fiscal_year_id',
                'variance_actual_account.code',
                $this->groupableTextColumn('variance_actual_account.name'),
                'variance_actual_account.type',
                'variance_actual_account.nature',
                'variance_actual_project.code',
                $this->groupableTextColumn('variance_actual_project.name'),
                'variance_actual_cost_center.code',
                $this->groupableTextColumn('variance_actual_cost_center.name'),
            ]);

        $union = $budgetRows->unionAll($actualRows);
        $dimensions = [
            'financial_period_id',
            'period_month',
            'period_start_date',
            'period_end_date',
            'fiscal_year_id',
            'account_id',
            'account_code',
            'account_name',
            'account_type',
            'account_nature',
            'project_id',
            'project_code',
            'project_name',
            'cost_center_id',
            'cost_center_code',
            'cost_center_name',
            'currency',
        ];
        $qualifiedDimensions = array_map(fn (string $column): string => "variance_source.{$column}", $dimensions);

        // `account_name`/`project_name`/`cost_center_name` are translatable
        // `json` columns carried through the UNION above. Postgres has no
        // equality operator for `json` (only `jsonb`), so grouping by them
        // directly fails, and unlike a direct table join there is no primary
        // key on this derived table for Postgres's functional-dependency
        // relaxation to key off. Cast to `::text` consistently in both the
        // SELECT and GROUP BY (aliased back to the original column name) -
        // Postgres/PDO already serializes json to a string for a non-native
        // client, so downstream json_decode() of the value is unaffected.
        $jsonDimensionColumns = ['account_name', 'project_name', 'cost_center_name'];
        $qualifiedDimensionSelects = array_map(
            fn (string $column) => in_array($column, $jsonDimensionColumns, true)
                ? $this->groupableTextSelect("variance_source.{$column}", $column)
                : "variance_source.{$column}",
            $dimensions
        );
        $qualifiedDimensionGroupBy = array_map(
            fn (string $column) => in_array($column, $jsonDimensionColumns, true)
                ? $this->groupableTextColumn("variance_source.{$column}")
                : "variance_source.{$column}",
            $dimensions
        );

        $merged = DB::query()
            ->fromSub($union, 'variance_source')
            ->select($qualifiedDimensionSelects)
            ->selectRaw('MAX(variance_source.has_budget) AS has_budget')
            ->selectRaw('MAX(variance_source.has_actual) AS has_actual')
            ->selectRaw('COALESCE(SUM(variance_source.budget_minor), 0) AS budget_minor')
            ->selectRaw('COALESCE(SUM(variance_source.debit_minor), 0) AS debit_minor')
            ->selectRaw('COALESCE(SUM(variance_source.credit_minor), 0) AS credit_minor')
            ->selectRaw('COALESCE(SUM(variance_source.ledger_row_count), 0) AS ledger_row_count')
            ->groupBy($qualifiedDimensionGroupBy);

        $actualExpression = '(CASE WHEN variance_merged.account_nature = \'credit\' THEN variance_merged.credit_minor - variance_merged.debit_minor ELSE variance_merged.debit_minor - variance_merged.credit_minor END)';
        $varianceExpression = "({$actualExpression} - variance_merged.budget_minor)";

        return DB::query()
            ->fromSub($merged, 'variance_merged')
            ->select(array_map(fn (string $column): string => "variance_merged.{$column}", $dimensions))
            ->addSelect([
                'variance_merged.budget_minor',
                'variance_merged.debit_minor',
                'variance_merged.credit_minor',
                'variance_merged.ledger_row_count',
            ])
            ->selectRaw("{$actualExpression} AS actual_minor")
            ->selectRaw("{$varianceExpression} AS variance_minor")
            ->selectRaw("ABS({$varianceExpression}) AS variance_abs_minor")
            ->selectRaw("CASE WHEN variance_merged.budget_minor > 0 THEN CAST((ABS({$varianceExpression}) * 20000 + variance_merged.budget_minor) / (variance_merged.budget_minor * 2) AS INTEGER) ELSE NULL END AS variance_percent_bps")
            ->selectRaw("CASE WHEN variance_merged.has_budget = 1 AND variance_merged.has_actual = 0 THEN 'budget_only' WHEN variance_merged.has_budget = 0 AND variance_merged.has_actual = 1 THEN 'actual_only' ELSE 'matched' END AS row_type");
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, array<string, int|string|null>>
     */
    private function summaryByCurrency(array $context): array
    {
        $rows = DB::query()
            ->fromSub($this->varianceRowsQuery($context), 'variance_summary')
            ->select('variance_summary.currency')
            ->selectRaw('COALESCE(SUM(variance_summary.budget_minor), 0) AS budget_minor')
            ->selectRaw('COALESCE(SUM(variance_summary.actual_minor), 0) AS actual_minor')
            ->selectRaw('COALESCE(SUM(variance_summary.debit_minor), 0) AS debit_minor')
            ->selectRaw('COALESCE(SUM(variance_summary.credit_minor), 0) AS credit_minor')
            ->selectRaw('COALESCE(SUM(variance_summary.variance_minor), 0) AS variance_minor')
            ->selectRaw('COUNT(*) AS row_count')
            ->selectRaw("SUM(CASE WHEN variance_summary.row_type = 'matched' THEN 1 ELSE 0 END) AS matched_count")
            ->selectRaw("SUM(CASE WHEN variance_summary.row_type = 'budget_only' THEN 1 ELSE 0 END) AS budget_only_count")
            ->selectRaw("SUM(CASE WHEN variance_summary.row_type = 'actual_only' THEN 1 ELSE 0 END) AS actual_only_count")
            ->groupBy('variance_summary.currency')
            ->orderBy('variance_summary.currency')
            ->get();

        if ($rows->isEmpty()) {
            /** @var Budget $budget */
            $budget = $context['budget'];
            $fallbackCurrency = $context['currency'] ?? $budget->default_currency ?? $this->currencyResolver->resolve();
            $rows = collect([(object) [
                'currency' => $fallbackCurrency,
                'budget_minor' => 0,
                'actual_minor' => 0,
                'debit_minor' => 0,
                'credit_minor' => 0,
                'variance_minor' => 0,
                'row_count' => 0,
                'matched_count' => 0,
                'budget_only_count' => 0,
                'actual_only_count' => 0,
            ]]);
        }

        $summary = [];
        foreach ($rows as $row) {
            $currency = (string) $row->currency;
            $budgetMinor = (int) $row->budget_minor;
            $varianceMinor = (int) $row->variance_minor;
            $varianceAbsMinor = abs($varianceMinor);

            $summary[$currency] = [
                'currency' => $currency,
                'budget_minor' => $budgetMinor,
                'actual_minor' => (int) $row->actual_minor,
                'debit_minor' => (int) $row->debit_minor,
                'credit_minor' => (int) $row->credit_minor,
                'variance_minor' => $varianceMinor,
                'variance_abs_minor' => $varianceAbsMinor,
                'variance_percent_bps' => $budgetMinor > 0
                    ? (int) intdiv($varianceAbsMinor * 20000 + $budgetMinor, $budgetMinor * 2)
                    : null,
                'row_count' => (int) $row->row_count,
                'matched_count' => (int) $row->matched_count,
                'budget_only_count' => (int) $row->budget_only_count,
                'actual_only_count' => (int) $row->actual_only_count,
            ];
        }

        return $summary;
    }

    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    private function decodeNullableTranslations(mixed $value): array|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decodeTranslations($value);
    }
}
