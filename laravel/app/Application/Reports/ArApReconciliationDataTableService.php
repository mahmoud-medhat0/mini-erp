<?php

namespace App\Application\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class ArApReconciliationDataTableService
{
    private const SORT_COLUMNS = [
        'partner_code' => 'reconciliation_partners.partner_code',
        'partner_name' => 'reconciliation_partners.partner_name',
        'subledger_balance_minor' => 'reconciliation_partners.subledger_balance_minor',
    ];

    public function __construct(
        private readonly ArApReconciliationQueryService $queryService,
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /** @param array{as_of_date: string, currency: string|null} $filters */
    public function accountsReceivable(array $filters): JsonResponse
    {
        return $this->response($filters, 'receivable');
    }

    /** @param array{as_of_date: string, currency: string|null} $filters */
    public function accountsPayable(array $filters): JsonResponse
    {
        return $this->response($filters, 'payable');
    }

    /**
     * @param  array{as_of_date: string, currency: string|null}  $filters
     * @param  'receivable'|'payable'  $type
     */
    private function response(array $filters, string $type): JsonResponse
    {
        $currency = $this->currencyResolver->resolve($filters['currency']);
        $query = DB::query()
            ->fromSub(
                $this->queryService->partnerBalances($type, $filters['as_of_date'], $currency),
                'reconciliation_partners',
            )
            ->select('reconciliation_partners.*');

        return $this->dataTable($query)
            ->editColumn('partner_name', fn (stdClass $row): array|string => $this->queryService->translatableName($row->partner_name))
            ->editColumn('subledger_balance_minor', fn (stdClass $row): int => (int) $row->subledger_balance_minor)
            ->toJson();
    }

    private function dataTable(Builder $query): QueryDataTable
    {
        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $pattern = "%{$search}%";
                $builder->where(function (Builder $nested) use ($pattern): void {
                    $nested
                        ->whereLike('reconciliation_partners.partner_code', $pattern)
                        ->orWhereLike('reconciliation_partners.partner_name', $pattern);
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

                $builder->orderBy('reconciliation_partners.partner_id');
            });
    }
}
