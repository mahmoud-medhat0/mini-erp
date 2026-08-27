<?php

namespace App\Application\Reports;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchProfitabilityReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(?string $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $fromDate = $dateFrom ? Carbon::parse($dateFrom)->toDateString() : Carbon::now()->startOfYear()->toDateString();
        $toDate = $dateTo ? Carbon::parse($dateTo)->toDateString() : Carbon::now()->toDateString();

        $ledgerRows = $this->ledgerRows($fromDate, $toDate, $branchId);
        $totalsByBranch = $this->totalsByBranch($ledgerRows);

        $branches = Branch::query()
            ->when($branchId, fn ($query) => $query->where('id', $branchId))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active']);

        $rows = $branches
            ->map(function (Branch $branch) use ($totalsByBranch): array {
                $totals = $totalsByBranch->get((string) $branch->id, $this->emptyTotals());

                return $this->formatRow(
                    branchId: (string) $branch->id,
                    branchCode: (string) $branch->code,
                    branchName: $branch->name,
                    isActive: (bool) $branch->is_active,
                    isUnassigned: false,
                    totals: $totals,
                );
            })
            ->filter(fn (array $row): bool => $branchId !== null || $this->rowHasMovement($row))
            ->values();

        $unassignedTotals = $totalsByBranch->get('', $this->emptyTotals());
        if ($branchId === null && $this->totalsHaveMovement($unassignedTotals)) {
            $rows->push($this->formatRow(
                branchId: null,
                branchCode: 'UNASSIGNED',
                branchName: null,
                isActive: true,
                isUnassigned: true,
                totals: $unassignedTotals,
            ));
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
            'base_currency' => $this->baseCurrency(),
            'currency_codes' => $this->currencyCodes($fromDate, $toDate, $branchId),
            'rows' => $rows->all(),
            'summary' => $this->summary($rows),
            'readiness' => [
                'branch_dimension_status' => 'enabled_optional_gl_dimension',
                'unassigned_pnl_row_count' => $this->unassignedPnlRowCount($ledgerRows),
                'unassigned_net_income_minor' => (int) $unassignedTotals['net_income_minor'],
                'has_unassigned_pnl' => $this->totalsHaveMovement($unassignedTotals),
            ],
        ];
    }

    private function ledgerRows(string $fromDate, string $toDate, ?string $branchId): Collection
    {
        return DB::table('ledger_entry')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->leftJoin('financial_statement_line', 'financial_statement_line.id', '=', 'account.financial_statement_line_id')
            ->select('ledger_entry.branch_id')
            ->selectRaw('account.id as account_id')
            ->selectRaw('account.type as account_type')
            ->selectRaw('account.nature as account_nature')
            ->selectRaw('financial_statement_line.section_code as section_code')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
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
            ->when($branchId, fn ($query) => $query->where('ledger_entry.branch_id', $branchId))
            ->groupBy(
                'ledger_entry.branch_id',
                'account.id',
                'account.type',
                'account.nature',
                'financial_statement_line.section_code',
            )
            ->get();
    }

    private function totalsByBranch(Collection $ledgerRows): Collection
    {
        $totals = collect();

        foreach ($ledgerRows as $row) {
            $branchKey = (string) ($row->branch_id ?? '');
            $current = $totals->get($branchKey, $this->emptyTotals());
            $section = $this->sectionFor((string) ($row->section_code ?? ''), (string) $row->account_type);
            $debit = (int) $row->debit_minor;
            $credit = (int) $row->credit_minor;

            match ($section) {
                'revenue' => $current['revenue_minor'] += $credit - $debit,
                'contra_revenue' => $current['contra_revenue_minor'] += $debit - $credit,
                'cogs' => $current['cogs_minor'] += $debit - $credit,
                'operating_expenses' => $current['operating_expense_minor'] += $debit - $credit,
                'other_income' => $current['other_income_minor'] += $credit - $debit,
                'other_expenses' => $current['other_expense_minor'] += $debit - $credit,
                default => null,
            };

            $current['ledger_row_count']++;
            $current['debit_minor'] += $debit;
            $current['credit_minor'] += $credit;
            $current['net_revenue_minor'] = $current['revenue_minor'] - $current['contra_revenue_minor'];
            $current['gross_profit_minor'] = $current['net_revenue_minor'] - $current['cogs_minor'];
            $current['operating_income_minor'] = $current['gross_profit_minor'] - $current['operating_expense_minor'];
            $current['net_income_minor'] = $current['operating_income_minor'] + $current['other_income_minor'] - $current['other_expense_minor'];

            $totals->put($branchKey, $current);
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
        ?string $branchId,
        string $branchCode,
        mixed $branchName,
        bool $isActive,
        bool $isUnassigned,
        array $totals,
    ): array {
        return [
            'branch_id' => $branchId,
            'branch_code' => $branchCode,
            'branch_name' => $branchName,
            'is_active' => $isActive,
            'is_unassigned' => $isUnassigned,
            ...$totals,
            'profit_margin_bps' => $totals['net_revenue_minor'] !== 0
                ? intdiv($totals['net_income_minor'] * 10000, abs($totals['net_revenue_minor']))
                : null,
        ];
    }

    private function summary(Collection $rows): array
    {
        $summary = $this->emptyTotals();
        foreach ($rows as $row) {
            foreach (array_keys($summary) as $key) {
                $summary[$key] += (int) ($row[$key] ?? 0);
            }
        }

        return $summary;
    }

    private function rowHasMovement(array $row): bool
    {
        return (int) $row['ledger_row_count'] > 0;
    }

    private function totalsHaveMovement(array $totals): bool
    {
        return (int) $totals['ledger_row_count'] > 0;
    }

    private function unassignedPnlRowCount(Collection $ledgerRows): int
    {
        return $ledgerRows->filter(fn ($row): bool => $row->branch_id === null)->count();
    }

    private function currencyCodes(string $fromDate, string $toDate, ?string $branchId): array
    {
        return DB::table('ledger_entry')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->leftJoin('financial_statement_line', 'financial_statement_line.id', '=', 'account.financial_statement_line_id')
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
            ->when($branchId, fn ($query) => $query->where('ledger_entry.branch_id', $branchId))
            ->distinct()
            ->orderBy('ledger_entry.currency')
            ->pluck('ledger_entry.currency')
            ->map(fn ($currency): string => (string) $currency)
            ->values()
            ->all();
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
