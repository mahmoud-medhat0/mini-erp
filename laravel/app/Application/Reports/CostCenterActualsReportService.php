<?php

namespace App\Application\Reports;

use App\Models\CostCenter;
use App\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;

class CostCenterActualsReportService
{
    private const SORT_COLUMNS = [
        'cost_center_code' => 'cost_center_actuals.cost_center_code',
        'cost_center_name' => 'cost_center_actuals.cost_center_name',
        'cost_center_status' => 'cost_center_actuals.cost_center_status',
        'currency' => 'cost_center_actuals.currency',
        'ledger_row_count' => 'cost_center_actuals.ledger_row_count',
        'debit_minor' => 'cost_center_actuals.debit_minor',
        'credit_minor' => 'cost_center_actuals.credit_minor',
        'net_minor' => 'cost_center_actuals.net_minor',
    ];

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

    /**
     * Return page-level figures calculated over the complete filtered result.
     * The potentially large row set is exposed separately by datatable().
     *
     * @return array<string, mixed>
     */
    public function metadata(
        ?string $costCenterId = null,
        ?string $projectId = null,
        ?string $accountId = null,
        ?string $currency = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $periodId = null,
    ): array {
        $context = $this->queryContext($costCenterId, $projectId, $accountId, $currency, $dateFrom, $dateTo, $periodId);

        $summaryRows = DB::query()
            ->fromSub($this->datatableRowsQuery($context), 'cost_center_actuals')
            ->select('cost_center_actuals.currency')
            ->selectRaw('COALESCE(SUM(cost_center_actuals.ledger_row_count), 0) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(cost_center_actuals.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(cost_center_actuals.credit_minor), 0) as credit_minor')
            ->selectRaw('COALESCE(SUM(cost_center_actuals.net_minor), 0) as net_minor')
            ->groupBy('cost_center_actuals.currency')
            ->orderBy('cost_center_actuals.currency')
            ->get();

        $summaryByCurrency = [];
        foreach ($summaryRows as $row) {
            $code = (string) $row->currency;
            $summaryByCurrency[$code] = [
                'currency' => $code,
                'ledger_row_count' => (int) $row->ledger_row_count,
                'debit_minor' => (int) $row->debit_minor,
                'credit_minor' => (int) $row->credit_minor,
                'net_minor' => (int) $row->net_minor,
            ];
        }

        $currencyCodes = array_keys($summaryByCurrency);
        if ($currencyCodes === []) {
            $currencyCodes = [$currency ?: $this->baseCurrency()];
            $summaryByCurrency[$currencyCodes[0]] = [
                'currency' => $currencyCodes[0],
                'ledger_row_count' => 0,
                'debit_minor' => 0,
                'credit_minor' => 0,
                'net_minor' => 0,
            ];
        }

        $unassignedRowCount = (int) DB::query()
            ->fromSub($this->datatableRowsQuery($context), 'cost_center_actuals')
            ->where('cost_center_actuals.is_unassigned', 1)
            ->sum('cost_center_actuals.ledger_row_count');

        return [
            'from_date' => $context['from_date'],
            'to_date' => $context['to_date'],
            'period_id' => $periodId,
            'cost_center_id' => $costCenterId,
            'project_id' => $projectId,
            'account_id' => $accountId,
            'currency' => $currency,
            'base_currency' => $this->baseCurrency(),
            'currency_codes' => $currencyCodes,
            'has_mixed_currencies' => count($currencyCodes) > 1,
            'summary_by_currency' => $summaryByCurrency,
            'readiness' => [
                'unassigned_row_count' => $unassignedRowCount,
                'has_unassigned' => $unassignedRowCount > 0,
            ],
        ];
    }

