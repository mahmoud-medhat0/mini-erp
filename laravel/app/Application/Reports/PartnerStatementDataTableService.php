<?php

namespace App\Application\Reports;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class PartnerStatementDataTableService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /** @param array<string, string|null> $filters */
    public function customer(array $filters): JsonResponse
    {
        return $this->dataTable($filters, 'customer')->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function supplier(array $filters): JsonResponse
    {
        return $this->dataTable($filters, 'supplier')->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function customerSummary(array $filters): array
    {
        return $this->summary($filters, 'customer');
    }

    /** @param array<string, string|null> $filters */
    public function supplierSummary(array $filters): array
    {
        return $this->summary($filters, 'supplier');
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'customer'|'supplier'  $partner
     */
    private function dataTable(array $filters, string $partner): QueryDataTable
    {
        $openingBalance = $this->openingBalance($filters, $partner);
        $query = $this->statementRowsQuery($filters, $partner, $openingBalance);

        $dataTable = DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(REPLACE(COALESCE(statement_rows.source_type, ''), '_', ' ')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.reference, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.description, '')) LIKE ?", [$like])
                        ->orWhereRaw('CAST(statement_rows.date AS TEXT) LIKE ?', [$like]);
                });
            })
            ->order(function (Builder $builder): void {
                $columns = (array) request()->input('columns', []);
                $sortableColumns = $this->sortableColumns();
                $orderedExpressions = [];

                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);

                    if ($index === false || ! array_key_exists($index, $columns) || ! is_array($columns[$index])) {
                        continue;
                    }

                    $data = $columns[$index]['data'] ?? null;

                    if (! is_string($data) || ! isset($sortableColumns[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    $expression = $sortableColumns[$data];
                    $builder->orderBy($expression, $direction);
                    $orderedExpressions[] = $expression;

                    if ($data === 'date') {
                        $builder->orderBy('statement_rows.sort_created_at', $direction);
                        $orderedExpressions[] = 'statement_rows.sort_created_at';
                    }
                }

                if ($orderedExpressions === []) {
                    $builder
                        ->orderBy('statement_rows.date')
                        ->orderBy('statement_rows.sort_created_at');
                }

                if (! in_array('statement_rows.id', $orderedExpressions, true)) {
                    $builder->orderBy('statement_rows.id');
                }
            })
            ->addColumn('type', fn (stdClass $row): string => $this->typeLabel($row->source_type, $partner));

        foreach (['debit_minor', 'credit_minor', 'running_balance_minor'] as $column) {
            $dataTable->editColumn($column, fn (stdClass $row): int => (int) $row->{$column});
        }

        return $dataTable
            ->removeColumn('source_type')
            ->removeColumn('sort_created_at');
    }

    /**
     * Keep the window calculation inside a subquery. DataTables search is
     * applied by the outer query, so a filtered row retains the balance from
     * every preceding movement in the selected statement period.
     *
     * @param  array<string, string|null>  $filters
     * @param  'customer'|'supplier'  $partner
     */
    private function statementRowsQuery(array $filters, string $partner, int $openingBalance): Builder
    {
        $config = $this->config($partner);
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);
        $entryAlias = 'statement_entry';
        $movementExpression = $partner === 'customer'
            ? "{$entryAlias}.debit_minor - {$entryAlias}.credit_minor"
            : "{$entryAlias}.credit_minor - {$entryAlias}.debit_minor";

        $movements = DB::table($config['entry_table'].' as '.$entryAlias)
            ->leftJoin('journal_entry as statement_journal', 'statement_journal.id', '=', "{$entryAlias}.journal_entry_id")
            ->leftJoin($config['opening_table'].' as statement_opening', function (JoinClause $join) use ($entryAlias, $config): void {
                $join
                    ->on('statement_opening.id', '=', "{$entryAlias}.source_id")
                    ->where("{$entryAlias}.source_type", '=', $config['opening_source']);
            })
            ->leftJoin($config['payment_table'].' as statement_payment', function (JoinClause $join) use ($entryAlias, $config): void {
                $join
                    ->on('statement_payment.id', '=', "{$entryAlias}.source_id")
                    ->where("{$entryAlias}.source_type", '=', $config['payment_source']);
            })
            ->where("{$entryAlias}.{$config['partner_foreign_key']}", $filters[$config['partner_foreign_key']])
            ->where("{$entryAlias}.currency", $currency)
            ->whereBetween("{$entryAlias}.entry_date", [$filters['date_from'], $filters['date_to']])
            ->select([
                "{$entryAlias}.id",
                "{$entryAlias}.entry_date as date",
                "{$entryAlias}.source_type",
                "{$entryAlias}.debit_minor",
                "{$entryAlias}.credit_minor",
            ])
            ->selectRaw("COALESCE({$entryAlias}.description, ?) AS description", [$config['fallback_description']])
            ->selectRaw($this->referenceExpression($config, $entryAlias).' AS reference')
            ->selectRaw("COALESCE({$entryAlias}.created_at, {$entryAlias}.entry_date) AS sort_created_at")
            ->selectRaw("({$movementExpression}) AS movement_minor");

        $windowed = DB::query()
            ->fromSub($movements, 'statement_movements')
            ->select([
                'statement_movements.id',
                'statement_movements.date',
                'statement_movements.source_type',
                'statement_movements.reference',
                'statement_movements.description',
                'statement_movements.debit_minor',
                'statement_movements.credit_minor',
                'statement_movements.sort_created_at',
            ])
            ->selectRaw(
                'CAST(? AS BIGINT) + SUM(statement_movements.movement_minor) OVER (ORDER BY statement_movements.date, statement_movements.sort_created_at, statement_movements.id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_balance_minor',
                [$openingBalance],
            );

        return DB::query()->fromSub($windowed, 'statement_rows')->select('statement_rows.*');
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'customer'|'supplier'  $partner
     */
    private function summary(array $filters, string $partner): array
    {
        $config = $this->config($partner);
        $partnerId = (string) $filters[$config['partner_foreign_key']];
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);
        $deltaExpression = $partner === 'customer'
            ? 'debit_minor - credit_minor'
            : 'credit_minor - debit_minor';

        $amounts = DB::table($config['entry_table'])
            ->where($config['partner_foreign_key'], $partnerId)
            ->where('currency', $currency)
            ->where('entry_date', '<=', $filters['date_to'])
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_date < ? THEN {$deltaExpression} ELSE 0 END), 0) AS opening_balance_minor", [$filters['date_from']])
            ->selectRaw('COALESCE(SUM(CASE WHEN entry_date BETWEEN ? AND ? THEN debit_minor ELSE 0 END), 0) AS total_debit_minor', [$filters['date_from'], $filters['date_to']])
            ->selectRaw('COALESCE(SUM(CASE WHEN entry_date BETWEEN ? AND ? THEN credit_minor ELSE 0 END), 0) AS total_credit_minor', [$filters['date_from'], $filters['date_to']])
            ->first();

        $openingBalance = (int) ($amounts->opening_balance_minor ?? 0);
        $totalDebit = (int) ($amounts->total_debit_minor ?? 0);
        $totalCredit = (int) ($amounts->total_credit_minor ?? 0);
        $closingBalance = $partner === 'customer'
            ? $openingBalance + $totalDebit - $totalCredit
            : $openingBalance + $totalCredit - $totalDebit;
        $model = $partner === 'customer'
            ? Customer::query()->findOrFail($partnerId)
            : Supplier::query()->findOrFail($partnerId);

        return [
            $partner => [
                'id' => $model->id,
                'code' => $model->code,
                'name' => $model->name,
                'tax_number' => $model->tax_number,
                'phone' => $model->phone,
            ],
            'filters' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'currency' => $currency,
            ],
            'opening_balance_minor' => $openingBalance,
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'closing_balance_minor' => $closingBalance,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'customer'|'supplier'  $partner
     */
    private function openingBalance(array $filters, string $partner): int
    {
        $config = $this->config($partner);
        $deltaExpression = $partner === 'customer'
            ? 'debit_minor - credit_minor'
            : 'credit_minor - debit_minor';

        return (int) DB::table($config['entry_table'])
            ->where($config['partner_foreign_key'], $filters[$config['partner_foreign_key']])
            ->where('currency', $this->currencyResolver->resolve($filters['currency'] ?? null))
            ->where('entry_date', '<', $filters['date_from'])
            ->sum(DB::raw($deltaExpression));
    }

    /** @param array<string, string> $config */
    private function referenceExpression(array $config, string $entryAlias): string
    {
        return "CASE
            WHEN {$entryAlias}.source_type = '{$config['opening_source']}' AND statement_opening.id IS NOT NULL
                THEN COALESCE(NULLIF(statement_opening.reference, ''), '{$config['opening_prefix']}' || CAST(statement_opening.id AS TEXT))
            WHEN {$entryAlias}.source_type = '{$config['payment_source']}' AND statement_payment.id IS NOT NULL
                THEN COALESCE(NULLIF(statement_payment.number, ''), NULLIF(statement_payment.reference, ''), '{$config['payment_prefix']}' || CAST(statement_payment.id AS TEXT))
            ELSE COALESCE(NULLIF(statement_journal.reference, ''), NULLIF(statement_journal.number, ''), '{$config['entry_prefix']}' || CAST({$entryAlias}.id AS TEXT))
        END";
    }

    /** @return array<string, string> */
    private function sortableColumns(): array
    {
        return [
            'date' => 'statement_rows.date',
            'type' => 'statement_rows.source_type',
            'reference' => 'statement_rows.reference',
            'description' => 'statement_rows.description',
            'debit_minor' => 'statement_rows.debit_minor',
            'credit_minor' => 'statement_rows.credit_minor',
            'running_balance_minor' => 'statement_rows.running_balance_minor',
        ];
    }

    /**
     * @param  'customer'|'supplier'  $partner
     * @return array<string, string>
     */
    private function config(string $partner): array
    {
        if ($partner === 'customer') {
            return [
                'entry_table' => 'receivable_entry',
                'partner_foreign_key' => 'customer_id',
                'opening_table' => 'customer_opening_balance',
                'opening_source' => 'customer_opening_balance',
                'payment_table' => 'customer_receipt',
                'payment_source' => 'customer_receipt',
                'opening_prefix' => 'OB-',
                'payment_prefix' => 'REC-',
                'entry_prefix' => 'RE-',
                'fallback_description' => 'Receivable Entry',
            ];
        }

        return [
            'entry_table' => 'payable_entry',
            'partner_foreign_key' => 'supplier_id',
            'opening_table' => 'supplier_opening_balance',
            'opening_source' => 'supplier_opening_balance',
            'payment_table' => 'supplier_payment',
            'payment_source' => 'supplier_payment',
            'opening_prefix' => 'OB-',
            'payment_prefix' => 'PAY-',
            'entry_prefix' => 'PE-',
            'fallback_description' => 'Payable Entry',
        ];
    }

    /** @param 'customer'|'supplier' $partner */
    private function typeLabel(?string $sourceType, string $partner): string
    {
        return match ($sourceType) {
            'customer_opening_balance', 'supplier_opening_balance', 'opening_balance' => 'Opening Balance',
            'customer_receipt' => 'Customer Receipt',
            'supplier_payment' => 'Supplier Payment',
            null, '' => $partner === 'customer' ? 'Receivable Entry' : 'Payable Entry',
            default => Str::headline($sourceType),
        };
    }
}
