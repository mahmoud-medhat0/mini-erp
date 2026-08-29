<?php

namespace App\Application\Reports;

use App\Models\CostCenter;
use App\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CostCenterActualsReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(
        ?string $costCenterId = null,
        ?string $projectId = null,
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
            costCenterId: $costCenterId,
            projectId: $projectId,
            accountId: $accountId,
            currency: $currency,
        );

        $groupedByCostCenterCurrency = $this->aggregateByCostCenterAndCurrency($ledgerRows);

        $costCenterIds = $groupedByCostCenterCurrency
            ->keys()
            ->map(fn (string $key): string => explode('__', $key)[0])
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values();

        if ($costCenterId !== null && ! $costCenterIds->contains($costCenterId)) {
            $costCenterIds->push($costCenterId);
        }

        $costCenters = CostCenter::query()
            ->whereIn('id', $costCenterIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category', 'is_active'])
            ->keyBy(fn (CostCenter $cc): string => (string) $cc->id);

        $rows = collect();

        foreach ($groupedByCostCenterCurrency as $key => $group) {
            [$ccId, $curr] = explode('__', $key);

            if ($ccId !== '') {
                $costCenter = $costCenters->get($ccId);
                if ($costCenter) {
                    $rows->push([
                        'cost_center_id' => (string) $costCenter->id,
                        'cost_center_code' => (string) $costCenter->code,
                        'cost_center_name' => $costCenter->getTranslations('name'),
                        'cost_center_status' => $costCenter->is_active ? 'active' : 'inactive',
                        'is_unassigned' => false,
                        'currency' => $curr,
                        'ledger_row_count' => $group['ledger_row_count'],
                        'debit_minor' => $group['debit_minor'],
                        'credit_minor' => $group['credit_minor'],
                        'net_minor' => $group['net_minor'],
                        'accounts' => $group['accounts'],
                    ]);
                }
            } else {
                if ($costCenterId === null && $group['ledger_row_count'] > 0) {
                    $rows->push([
                        'cost_center_id' => null,
                        'cost_center_code' => 'UNASSIGNED',
                        'cost_center_name' => null,
                        'cost_center_status' => null,
                        'is_unassigned' => true,
                        'currency' => $curr,
                        'ledger_row_count' => $group['ledger_row_count'],
                        'debit_minor' => $group['debit_minor'],
                        'credit_minor' => $group['credit_minor'],
                        'net_minor' => $group['net_minor'],
                        'accounts' => $group['accounts'],
                    ]);
                }
            }
        }

        // If specific costCenterId requested with no movements, show empty row
        if ($costCenterId !== null && $rows->isEmpty()) {
            $costCenter = $costCenters->get($costCenterId);
            if ($costCenter) {
                $curr = $currency ?? $this->baseCurrency();
                $rows->push([
                    'cost_center_id' => (string) $costCenter->id,
                    'cost_center_code' => (string) $costCenter->code,
                    'cost_center_name' => $costCenter->getTranslations('name'),
                    'cost_center_status' => $costCenter->is_active ? 'active' : 'inactive',
                    'is_unassigned' => false,
                    'currency' => $curr,
                    'ledger_row_count' => 0,
                    'debit_minor' => 0,
                    'credit_minor' => 0,
                    'net_minor' => 0,
                    'accounts' => [],
                ]);
            }
        }

        $sortedRows = $rows->sortBy([
            ['is_unassigned', 'asc'],
            ['cost_center_code', 'asc'],
            ['currency', 'asc'],
        ])->values();

        $currencyCodes = $this->resolveCurrencyCodes($sortedRows, $currency);
        $summaryByCurrency = $this->summarizeByCurrency($sortedRows, $currencyCodes);

        $unassignedRowCount = $ledgerRows->filter(fn ($r): bool => $r->cost_center_id === null)->sum('ledger_row_count');

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'period_id' => $periodId,
            'cost_center_id' => $costCenterId,
            'project_id' => $projectId,
            'account_id' => $accountId,
            'currency' => $currency,
            'base_currency' => $this->baseCurrency(),
            'currency_codes' => $currencyCodes,
            'has_mixed_currencies' => count($currencyCodes) > 1,
            'rows' => $sortedRows->all(),
            'summary_by_currency' => $summaryByCurrency,
            'readiness' => [
                'unassigned_row_count' => (int) $unassignedRowCount,
                'has_unassigned' => (int) $unassignedRowCount > 0,
            ],
        ];
    }

    private function queryLedgerRows(
        string $fromDate,
        string $toDate,
        ?string $costCenterId,
        ?string $projectId,
        ?string $accountId,
        ?string $currency,
    ): Collection {
        return DB::table('ledger_entry')
            ->join('journal_entry', 'journal_entry.id', '=', 'ledger_entry.journal_entry_id')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->select('ledger_entry.cost_center_id', 'ledger_entry.currency')
            ->selectRaw('account.id as account_id')
            ->selectRaw('account.code as account_code')
            ->selectRaw('account.name as account_name')
            ->selectRaw('account.type as account_type')
            ->selectRaw('account.nature as account_nature')
            ->selectRaw('COUNT(ledger_entry.id) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
            ->where('journal_entry.status', '=', 'posted')
            ->where('ledger_entry.entry_date', '>=', $fromDate)
            ->where('ledger_entry.entry_date', '<=', $toDate)
            ->when($costCenterId, fn ($query) => $query->where('ledger_entry.cost_center_id', $costCenterId))
            ->when($projectId, fn ($query) => $query->where('ledger_entry.project_id', $projectId))
            ->when($accountId, fn ($query) => $query->where('ledger_entry.account_id', $accountId))
            ->when($currency, fn ($query) => $query->where('ledger_entry.currency', $currency))
            ->groupBy(
                'ledger_entry.cost_center_id',
                'ledger_entry.currency',
                'account.id',
                'account.code',
                'account.name',
                'account.type',
                'account.nature',
            )
            ->orderBy('account.code')
            ->get();
    }

    private function aggregateByCostCenterAndCurrency(Collection $ledgerRows): Collection
    {
        $groups = collect();

        foreach ($ledgerRows as $row) {
            $key = ($row->cost_center_id ?? '').'__'.$row->currency;
            $current = $groups->get($key, [
                'ledger_row_count' => 0,
                'debit_minor' => 0,
                'credit_minor' => 0,
                'net_minor' => 0,
                'accounts' => [],
            ]);

            $debit = (int) $row->debit_minor;
            $credit = (int) $row->credit_minor;
            $count = (int) $row->ledger_row_count;
            $nature = (string) $row->account_nature;
            $netMinor = ($nature === 'credit') ? ($credit - $debit) : ($debit - $credit);

            $accountName = is_string($row->account_name) && str_starts_with($row->account_name, '{')
                ? json_decode($row->account_name, true)
                : $row->account_name;

            $current['accounts'][] = [
                'account_id' => (string) $row->account_id,
                'account_code' => (string) $row->account_code,
                'account_name' => $accountName,
                'account_type' => (string) $row->account_type,
                'account_nature' => $nature,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'net_minor' => $netMinor,
                'ledger_row_count' => $count,
            ];

            $current['ledger_row_count'] += $count;
            $current['debit_minor'] += $debit;
            $current['credit_minor'] += $credit;
            $current['net_minor'] += $netMinor;

            $groups->put($key, $current);
        }

        return $groups;
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
                'ledger_row_count' => 0,
                'debit_minor' => 0,
                'credit_minor' => 0,
                'net_minor' => 0,
            ];
        }

        foreach ($rows as $row) {
            $curr = (string) $row['currency'];
            if (! isset($summary[$curr])) {
                $summary[$curr] = [
                    'currency' => $curr,
                    'ledger_row_count' => 0,
                    'debit_minor' => 0,
                    'credit_minor' => 0,
                    'net_minor' => 0,
                ];
            }

            $summary[$curr]['ledger_row_count'] += (int) ($row['ledger_row_count'] ?? 0);
            $summary[$curr]['debit_minor'] += (int) ($row['debit_minor'] ?? 0);
            $summary[$curr]['credit_minor'] += (int) ($row['credit_minor'] ?? 0);
            $summary[$curr]['net_minor'] += (int) ($row['net_minor'] ?? 0);
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

    private function baseCurrency(): string
    {
        return $this->currencyResolver->resolve();
    }
}