    public function datatable(
        ?string $costCenterId = null,
        ?string $projectId = null,
        ?string $accountId = null,
        ?string $currency = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $periodId = null,
    ): JsonResponse {
        $context = $this->queryContext($costCenterId, $projectId, $accountId, $currency, $dateFrom, $dateTo, $periodId);
        $query = DB::query()
            ->fromSub($this->datatableRowsQuery($context), 'cost_center_actuals')
            ->select('cost_center_actuals.*');

        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));
                if ($search === '') {
                    return;
                }

                $pattern = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($pattern): void {
                    $nested->whereRaw('LOWER(cost_center_actuals.cost_center_code) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(CAST(COALESCE(cost_center_actuals.cost_center_name, \'\') AS TEXT)) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(cost_center_actuals.cost_center_status, \'\')) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(cost_center_actuals.currency) LIKE ?', [$pattern]);
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

                $builder->orderBy('cost_center_actuals.is_unassigned')
                    ->orderBy('cost_center_actuals.cost_center_code')
                    ->orderBy('cost_center_actuals.currency');
            })
            ->editColumn('cost_center_name', fn (stdClass $row): array|string|null => $this->decodeNullableTranslations($row->cost_center_name))
            // Yajra's default escape='*' would HTML-escape "&" etc. inside the decoded
            // {en, ar} maps - including the nested `accounts.N.account_name` entries
            // built by accountBreakdown() below, whose array indices vary per row and
            // can't be listed statically via rawColumns(). Disable escaping for this
            // response entirely instead: safe here since the frontend slot renderers
            // (datatables.net-react) render every value as plain React text (no
            // dangerouslySetInnerHTML anywhere in the codebase).
            ->escapeColumns([])
            ->editColumn('is_unassigned', fn (stdClass $row): bool => (bool) $row->is_unassigned)
            ->editColumn('ledger_row_count', fn (stdClass $row): int => (int) $row->ledger_row_count)
            ->editColumn('debit_minor', fn (stdClass $row): int => (int) $row->debit_minor)
            ->editColumn('credit_minor', fn (stdClass $row): int => (int) $row->credit_minor)
            ->editColumn('net_minor', fn (stdClass $row): int => (int) $row->net_minor)
            ->addColumn('accounts', fn (stdClass $row): array => $this->accountBreakdown(
                $context,
                $row->cost_center_id === null ? null : (string) $row->cost_center_id,
                (string) $row->currency,
            ))
            ->toJson();
    }

    /** @return array<string, mixed> */
    private function queryContext(
        ?string $costCenterId,
        ?string $projectId,
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
            'cost_center_id' => $costCenterId,
            'project_id' => $projectId,
            'account_id' => $accountId,
            'currency' => $currency,
            'from_date' => $dateFrom ? Carbon::parse($dateFrom)->toDateString() : Carbon::now()->startOfYear()->toDateString(),
            'to_date' => $dateTo ? Carbon::parse($dateTo)->toDateString() : Carbon::now()->toDateString(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function datatableRowsQuery(array $context): Builder
    {
        $movements = DB::table('ledger_entry')
            ->join('journal_entry', 'journal_entry.id', '=', 'ledger_entry.journal_entry_id')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->leftJoin('cost_center', 'cost_center.id', '=', 'ledger_entry.cost_center_id')
            ->selectRaw('ledger_entry.cost_center_id as cost_center_id')
            ->selectRaw("COALESCE(cost_center.code, 'UNASSIGNED') as cost_center_code")
            ->selectRaw('CAST(cost_center.name AS TEXT) as cost_center_name')
            ->selectRaw("CASE WHEN ledger_entry.cost_center_id IS NULL THEN NULL WHEN cost_center.is_active THEN 'active' ELSE 'inactive' END as cost_center_status")
            ->selectRaw('CASE WHEN ledger_entry.cost_center_id IS NULL THEN 1 ELSE 0 END as is_unassigned')
            ->selectRaw('ledger_entry.currency as currency')
            ->selectRaw('COUNT(ledger_entry.id) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
            ->selectRaw("COALESCE(SUM(CASE WHEN account.nature = 'credit' THEN ledger_entry.credit_minor - ledger_entry.debit_minor ELSE ledger_entry.debit_minor - ledger_entry.credit_minor END), 0) as net_minor")
            ->where('journal_entry.status', 'posted')
            ->whereBetween('ledger_entry.entry_date', [$context['from_date'], $context['to_date']])
            ->when($context['cost_center_id'], fn (Builder $builder) => $builder->where('ledger_entry.cost_center_id', $context['cost_center_id']))
            ->when($context['project_id'], fn (Builder $builder) => $builder->where('ledger_entry.project_id', $context['project_id']))
            ->when($context['account_id'], fn (Builder $builder) => $builder->where('ledger_entry.account_id', $context['account_id']))
            ->when($context['currency'], fn (Builder $builder) => $builder->where('ledger_entry.currency', $context['currency']))
            ->groupBy(
                'ledger_entry.cost_center_id',
                'cost_center.code',
                'cost_center.is_active',
                'ledger_entry.currency',
            )
            ->groupByRaw('CAST(cost_center.name AS TEXT)');

        if (! $context['cost_center_id']) {
            return $movements;
        }

        $emptyCurrency = $context['currency'] ?: $this->baseCurrency();
        $emptySelection = DB::table('cost_center')
            ->selectRaw('cost_center.id as cost_center_id')
            ->selectRaw('cost_center.code as cost_center_code')
            ->selectRaw('CAST(cost_center.name AS TEXT) as cost_center_name')
            ->selectRaw("CASE WHEN cost_center.is_active THEN 'active' ELSE 'inactive' END as cost_center_status")
            ->selectRaw('0 as is_unassigned')
            ->selectRaw('? as currency', [$emptyCurrency])
            ->selectRaw('0 as ledger_row_count, 0 as debit_minor, 0 as credit_minor, 0 as net_minor')
            ->where('cost_center.id', $context['cost_center_id'])
            ->whereNotExists(function (Builder $builder) use ($context): void {
                $builder->selectRaw('1')
                    ->from('ledger_entry as selected_ledger_entry')
                    ->join('journal_entry as selected_journal_entry', 'selected_journal_entry.id', '=', 'selected_ledger_entry.journal_entry_id')
                    ->where('selected_journal_entry.status', 'posted')
                    ->whereBetween('selected_ledger_entry.entry_date', [$context['from_date'], $context['to_date']])
                    ->where('selected_ledger_entry.cost_center_id', $context['cost_center_id'])
                    ->when($context['project_id'], fn (Builder $query) => $query->where('selected_ledger_entry.project_id', $context['project_id']))
                    ->when($context['account_id'], fn (Builder $query) => $query->where('selected_ledger_entry.account_id', $context['account_id']))
                    ->when($context['currency'], fn (Builder $query) => $query->where('selected_ledger_entry.currency', $context['currency']));
            });

        return DB::query()
            ->fromSub($movements->unionAll($emptySelection), 'cost_center_actuals_source')
            ->select(
                'cost_center_id',
                'cost_center_code',
                'cost_center_name',
                'cost_center_status',
                'is_unassigned',
                'currency',
            )
            ->selectRaw('SUM(ledger_row_count) as ledger_row_count')
            ->selectRaw('SUM(debit_minor) as debit_minor')
            ->selectRaw('SUM(credit_minor) as credit_minor')
            ->selectRaw('SUM(net_minor) as net_minor')
            ->groupBy('cost_center_id', 'cost_center_code', 'cost_center_name', 'cost_center_status', 'is_unassigned', 'currency');
    }

    /** @param array<string, mixed> $context */
    private function accountBreakdown(array $context, ?string $costCenterId, string $currency): array
    {
        return DB::table('ledger_entry')
            ->join('journal_entry', 'journal_entry.id', '=', 'ledger_entry.journal_entry_id')
            ->join('account', 'account.id', '=', 'ledger_entry.account_id')
            ->selectRaw('account.id as account_id, account.code as account_code, CAST(account.name AS TEXT) as account_name')
            ->selectRaw('account.type as account_type, account.nature as account_nature')
            ->selectRaw('COUNT(ledger_entry.id) as ledger_row_count')
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor), 0) as debit_minor')
            ->selectRaw('COALESCE(SUM(ledger_entry.credit_minor), 0) as credit_minor')
            ->where('journal_entry.status', 'posted')
            ->whereBetween('ledger_entry.entry_date', [$context['from_date'], $context['to_date']])
            ->where('ledger_entry.currency', $currency)
            ->when($costCenterId === null, fn (Builder $builder) => $builder->whereNull('ledger_entry.cost_center_id'))
            ->when($costCenterId !== null, fn (Builder $builder) => $builder->where('ledger_entry.cost_center_id', $costCenterId))
            ->when($context['project_id'], fn (Builder $builder) => $builder->where('ledger_entry.project_id', $context['project_id']))
            ->when($context['account_id'], fn (Builder $builder) => $builder->where('ledger_entry.account_id', $context['account_id']))
            ->groupBy('account.id', 'account.code', 'account.type', 'account.nature')
            ->groupByRaw('CAST(account.name AS TEXT)')
            ->orderBy('account.code')
            ->get()
            ->map(function (stdClass $row): array {
                $debit = (int) $row->debit_minor;
                $credit = (int) $row->credit_minor;

                return [
                    'account_id' => (string) $row->account_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => $this->decodeNullableTranslations($row->account_name),
                    'account_type' => (string) $row->account_type,
                    'account_nature' => (string) $row->account_nature,
                    'debit_minor' => $debit,
                    'credit_minor' => $credit,
                    'net_minor' => $row->account_nature === 'credit' ? $credit - $debit : $debit - $credit,
                    'ledger_row_count' => (int) $row->ledger_row_count,
                ];
            })
            ->all();
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
