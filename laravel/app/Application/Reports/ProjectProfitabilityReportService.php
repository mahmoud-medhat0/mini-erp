<?php

namespace App\Application\Reports;

use App\Models\FinancialPeriod;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;

class ProjectProfitabilityReportService
{
    private const SORT_COLUMNS = [
        'project_code' => 'project_profitability.project_code',
        'project_name' => 'project_profitability.project_name',
        'project_status' => 'project_profitability.project_status',
        'currency' => 'project_profitability.currency',
        'ledger_row_count' => 'project_profitability.ledger_row_count',
        'revenue_minor' => 'project_profitability.revenue_minor',
        'contra_revenue_minor' => 'project_profitability.contra_revenue_minor',
        'net_revenue_minor' => 'project_profitability.net_revenue_minor',
        'cogs_minor' => 'project_profitability.cogs_minor',
        'gross_profit_minor' => 'project_profitability.gross_profit_minor',
        'operating_expense_minor' => 'project_profitability.operating_expense_minor',
        'operating_income_minor' => 'project_profitability.operating_income_minor',
        'other_income_minor' => 'project_profitability.other_income_minor',
        'other_expense_minor' => 'project_profitability.other_expense_minor',
        'net_income_minor' => 'project_profitability.net_income_minor',
        'profit_margin_bps' => 'project_profitability.profit_margin_bps',
    ];

    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(
        ?string $projectId = null,
        ?string $costCenterId = null,
        ?string $accountId = null,
        ?string $currency = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $periodId = null,
    ): array {
        if ($periodId) {
            $period = FinancialPeriod::query()->where('id', $periodId)->first();
            if ($period) {
                $dateFrom = $period->start_date?->toDateString();
                $dateTo = $period->end_date?->toDateString();
            }
        }

        $fromDate = $dateFrom ? Carbon::parse($dateFrom)->toDateString() : Carbon::now()->startOfYear()->toDateString();
        $toDate = $dateTo ? Carbon::parse($dateTo)->toDateString() : Carbon::now()->toDateString();

        $ledgerRows = $this->queryLedgerRows(
            fromDate: $fromDate,
            toDate: $toDate,
            projectId: $projectId,
            costCenterId: $costCenterId,
            accountId: $accountId,
            currency: $currency,
        );

        $totalsByProjectCurrency = $this->aggregateByProjectAndCurrency($ledgerRows);

        $projectIds = $totalsByProjectCurrency
            ->keys()
            ->map(fn (string $key): string => explode('__', $key)[0])
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values();

        if ($projectId !== null && ! $projectIds->contains($projectId)) {
            $projectIds->push($projectId);
        }

        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'status', 'is_active'])
            ->keyBy(fn (Project $p): string => (string) $p->id);

        $rows = collect();

        // Populate rows for distinct projects & currencies
        foreach ($totalsByProjectCurrency as $key => $totals) {
            [$pId, $curr] = explode('__', $key);

            if ($pId !== '') {
                $project = $projects->get($pId);
                if ($project) {
                    $rows->push($this->formatRow(
                        projectId: (string) $project->id,
                        projectCode: (string) $project->code,
                        projectName: $project->getTranslations('name'),
                        projectStatus: (string) ($project->status ?? ($project->is_active ? 'active' : 'inactive')),
                        isUnassigned: false,
                        currency: $curr,
                        totals: $totals,
                    ));
                }
            } else {
                // Unassigned row for this currency
                if ($projectId === null && $this->totalsHaveMovement($totals)) {
                    $rows->push($this->formatRow(
                        projectId: null,
                        projectCode: 'UNASSIGNED',
                        projectName: null,
                        projectStatus: null,
                        isUnassigned: true,
                        currency: $curr,
                        totals: $totals,
                    ));
                }
            }
        }

        // If specific projectId requested with no movements, show empty row
        if ($projectId !== null && $rows->isEmpty()) {
            $project = $projects->get($projectId);
            if ($project) {
                $curr = $currency ?? $this->baseCurrency();
                $rows->push($this->formatRow(
                    projectId: (string) $project->id,
                    projectCode: (string) $project->code,
                    projectName: $project->getTranslations('name'),
                    projectStatus: (string) ($project->status ?? ($project->is_active ? 'active' : 'inactive')),
                    isUnassigned: false,
                    currency: $curr,
                    totals: $this->emptyTotals(),
                ));
            }
        }

        // Sort rows by project_code asc, then currency asc
        $sortedRows = $rows->sortBy([
            ['is_unassigned', 'asc'],
            ['project_code', 'asc'],
            ['currency', 'asc'],
        ])->values();

        $currencyCodes = $this->resolveCurrencyCodes($sortedRows, $currency);
        $summaryByCurrency = $this->summarizeByCurrency($sortedRows, $currencyCodes);

        $unassignedPnlRowCount = $ledgerRows->filter(fn ($r): bool => $r->project_id === null)->sum('ledger_row_count');

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'period_id' => $periodId,
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'account_id' => $accountId,
            'currency' => $currency,
            'base_currency' => $this->baseCurrency(),
            'currency_codes' => $currencyCodes,
            'has_mixed_currencies' => count($currencyCodes) > 1,
            'rows' => $sortedRows->all(),
            'summary_by_currency' => $summaryByCurrency,
            'readiness' => [
                'unassigned_pnl_row_count' => (int) $unassignedPnlRowCount,
                'has_unassigned_pnl' => (int) $unassignedPnlRowCount > 0,
            ],
        ];
    }

    /**
     * Return full-filter summaries without hydrating the potentially large row set.
     *
     * @return array<string, mixed>
     */
    public function metadata(
        ?string $projectId = null,
        ?string $costCenterId = null,
        ?string $accountId = null,
        ?string $currency = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $periodId = null,
    ): array {
        $context = $this->queryContext($projectId, $costCenterId, $accountId, $currency, $dateFrom, $dateTo, $periodId);
        $summaryRows = DB::query()
            ->fromSub($this->datatableRowsQuery($context), 'project_profitability')
            ->select('project_profitability.currency')
            ->selectRaw('COALESCE(SUM(project_profitability.ledger_row_count), 0) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(project_profitability.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.credit_minor), 0) as credit_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.revenue_minor), 0) as revenue_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.contra_revenue_minor), 0) as contra_revenue_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.cogs_minor), 0) as cogs_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.operating_expense_minor), 0) as operating_expense_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.other_income_minor), 0) as other_income_minor')
            ->selectRaw('COALESCE(SUM(project_profitability.other_expense_minor), 0) as other_expense_minor')
            ->groupBy('project_profitability.currency')
            ->orderBy('project_profitability.currency')
            ->get();

        $summaryByCurrency = [];
        foreach ($summaryRows as $row) {
            $code = (string) $row->currency;
            $totals = [
                'ledger_row_count' => (int) $row->ledger_row_count,
                'debit_minor' => (int) $row->debit_minor,
                'credit_minor' => (int) $row->credit_minor,
                'revenue_minor' => (int) $row->revenue_minor,
                'contra_revenue_minor' => (int) $row->contra_revenue_minor,
                'cogs_minor' => (int) $row->cogs_minor,
                'operating_expense_minor' => (int) $row->operating_expense_minor,
                'other_income_minor' => (int) $row->other_income_minor,
                'other_expense_minor' => (int) $row->other_expense_minor,
            ];
            $summaryByCurrency[$code] = ['currency' => $code, ...$this->deriveTotals($totals)];
        }

        $currencyCodes = array_keys($summaryByCurrency);
        if ($currencyCodes === []) {
            $currencyCodes = [$currency ?: $this->baseCurrency()];
            $summaryByCurrency[$currencyCodes[0]] = ['currency' => $currencyCodes[0], ...$this->deriveTotals($this->emptyPrimitiveTotals())];
        }

        $unassignedPnlRowCount = (int) DB::query()
            ->fromSub($this->datatableRowsQuery($context), 'project_profitability')
            ->where('project_profitability.is_unassigned', 1)
            ->sum('project_profitability.ledger_row_count');

        return [
            'from_date' => $context['from_date'],
            'to_date' => $context['to_date'],
            'period_id' => $periodId,
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'account_id' => $accountId,
            'currency' => $currency,
            'base_currency' => $this->baseCurrency(),
            'currency_codes' => $currencyCodes,
            'has_mixed_currencies' => count($currencyCodes) > 1,
            'summary_by_currency' => $summaryByCurrency,
            'readiness' => [
                'unassigned_pnl_row_count' => $unassignedPnlRowCount,
                'has_unassigned_pnl' => $unassignedPnlRowCount > 0,
            ],
        ];
    }

    public function datatable(
        ?string $projectId = null,
        ?string $costCenterId = null,
        ?string $accountId = null,
        ?string $currency = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $periodId = null,
    ): JsonResponse {
        $context = $this->queryContext($projectId, $costCenterId, $accountId, $currency, $dateFrom, $dateTo, $periodId);
        $query = DB::query()
            ->fromSub($this->datatableRowsQuery($context), 'project_profitability')
            ->select('project_profitability.*');

        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));
                if ($search === '') {
                    return;
                }

                $pattern = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($pattern): void {
                    $nested->whereRaw('LOWER(project_profitability.project_code) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(CAST(COALESCE(project_profitability.project_name, \'\') AS TEXT)) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(project_profitability.project_status, \'\')) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(project_profitability.currency) LIKE ?', [$pattern]);
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

                    $builder->orderBy(self::SORT_COLUMNS[$data], ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc');
                }

                $builder->orderBy('project_profitability.is_unassigned')
                    ->orderBy('project_profitability.project_code')
                    ->orderBy('project_profitability.currency');
            })
            ->editColumn('project_name', fn (stdClass $row): array|string|null => $this->decodeNullableTranslations($row->project_name))
            ->editColumn('is_unassigned', fn (stdClass $row): bool => (bool) $row->is_unassigned)
            ->editColumn('ledger_row_count', fn (stdClass $row): int => (int) $row->ledger_row_count)
            ->editColumn('debit_minor', fn (stdClass $row): int => (int) $row->debit_minor)
            ->editColumn('credit_minor', fn (stdClass $row): int => (int) $row->credit_minor)
            ->editColumn('revenue_minor', fn (stdClass $row): int => (int) $row->revenue_minor)
            ->editColumn('contra_revenue_minor', fn (stdClass $row): int => (int) $row->contra_revenue_minor)
            ->editColumn('net_revenue_minor', fn (stdClass $row): int => (int) $row->net_revenue_minor)
            ->editColumn('cogs_minor', fn (stdClass $row): int => (int) $row->cogs_minor)
            ->editColumn('gross_profit_minor', fn (stdClass $row): int => (int) $row->gross_profit_minor)
            ->editColumn('operating_expense_minor', fn (stdClass $row): int => (int) $row->operating_expense_minor)
            ->editColumn('operating_income_minor', fn (stdClass $row): int => (int) $row->operating_income_minor)
            ->editColumn('other_income_minor', fn (stdClass $row): int => (int) $row->other_income_minor)
            ->editColumn('other_expense_minor', fn (stdClass $row): int => (int) $row->other_expense_minor)
            ->editColumn('net_income_minor', fn (stdClass $row): int => (int) $row->net_income_minor)
            ->editColumn('profit_margin_bps', fn (stdClass $row): ?int => $row->profit_margin_bps === null ? null : (int) $row->profit_margin_bps)
            ->toJson();
    }

    /** @return array<string, mixed> */
    private function queryContext(
        ?string $projectId,
        ?string $costCenterId,
        ?string $accountId,
        ?string $currency,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $periodId,
    ): array {
        if ($periodId) {
            $period = FinancialPeriod::query()->where('id', $periodId)->first();
            if ($period) {
                $dateFrom = $period->start_date?->toDateString();
                $dateTo = $period->end_date?->toDateString();
            }
        }

        return [
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'account_id' => $accountId,
            'currency' => $currency,
            'from_date' => $dateFrom ? Carbon::parse($dateFrom)->toDateString() : Carbon::now()->startOfYear()->toDateString(),
            'to_date' => $dateTo ? Carbon::parse($dateTo)->toDateString() : Carbon::now()->toDateString(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function datatableRowsQuery(array $context): Builder
    {
        $recognizedSections = "'revenue', 'contra_revenue', 'cogs', 'operating_expenses', 'other_income', 'other_expenses'";
        $section = "CASE WHEN financial_statement_line.section_code IN ($recognizedSections) THEN financial_statement_line.section_code WHEN account.type = 'revenue' THEN 'revenue' WHEN account.type = 'contra_revenue' THEN 'contra_revenue' WHEN account.type = 'expense' THEN 'operating_expenses' ELSE NULL END";

        $movements = DB::table('ledger_entry')
            ->join('journal_entry', 'journal_entry.id', '=', 'ledger_entry.journal_entry_id')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->leftJoin('financial_statement_line', 'financial_statement_line.id', '=', 'account.financial_statement_line_id')
            ->leftJoin('project', 'project.id', '=', 'ledger_entry.project_id')
            ->selectRaw('ledger_entry.project_id as project_id')
            ->selectRaw("COALESCE(project.code, 'UNASSIGNED') as project_code")
            ->selectRaw('CAST(project.name AS TEXT) as project_name')
            ->selectRaw("CASE WHEN ledger_entry.project_id IS NULL THEN NULL ELSE COALESCE(project.status, CASE WHEN project.is_active THEN 'active' ELSE 'inactive' END) END as project_status")
            ->selectRaw('CASE WHEN ledger_entry.project_id IS NULL THEN 1 ELSE 0 END as is_unassigned')
            ->selectRaw('ledger_entry.currency as currency')
            ->selectRaw('COUNT(ledger_entry.id) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
            ->selectRaw("COALESCE(SUM(CASE WHEN ($section) = 'revenue' THEN ledger_entry.credit_minor - ledger_entry.debit_minor ELSE 0 END), 0) as revenue_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN ($section) = 'contra_revenue' THEN ledger_entry.debit_minor - ledger_entry.credit_minor ELSE 0 END), 0) as contra_revenue_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN ($section) = 'cogs' THEN ledger_entry.debit_minor - ledger_entry.credit_minor ELSE 0 END), 0) as cogs_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN ($section) = 'operating_expenses' THEN ledger_entry.debit_minor - ledger_entry.credit_minor ELSE 0 END), 0) as operating_expense_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN ($section) = 'other_income' THEN ledger_entry.credit_minor - ledger_entry.debit_minor ELSE 0 END), 0) as other_income_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN ($section) = 'other_expenses' THEN ledger_entry.debit_minor - ledger_entry.credit_minor ELSE 0 END), 0) as other_expense_minor")
            ->where('journal_entry.status', 'posted')
            ->whereBetween('ledger_entry.entry_date', [$context['from_date'], $context['to_date']])
            ->where(function (Builder $builder) use ($recognizedSections): void {
                $builder->whereIn('account.type', ['revenue', 'expense', 'contra_revenue'])
                    ->orWhereRaw("financial_statement_line.section_code IN ($recognizedSections)");
            })
            ->when($context['project_id'], fn (Builder $builder) => $builder->where('ledger_entry.project_id', $context['project_id']))
            ->when($context['cost_center_id'], fn (Builder $builder) => $builder->where('ledger_entry.cost_center_id', $context['cost_center_id']))
            ->when($context['account_id'], fn (Builder $builder) => $builder->where('ledger_entry.account_id', $context['account_id']))
            ->when($context['currency'], fn (Builder $builder) => $builder->where('ledger_entry.currency', $context['currency']))
            ->groupBy(
                'ledger_entry.project_id',
                'project.code',
                'project.status',
                'project.is_active',
                'ledger_entry.currency',
            )
            ->groupByRaw('CAST(project.name AS TEXT)');

        $primitiveRows = $movements;
        if ($context['project_id']) {
            $emptyCurrency = $context['currency'] ?: $this->baseCurrency();
            $emptySelection = DB::table('project')
                ->selectRaw('project.id as project_id, project.code as project_code, CAST(project.name AS TEXT) as project_name')
                ->selectRaw("COALESCE(project.status, CASE WHEN project.is_active THEN 'active' ELSE 'inactive' END) as project_status")
                ->selectRaw('0 as is_unassigned')
                ->selectRaw('? as currency', [$emptyCurrency])
                ->selectRaw('0 as ledger_row_count, 0 as debit_minor, 0 as credit_minor')
                ->selectRaw('0 as revenue_minor, 0 as contra_revenue_minor, 0 as cogs_minor')
                ->selectRaw('0 as operating_expense_minor, 0 as other_income_minor, 0 as other_expense_minor')
                ->where('project.id', $context['project_id'])
                ->whereNotExists(function (Builder $builder) use ($context, $recognizedSections): void {
                    $builder->selectRaw('1')
                        ->from('ledger_entry as selected_ledger_entry')
                        ->join('journal_entry as selected_journal_entry', 'selected_journal_entry.id', '=', 'selected_ledger_entry.journal_entry_id')
                        ->join('account as selected_account', 'selected_account.id', '=', 'selected_ledger_entry.account_id')
                        ->leftJoin('financial_statement_line as selected_statement_line', 'selected_statement_line.id', '=', 'selected_account.financial_statement_line_id')
                        ->where('selected_journal_entry.status', 'posted')
                        ->whereBetween('selected_ledger_entry.entry_date', [$context['from_date'], $context['to_date']])
                        ->where('selected_ledger_entry.project_id', $context['project_id'])
                        ->where(function (Builder $eligible) use ($recognizedSections): void {
                            $eligible->whereIn('selected_account.type', ['revenue', 'expense', 'contra_revenue'])
                                ->orWhereRaw("selected_statement_line.section_code IN ($recognizedSections)");
                        })
                        ->when($context['cost_center_id'], fn (Builder $query) => $query->where('selected_ledger_entry.cost_center_id', $context['cost_center_id']))
                        ->when($context['account_id'], fn (Builder $query) => $query->where('selected_ledger_entry.account_id', $context['account_id']))
                        ->when($context['currency'], fn (Builder $query) => $query->where('selected_ledger_entry.currency', $context['currency']));
                });

            $primitiveRows = DB::query()
                ->fromSub($movements->unionAll($emptySelection), 'project_profitability_source')
                ->select('project_id', 'project_code', 'project_name', 'project_status', 'is_unassigned', 'currency')
                ->selectRaw('SUM(ledger_row_count) as ledger_row_count')
                ->selectRaw('SUM(debit_minor) as debit_minor')
                ->selectRaw('SUM(credit_minor) as credit_minor')
                ->selectRaw('SUM(revenue_minor) as revenue_minor')
                ->selectRaw('SUM(contra_revenue_minor) as contra_revenue_minor')
                ->selectRaw('SUM(cogs_minor) as cogs_minor')
                ->selectRaw('SUM(operating_expense_minor) as operating_expense_minor')
                ->selectRaw('SUM(other_income_minor) as other_income_minor')
                ->selectRaw('SUM(other_expense_minor) as other_expense_minor')
                ->groupBy('project_id', 'project_code', 'project_name', 'project_status', 'is_unassigned', 'currency');
        }

        $derived = DB::query()
            ->fromSub($primitiveRows, 'project_profitability_primitive')
            ->select('project_profitability_primitive.*')
            ->selectRaw('(revenue_minor - contra_revenue_minor) as net_revenue_minor')
            ->selectRaw('(revenue_minor - contra_revenue_minor - cogs_minor) as gross_profit_minor')
            ->selectRaw('(revenue_minor - contra_revenue_minor - cogs_minor - operating_expense_minor) as operating_income_minor')
            ->selectRaw('(revenue_minor - contra_revenue_minor - cogs_minor - operating_expense_minor + other_income_minor - other_expense_minor) as net_income_minor');

        return DB::query()
            ->fromSub($derived, 'project_profitability_derived')
            ->select('project_profitability_derived.*')
            ->selectRaw('CASE WHEN net_revenue_minor = 0 THEN NULL ELSE CAST((net_income_minor * 10000) / ABS(net_revenue_minor) AS INTEGER) END as profit_margin_bps');
    }

    /** @param array<string, int> $totals */
    private function deriveTotals(array $totals): array
    {
        $totals['net_revenue_minor'] = $totals['revenue_minor'] - $totals['contra_revenue_minor'];
        $totals['gross_profit_minor'] = $totals['net_revenue_minor'] - $totals['cogs_minor'];
        $totals['operating_income_minor'] = $totals['gross_profit_minor'] - $totals['operating_expense_minor'];
        $totals['net_income_minor'] = $totals['operating_income_minor'] + $totals['other_income_minor'] - $totals['other_expense_minor'];
        $totals['profit_margin_bps'] = $totals['net_revenue_minor'] !== 0
            ? intdiv($totals['net_income_minor'] * 10000, abs($totals['net_revenue_minor']))
            : null;

        return $totals;
    }

    /** @return array<string, int> */
    private function emptyPrimitiveTotals(): array
    {
        return [
            'ledger_row_count' => 0,
            'debit_minor' => 0,
            'credit_minor' => 0,
            'revenue_minor' => 0,
            'contra_revenue_minor' => 0,
            'cogs_minor' => 0,
            'operating_expense_minor' => 0,
            'other_income_minor' => 0,
            'other_expense_minor' => 0,
        ];
    }

    private function decodeNullableTranslations(mixed $value): array|string|null
    {
        if (! is_string($value) || ! str_starts_with($value, '{')) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    private function queryLedgerRows(
        string $fromDate,
        string $toDate,
        ?string $projectId,
        ?string $costCenterId,
        ?string $accountId,
        ?string $currency,
    ): Collection {
        return DB::table('ledger_entry')
            ->join('journal_entry', 'journal_entry.id', '=', 'ledger_entry.journal_entry_id')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->leftJoin('financial_statement_line', 'financial_statement_line.id', '=', 'account.financial_statement_line_id')
            ->select('ledger_entry.project_id', 'ledger_entry.currency')
            ->selectRaw('account.id as account_id')
            ->selectRaw('account.type as account_type')
            ->selectRaw('account.nature as account_nature')
            ->selectRaw('financial_statement_line.section_code as section_code')
            ->selectRaw('COUNT(ledger_entry.id) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
            ->where('journal_entry.status', '=', 'posted')
            ->where('ledger_entry.entry_date', '>=', $fromDate)
            ->where('ledger_entry.entry_date', '<=', $toDate)
            ->where(function ($query): void {
                $query->whereIn('account.type', ['revenue', 'expense', 'contra_revenue'])
                    ->orWhereIn('financial_statement_line.section_code', [
                        'revenue',
                        'contra_revenue',
                        'cogs',
                        'operating_expenses',
                        'other_income',
                        'other_expenses',
                    ]);
            })
            ->when($projectId, fn ($query) => $query->where('ledger_entry.project_id', $projectId))
            ->when($costCenterId, fn ($query) => $query->where('ledger_entry.cost_center_id', $costCenterId))
            ->when($accountId, fn ($query) => $query->where('ledger_entry.account_id', $accountId))
            ->when($currency, fn ($query) => $query->where('ledger_entry.currency', $currency))
            ->groupBy(
                'ledger_entry.project_id',
                'ledger_entry.currency',
                'account.id',
                'account.type',
                'account.nature',
                'financial_statement_line.section_code',
            )
            ->get();
    }

    private function aggregateByProjectAndCurrency(Collection $ledgerRows): Collection
    {
        $totals = collect();

        foreach ($ledgerRows as $row) {
            $key = ($row->project_id ?? '').'__'.$row->currency;
            $current = $totals->get($key, $this->emptyTotals());
            $section = $this->sectionFor((string) ($row->section_code ?? ''), (string) $row->account_type);
            $debit = (int) $row->debit_minor;
            $credit = (int) $row->credit_minor;
            $count = (int) $row->ledger_row_count;

            match ($section) {
                'revenue' => $current['revenue_minor'] += $credit - $debit,
                'contra_revenue' => $current['contra_revenue_minor'] += $debit - $credit,
                'cogs' => $current['cogs_minor'] += $debit - $credit,
                'operating_expenses' => $current['operating_expense_minor'] += $debit - $credit,
                'other_income' => $current['other_income_minor'] += $credit - $debit,
                'other_expenses' => $current['other_expense_minor'] += $debit - $credit,
                default => null,
            };

            $current['ledger_row_count'] += $count;
            $current['debit_minor'] += $debit;
            $current['credit_minor'] += $credit;
            $current['net_revenue_minor'] = $current['revenue_minor'] - $current['contra_revenue_minor'];
            $current['gross_profit_minor'] = $current['net_revenue_minor'] - $current['cogs_minor'];
            $current['operating_income_minor'] = $current['gross_profit_minor'] - $current['operating_expense_minor'];
            $current['net_income_minor'] = $current['operating_income_minor'] + $current['other_income_minor'] - $current['other_expense_minor'];

            $totals->put($key, $current);
        }

        return $totals;
    }

    private function sectionFor(string $sectionCode, string $accountType): ?string
    {
        if (in_array($sectionCode, ['revenue', 'contra_revenue', 'cogs', 'operating_expenses', 'other_income', 'other_expenses'], true)) {
            return $sectionCode;
        }

        return match ($accountType) {
            'revenue' => 'revenue',
            'contra_revenue' => 'contra_revenue',
            'expense' => 'operating_expenses',
            default => null,
        };
    }

    private function formatRow(
        ?string $projectId,
        string $projectCode,
        mixed $projectName,
        ?string $projectStatus,
        bool $isUnassigned,
        string $currency,
        array $totals,
    ): array {
        return [
            'project_id' => $projectId,
            'project_code' => $projectCode,
            'project_name' => $projectName,
            'project_status' => $projectStatus,
            'is_unassigned' => $isUnassigned,
            'currency' => $currency,
            ...$totals,
            'profit_margin_bps' => $totals['net_revenue_minor'] !== 0
                ? intdiv($totals['net_income_minor'] * 10000, abs($totals['net_revenue_minor']))
                : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $currencyCodes
     * @return array<string, array<string, mixed>>
     */
    private function summarizeByCurrency(Collection $rows, array $currencyCodes): array
    {
        $summary = [];

        foreach ($currencyCodes as $code) {
            $summary[$code] = [
                'currency' => $code,
                ...$this->emptyTotals(),
                'profit_margin_bps' => null,
            ];
        }

        foreach ($rows as $row) {
            $curr = (string) $row['currency'];
            if (! isset($summary[$curr])) {
                $summary[$curr] = [
                    'currency' => $curr,
                    ...$this->emptyTotals(),
                    'profit_margin_bps' => null,
                ];
            }

            foreach (array_keys($this->emptyTotals()) as $key) {
                $summary[$curr][$key] += (int) ($row[$key] ?? 0);
            }
        }

        foreach ($summary as $code => $totals) {
            $summary[$code]['net_revenue_minor'] = $totals['revenue_minor'] - $totals['contra_revenue_minor'];
            $summary[$code]['gross_profit_minor'] = $summary[$code]['net_revenue_minor'] - $totals['cogs_minor'];
            $summary[$code]['operating_income_minor'] = $summary[$code]['gross_profit_minor'] - $totals['operating_expense_minor'];
            $summary[$code]['net_income_minor'] = $summary[$code]['operating_income_minor'] + $totals['other_income_minor'] - $totals['other_expense_minor'];
            $summary[$code]['profit_margin_bps'] = $summary[$code]['net_revenue_minor'] !== 0
                ? intdiv($summary[$code]['net_income_minor'] * 10000, abs($summary[$code]['net_revenue_minor']))
                : null;
        }

        return $summary;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<string>
     */
    private function resolveCurrencyCodes(Collection $rows, ?string $currencyFilter): array
    {
        if ($currencyFilter !== null && $currencyFilter !== '') {
            return [$currencyFilter];
        }

        $codes = $rows->pluck('currency')->unique()->sort()->values()->all();

        if (empty($codes)) {
            return [$this->baseCurrency()];
        }

        return $codes;
    }

    private function totalsHaveMovement(array $totals): bool
    {
        return (int) $totals['ledger_row_count'] > 0;
    }

    private function baseCurrency(): string
    {
        return $this->currencyResolver->resolve();
    }

    private function emptyTotals(): array
    {
        return [
            'ledger_row_count' => 0,
            'debit_minor' => 0,
            'credit_minor' => 0,
            'revenue_minor' => 0,
            'contra_revenue_minor' => 0,
            'net_revenue_minor' => 0,
            'cogs_minor' => 0,
            'gross_profit_minor' => 0,
            'operating_expense_minor' => 0,
            'operating_income_minor' => 0,
            'other_income_minor' => 0,
            'other_expense_minor' => 0,
            'net_income_minor' => 0,
        ];
    }
}
