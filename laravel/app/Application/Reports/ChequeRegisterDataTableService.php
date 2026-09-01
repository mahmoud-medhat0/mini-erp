<?php

namespace App\Application\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class ChequeRegisterDataTableService
{
    /** @param array<string, string|null> $filters */
    public function data(array $filters): JsonResponse
    {
        return $this->dataTable($this->combinedQuery($filters))->toJson();
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $totals = DB::query()
            ->fromSub($this->combinedQuery($filters), 'cheque_summary')
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'incoming' THEN amount_minor ELSE 0 END), 0) AS incoming_total_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'outgoing' THEN amount_minor ELSE 0 END), 0) AS outgoing_total_minor")
            ->selectRaw('COALESCE(SUM(amount_minor), 0) AS total_amount_minor')
            ->first();

        return [
            'direction' => $filters['direction'] ?? 'all',
            'filters' => [
                'status' => $filters['status'] ?? null,
                'customer_id' => $filters['customer_id'] ?? null,
                'supplier_id' => $filters['supplier_id'] ?? null,
                'bank_account_id' => $filters['bank_account_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'currency' => $filters['currency'],
            ],
            'total_count' => (int) ($totals->total_count ?? 0),
            'incoming_total_minor' => (int) ($totals->incoming_total_minor ?? 0),
            'outgoing_total_minor' => (int) ($totals->outgoing_total_minor ?? 0),
            'total_amount_minor' => (int) ($totals->total_amount_minor ?? 0),
        ];
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
                foreach ([
                    'direction',
                    'party_code',
                    'party_name',
                    'cheque_number',
                    'bank_account_code',
                    'bank_account_name',
                    'status',
                    'reference',
                    'notes',
                ] as $column) {
                    $method = $column === 'direction' ? 'whereRaw' : 'orWhereRaw';
                    $nested->{$method}("LOWER(COALESCE(CAST(cheque_rows.{$column} AS TEXT), '')) LIKE ?", [$like]);
                }
            });
        });

        $dataTable
            ->editColumn('party_name', fn (stdClass $row): array|string => $this->translatableName($row->party_name))
            ->editColumn('bank_account_name', fn (stdClass $row): array|string => $this->translatableName($row->bank_account_name))
            ->editColumn('amount_minor', fn (stdClass $row): int => (int) $row->amount_minor);

        foreach (['party_name', 'cheque_number', 'due_date', 'bank_account_name', 'status', 'amount_minor'] as $column) {
            $dataTable->orderColumn(
                $column,
                "{$column} \$1, due_date ASC, cheque_number ASC, direction ASC, id ASC",
            );
        }

        return $dataTable;
    }

    /** @param array<string, string|null> $filters */
    private function combinedQuery(array $filters): Builder
    {
        $direction = $filters['direction'] ?? 'all';

        if ($direction === 'incoming') {
            return DB::query()->fromSub($this->incomingQuery($filters), 'cheque_rows')->select('cheque_rows.*');
        }

        if ($direction === 'outgoing') {
            return DB::query()->fromSub($this->outgoingQuery($filters), 'cheque_rows')->select('cheque_rows.*');
        }

        $union = $this->incomingQuery($filters)->unionAll($this->outgoingQuery($filters));

        return DB::query()->fromSub($union, 'cheque_rows')->select('cheque_rows.*');
    }

    /** @param array<string, string|null> $filters */
    private function incomingQuery(array $filters): Builder
    {
        $dueDate = 'COALESCE(incoming_cheque.due_date, incoming_cheque.received_date, CAST(incoming_cheque.created_at AS DATE))';

        return DB::table('incoming_cheque')
            ->join('customer as cheque_party', 'cheque_party.id', '=', 'incoming_cheque.customer_id')
            ->leftJoin('bank_account as cheque_bank', function (JoinClause $join): void {
                $join->on('cheque_bank.id', '=', 'incoming_cheque.deposit_bank_account_id');
            })
            ->where('incoming_cheque.currency', $filters['currency'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('incoming_cheque.status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('incoming_cheque.customer_id', $id))
            ->when($filters['bank_account_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('incoming_cheque.deposit_bank_account_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereRaw("{$dueDate} >= ?", [$date]))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereRaw("{$dueDate} <= ?", [$date]))
            ->select([
                'incoming_cheque.id',
                'incoming_cheque.cheque_number',
                'incoming_cheque.currency',
                'incoming_cheque.amount_minor',
                'incoming_cheque.status',
                'incoming_cheque.reference',
                'incoming_cheque.description as notes',
                'incoming_cheque.created_at',
                'cheque_party.code as party_code',
                'cheque_bank.code as bank_account_code',
            ])
            ->selectRaw("'incoming' AS direction")
            ->selectRaw('CAST(cheque_party.name AS TEXT) AS party_name')
            ->selectRaw("COALESCE(CAST(cheque_bank.name AS TEXT), incoming_cheque.drawer_bank_name, '—') AS bank_account_name")
            ->selectRaw("{$dueDate} AS due_date");
    }

    /** @param array<string, string|null> $filters */
    private function outgoingQuery(array $filters): Builder
    {
        $dueDate = 'COALESCE(outgoing_cheque.due_date, outgoing_cheque.issued_date, CAST(outgoing_cheque.created_at AS DATE))';

        return DB::table('outgoing_cheque')
            ->join('supplier as cheque_party', 'cheque_party.id', '=', 'outgoing_cheque.supplier_id')
            ->join('bank_account as cheque_bank', 'cheque_bank.id', '=', 'outgoing_cheque.bank_account_id')
            ->where('outgoing_cheque.currency', $filters['currency'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('outgoing_cheque.status', $status))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('outgoing_cheque.supplier_id', $id))
            ->when($filters['bank_account_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('outgoing_cheque.bank_account_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereRaw("{$dueDate} >= ?", [$date]))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereRaw("{$dueDate} <= ?", [$date]))
            ->select([
                'outgoing_cheque.id',
                'outgoing_cheque.cheque_number',
                'outgoing_cheque.currency',
                'outgoing_cheque.amount_minor',
                'outgoing_cheque.status',
                'outgoing_cheque.reference',
                'outgoing_cheque.description as notes',
                'outgoing_cheque.created_at',
                'cheque_party.code as party_code',
                'cheque_bank.code as bank_account_code',
            ])
            ->selectRaw("'outgoing' AS direction")
            ->selectRaw('CAST(cheque_party.name AS TEXT) AS party_name')
            ->selectRaw("COALESCE(CAST(cheque_bank.name AS TEXT), '—') AS bank_account_name")
            ->selectRaw("{$dueDate} AS due_date");
    }

    private function translatableName(mixed $name): array|string
    {
        if (! is_string($name)) {
            return is_array($name) ? $name : (string) $name;
        }

        $decoded = json_decode($name, true);

        return is_array($decoded) ? $decoded : $name;
    }
}
