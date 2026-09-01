<?php

namespace App\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class AgingReportDataTableService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /** @param array<string, string|null> $filters */
    public function accountsReceivable(array $filters): JsonResponse
    {
        return $this->dataTable($this->groupedQuery($filters, 'receivable'))
            ->editColumn('customer_name', fn (stdClass $row): array|string => $this->translatableName($row->customer_name))
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function accountsPayable(array $filters): JsonResponse
    {
        return $this->dataTable($this->groupedQuery($filters, 'payable'))
            ->editColumn('supplier_name', fn (stdClass $row): array|string => $this->translatableName($row->supplier_name))
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function accountsReceivableSummary(array $filters): array
    {
        return $this->summary($filters, 'receivable');
    }

    /** @param array<string, string|null> $filters */
    public function accountsPayableSummary(array $filters): array
    {
        return $this->summary($filters, 'payable');
    }

    private function dataTable(Builder $query): QueryDataTable
    {
        $dataTable = DataTables::query($query)->filter(function (Builder $builder): void {
            $search = trim((string) request()->input('search.value', ''));

            if ($search === '') {
                return;
            }

            $like = '%'.mb_strtolower($search).'%';

            $builder->where(function (Builder $nested) use ($like): void {
                $nested
                    ->whereRaw('LOWER(aging_entries.partner_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(CAST(aging_entries.partner_name AS TEXT)) LIKE ?', [$like]);
            });
        });

        $dataTable->order(function (Builder $builder): void {
            $columns = (array) request()->input('columns', []);
            $orders = (array) request()->input('order', []);

            foreach ($orders as $order) {
                $index = (int) ($order['column'] ?? -1);
                $column = $columns[$index]['data'] ?? null;

                if (is_string($column) && in_array($column, $this->sortableColumns(), true)) {
                    $builder->orderBy($column, ($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
                }
            }

            $builder->orderBy('aging_entries.partner_id');
        });

        foreach (['open_items_count', 'current', 'b1_30', 'b31_60', 'b61_90', 'over_90', 'total'] as $column) {
            $dataTable->editColumn($column, fn (stdClass $row): int => (int) $row->{$column});
        }

        return $dataTable;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'receivable'|'payable'  $type
     */
    private function groupedQuery(array $filters, string $type): Builder
    {
        $partner = $type === 'receivable' ? 'customer' : 'supplier';
        $asOf = $this->asOf($filters);
        $query = DB::query()
            ->fromSub($this->openEntryQuery($filters, $type), 'aging_entries')
            ->select([
                "aging_entries.partner_id as {$partner}_id",
                "aging_entries.partner_code as {$partner}_code",
                "aging_entries.partner_name as {$partner}_name",
                'aging_entries.currency',
            ])
            ->selectRaw('COUNT(*) AS open_items_count')
            ->groupBy([
                'aging_entries.partner_id',
                'aging_entries.partner_code',
                'aging_entries.partner_name',
                'aging_entries.currency',
            ]);

        return $this->selectBuckets($query, $asOf);
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'receivable'|'payable'  $type
     * @return array{current: int, b1_30: int, b31_60: int, b61_90: int, over_90: int, total: int}
     */
    private function summary(array $filters, string $type): array
    {
        $query = DB::query()->fromSub($this->openEntryQuery($filters, $type), 'aging_entries');
        $row = $this->selectBuckets($query, $this->asOf($filters))->first();

        return [
            'current' => (int) ($row->current ?? 0),
            'b1_30' => (int) ($row->b1_30 ?? 0),
            'b31_60' => (int) ($row->b31_60 ?? 0),
            'b61_90' => (int) ($row->b61_90 ?? 0),
            'over_90' => (int) ($row->over_90 ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * Build one row per still-open ledger entry. Allocation and settlement
     * totals are joined once, avoiding per-entry aggregate queries.
     *
     * @param  array<string, string|null>  $filters
     * @param  'receivable'|'payable'  $type
     */
    private function openEntryQuery(array $filters, string $type): Builder
    {
        $isReceivable = $type === 'receivable';
        $entryTable = $isReceivable ? 'receivable_entry' : 'payable_entry';
        $allocationTable = $isReceivable ? 'receivable_allocation' : 'payable_allocation';
        $settlementTable = $isReceivable ? 'receivable_entry_settlement' : 'payable_entry_settlement';
        $partnerTable = $isReceivable ? 'customer' : 'supplier';
        $partnerForeignKey = $isReceivable ? 'customer_id' : 'supplier_id';
        $allocationForeignKey = $isReceivable ? 'receivable_entry_id' : 'payable_entry_id';
        $settlementForeignKey = $isReceivable ? 'target_receivable_entry_id' : 'target_payable_entry_id';
        $netExpression = $isReceivable
            ? '(aging_entry.debit_minor - aging_entry.credit_minor)'
            : '(aging_entry.credit_minor - aging_entry.debit_minor)';

        $asOf = $this->asOf($filters);
        $asOfDate = $asOf->format('Y-m-d');
        $cutoff = $asOf->addDay()->format('Y-m-d H:i:s');
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);
        $partnerId = $filters[$partnerForeignKey] ?? null;

        $allocations = DB::table($allocationTable)
            ->select($allocationForeignKey)
            ->selectRaw('SUM(amount_minor) AS allocated_minor')
            ->where('allocated_at', '<', $cutoff)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where(function (Builder $active): void {
                    $active->where('status', 'active')->whereNull('reversed_at');
                })->orWhere(function (Builder $reversed) use ($cutoff): void {
                    $reversed->where('status', 'reversed')->where('reversed_at', '>=', $cutoff);
                });
            })
            ->groupBy($allocationForeignKey);

        $settlements = DB::table($settlementTable)
            ->select($settlementForeignKey)
            ->selectRaw('SUM(amount_minor) AS settled_minor')
            ->where('settled_at', '<', $cutoff)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where(function (Builder $active): void {
                    $active->where('status', 'active')->whereNull('reversed_at');
                })->orWhere(function (Builder $reversed) use ($cutoff): void {
                    $reversed->where('status', 'reversed')->where('reversed_at', '>=', $cutoff);
                });
            })
            ->groupBy($settlementForeignKey);

        $openExpression = "{$netExpression} - COALESCE(aging_allocations.allocated_minor, 0) - COALESCE(aging_settlements.settled_minor, 0)";

        return DB::table("{$entryTable} as aging_entry")
            ->join("{$partnerTable} as aging_partner", 'aging_partner.id', '=', "aging_entry.{$partnerForeignKey}")
            ->leftJoinSub($allocations, 'aging_allocations', function (JoinClause $join) use ($allocationForeignKey): void {
                $join->on("aging_allocations.{$allocationForeignKey}", '=', 'aging_entry.id');
            })
            ->leftJoinSub($settlements, 'aging_settlements', function (JoinClause $join) use ($settlementForeignKey): void {
                $join->on("aging_settlements.{$settlementForeignKey}", '=', 'aging_entry.id');
            })
            ->where('aging_entry.currency', $currency)
            ->where('aging_entry.entry_date', '<=', $asOfDate)
            ->when($partnerId, fn (Builder $query, string $id): Builder => $query->where("aging_entry.{$partnerForeignKey}", $id))
            ->whereRaw("{$netExpression} > 0")
            ->whereRaw("({$openExpression}) > 0")
            ->select([
                'aging_entry.id as entry_id',
                "aging_entry.{$partnerForeignKey} as partner_id",
                'aging_partner.code as partner_code',
                'aging_partner.name as partner_name',
                'aging_entry.currency',
            ])
            ->selectRaw('COALESCE(aging_entry.due_date, aging_entry.entry_date) AS basis_date')
            ->selectRaw("({$openExpression}) AS open_minor");
    }

    private function selectBuckets(Builder $query, CarbonImmutable $asOf): Builder
    {
        $asOfDate = $asOf->format('Y-m-d');
        $day30 = $asOf->subDays(30)->format('Y-m-d');
        $day60 = $asOf->subDays(60)->format('Y-m-d');
        $day90 = $asOf->subDays(90)->format('Y-m-d');

        return $query
            ->selectRaw('COALESCE(SUM(CASE WHEN aging_entries.basis_date >= ? THEN aging_entries.open_minor ELSE 0 END), 0) AS current', [$asOfDate])
            ->selectRaw('COALESCE(SUM(CASE WHEN aging_entries.basis_date >= ? AND aging_entries.basis_date < ? THEN aging_entries.open_minor ELSE 0 END), 0) AS b1_30', [$day30, $asOfDate])
            ->selectRaw('COALESCE(SUM(CASE WHEN aging_entries.basis_date >= ? AND aging_entries.basis_date < ? THEN aging_entries.open_minor ELSE 0 END), 0) AS b31_60', [$day60, $day30])
            ->selectRaw('COALESCE(SUM(CASE WHEN aging_entries.basis_date >= ? AND aging_entries.basis_date < ? THEN aging_entries.open_minor ELSE 0 END), 0) AS b61_90', [$day90, $day60])
            ->selectRaw('COALESCE(SUM(CASE WHEN aging_entries.basis_date < ? THEN aging_entries.open_minor ELSE 0 END), 0) AS over_90', [$day90])
            ->selectRaw('COALESCE(SUM(aging_entries.open_minor), 0) AS total');
    }

    /** @param array<string, string|null> $filters */
    private function asOf(array $filters): CarbonImmutable
    {
        return CarbonImmutable::parse($filters['as_of_date'] ?? date('Y-m-d'))->startOfDay();
    }

    private function translatableName(mixed $name): array|string
    {
        if (! is_string($name)) {
            return is_array($name) ? $name : (string) $name;
        }

        $decoded = json_decode($name, true);

        return is_array($decoded) ? $decoded : $name;
    }

    /** @return list<string> */
    private function sortableColumns(): array
    {
        return [
            'customer_name',
            'supplier_name',
            'open_items_count',
            'current',
            'b1_30',
            'b31_60',
            'b61_90',
            'over_90',
            'total',
        ];
    }
}
