<?php

namespace App\Application\Reports;

use App\Models\FinancialPeriod;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectProfitabilityReportService
{
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
