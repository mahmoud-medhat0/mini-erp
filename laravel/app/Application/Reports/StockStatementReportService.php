<?php

namespace App\Application\Reports;

use App\Models\Product;
use App\Models\StockMovementLedger;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class StockStatementReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /** @param array<string, string|null> $filters */
    public function product(array $filters): JsonResponse
    {
        return $this->dataTable($filters, 'product')->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function warehouse(array $filters): JsonResponse
    {
        return $this->dataTable($filters, 'warehouse')->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function productSummary(array $filters): array
    {
        return $this->summary($filters, 'product');
    }

    /** @param array<string, string|null> $filters */
    public function warehouseSummary(array $filters): array
    {
        return $this->summary($filters, 'warehouse');
    }

    /**
     * Full line-by-line export used only by CSV download. Uses Eloquent (rather
     * than the window-function query the datatable feed relies on) so
     * translatable product/warehouse names decode through the normal
     * HasTranslations accessor instead of arriving as raw JSON text.
     *
     * @param  array<string, string|null>  $filters
     * @param  'product'|'warehouse'  $dimension
     */
    public function generate(string $dimension, array $filters): array
    {
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);
        $singleProduct = $this->isSingleProduct($dimension, $filters);

        $scoped = fn () => $this->scopedQuery(StockMovementLedger::query(), $dimension, $filters)
            ->where('currency', $currency);

        $openingQtyE6 = $singleProduct
            ? (int) (clone $scoped())->where('movement_date', '<', $filters['date_from'])->sum('quantity_delta_e6')
            : 0;
        $openingValueMinor = (int) (clone $scoped())->where('movement_date', '<', $filters['date_from'])->sum('value_delta_minor');

        $movements = $scoped()
            ->with(['product:id,code,name', 'warehouse:id,code,name', 'journalEntry:id,number,reference'])
            ->whereBetween('movement_date', [$filters['date_from'], $filters['date_to']])
            ->orderBy('movement_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $runningQty = $openingQtyE6;
        $runningValue = $openingValueMinor;
        $totalInQty = 0;
        $totalOutQty = 0;
        $totalInValue = 0;
        $totalOutValue = 0;
        $lines = [];

        foreach ($movements as $movement) {
            $qty = (int) $movement->quantity_delta_e6;
            $value = (int) $movement->value_delta_minor;
            $runningQty += $qty;
            $runningValue += $value;

            if ($qty > 0) {
                $totalInQty += $qty;
            } else {
                $totalOutQty += -$qty;
            }

            if ($value > 0) {
                $totalInValue += $value;
            } else {
                $totalOutValue += -$value;
            }

            $lines[] = [
                'date' => $movement->movement_date?->format('Y-m-d'),
                'type' => $this->typeLabel($movement->movement_type),
                'reference' => $movement->journalEntry?->reference ?: ($movement->journalEntry?->number ?: 'MOV-'.$movement->id),
                'description' => $this->descriptionLabel($movement->source_type),
                'product_code' => $movement->product?->code,
                'product_name' => $movement->product?->name,
                'warehouse_code' => $movement->warehouse?->code,
                'warehouse_name' => $movement->warehouse?->name,
                'quantity_delta_e6' => $qty,
                'value_delta_minor' => $value,
                'balance_quantity_e6' => $singleProduct ? $runningQty : null,
                'balance_valuation_amount_minor' => $runningValue,
            ];
        }

        return [
            'dimension' => $dimension,
            'entity' => $this->entitySummary($dimension, $filters),
            'filters' => [
                'product_id' => $filters['product_id'] ?? null,
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'currency' => $currency,
            ],
            'single_product' => $singleProduct,
            'opening_balance_quantity_e6' => $singleProduct ? $openingQtyE6 : null,
            'opening_balance_value_minor' => $openingValueMinor,
            'lines' => $lines,
            'total_in_quantity_e6' => $singleProduct ? $totalInQty : null,
            'total_out_quantity_e6' => $singleProduct ? $totalOutQty : null,
            'total_in_value_minor' => $totalInValue,
            'total_out_value_minor' => $totalOutValue,
            'closing_balance_quantity_e6' => $singleProduct ? $runningQty : null,
            'closing_balance_value_minor' => $runningValue,
        ];
    }

    /** @param 'product'|'warehouse' $dimension */
    private function dataTable(array $filters, string $dimension): QueryDataTable
    {
        $singleProduct = $this->isSingleProduct($dimension, $filters);
        $opening = $this->openingBalance($filters, $dimension, $singleProduct);
        $query = $this->statementRowsQuery($filters, $dimension, $opening, $singleProduct);

        $dataTable = DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(REPLACE(COALESCE(statement_rows.movement_type, ''), '_', ' ')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.reference, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.product_code, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.product_name, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.warehouse_code, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(statement_rows.warehouse_name, '')) LIKE ?", [$like])
                        ->orWhereRaw('CAST(statement_rows.date AS TEXT) LIKE ?', [$like]);
                });
            })
            ->order(function (Builder $builder) use ($dimension): void {
                $columns = (array) request()->input('columns', []);
                $sortableColumns = $this->sortableColumns($dimension);
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
            ->addColumn('type', fn (stdClass $row): string => $this->typeLabel($row->movement_type))
            ->addColumn('description', fn (stdClass $row): string => $this->descriptionLabel($row->source_type));

        foreach (['quantity_delta_e6', 'value_delta_minor', 'balance_valuation_amount_minor'] as $column) {
            $dataTable->editColumn($column, fn (stdClass $row) => (int) $row->{$column});
        }

        $dataTable->editColumn('balance_quantity_e6', fn (stdClass $row) => $row->balance_quantity_e6 === null ? null : (int) $row->balance_quantity_e6);

        $dataTable = $dataTable
            ->removeColumn('movement_type')
            ->removeColumn('source_type')
            ->removeColumn('sort_created_at');

        return $dimension === 'product'
            ? $dataTable->removeColumn('product_code')->removeColumn('product_name')
            : $dataTable->removeColumn('warehouse_code')->removeColumn('warehouse_name');
    }

    /**
     * Keep the window calculation inside a subquery, mirroring
     * PartnerStatementDataTableService. Translatable product/warehouse names
     * are extracted to a plain locale string in SQL (never raw JSON) so a
     * later render can never surface an untranslated object.
     *
     * @param  array<string, string|null>  $filters
     * @param  'product'|'warehouse'  $dimension
     * @param  array{quantity_e6: int, value_minor: int}  $opening
     */
    private function statementRowsQuery(array $filters, string $dimension, array $opening, bool $singleProduct): Builder
    {
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);
        $entryAlias = 'ledger_entry';

        $movements = $this->scopedQuery(DB::table('stock_movement_ledger as '.$entryAlias), $dimension, $filters)
            ->leftJoin('journal_entry as statement_journal', 'statement_journal.id', '=', "{$entryAlias}.journal_entry_id")
            ->leftJoin('product as statement_product', 'statement_product.id', '=', "{$entryAlias}.product_id")
            ->leftJoin('warehouse as statement_warehouse', 'statement_warehouse.id', '=', "{$entryAlias}.warehouse_id")
            ->where("{$entryAlias}.currency", $currency)
            ->whereBetween("{$entryAlias}.movement_date", [$filters['date_from'], $filters['date_to']])
            ->select([
                "{$entryAlias}.id",
                "{$entryAlias}.movement_date as date",
                "{$entryAlias}.movement_type",
                "{$entryAlias}.source_type",
                "{$entryAlias}.quantity_delta_e6",
                "{$entryAlias}.value_delta_minor",
                'statement_product.code as product_code',
                'statement_warehouse.code as warehouse_code',
            ])
            ->selectRaw("COALESCE(NULLIF(statement_journal.reference, ''), NULLIF(statement_journal.number, ''), 'MOV-' || CAST({$entryAlias}.id AS TEXT)) AS reference")
            ->selectRaw($this->localizedNameExpression('statement_product.name').' AS product_name')
            ->selectRaw($this->localizedNameExpression('statement_warehouse.name').' AS warehouse_name')
            ->selectRaw("COALESCE({$entryAlias}.created_at, {$entryAlias}.movement_date) AS sort_created_at");

        $windowed = DB::query()
            ->fromSub($movements, 'statement_movements')
            ->select([
                'statement_movements.id',
                'statement_movements.date',
                'statement_movements.movement_type',
                'statement_movements.source_type',
                'statement_movements.reference',
                'statement_movements.product_code',
                'statement_movements.product_name',
                'statement_movements.warehouse_code',
                'statement_movements.warehouse_name',
                'statement_movements.quantity_delta_e6',
                'statement_movements.value_delta_minor',
                'statement_movements.sort_created_at',
            ])
            ->selectRaw(
                'CAST(? AS BIGINT) + SUM(statement_movements.value_delta_minor) OVER (ORDER BY statement_movements.date, statement_movements.sort_created_at, statement_movements.id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS balance_valuation_amount_minor',
                [$opening['value_minor']],
            );

        if ($singleProduct) {
            $windowed->selectRaw(
                'CAST(? AS BIGINT) + SUM(statement_movements.quantity_delta_e6) OVER (ORDER BY statement_movements.date, statement_movements.sort_created_at, statement_movements.id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS balance_quantity_e6',
                [$opening['quantity_e6']],
            );
        } else {
            $windowed->selectRaw('CAST(NULL AS BIGINT) AS balance_quantity_e6');
        }

        return DB::query()->fromSub($windowed, 'statement_rows')->select('statement_rows.*');
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'product'|'warehouse'  $dimension
     * @return array{quantity_e6: int, value_minor: int}
     */
    private function openingBalance(array $filters, string $dimension, bool $singleProduct): array
    {
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);

        $query = $this->scopedQuery(DB::table('stock_movement_ledger'), $dimension, $filters)
            ->where('currency', $currency)
            ->where('movement_date', '<', $filters['date_from']);

        return [
            'quantity_e6' => $singleProduct ? (int) (clone $query)->sum('quantity_delta_e6') : 0,
            'value_minor' => (int) (clone $query)->sum('value_delta_minor'),
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  'product'|'warehouse'  $dimension
     */
    private function summary(array $filters, string $dimension): array
    {
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);
        $singleProduct = $this->isSingleProduct($dimension, $filters);

        $scoped = fn () => $this->scopedQuery(DB::table('stock_movement_ledger'), $dimension, $filters)
            ->where('currency', $currency);

        $openingQtyE6 = $singleProduct
            ? (int) (clone $scoped())->where('movement_date', '<', $filters['date_from'])->sum('quantity_delta_e6')
            : 0;
        $openingValueMinor = (int) (clone $scoped())->where('movement_date', '<', $filters['date_from'])->sum('value_delta_minor');

        $rangeAmounts = $scoped()
            ->whereBetween('movement_date', [$filters['date_from'], $filters['date_to']])
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_delta_e6 > 0 THEN quantity_delta_e6 ELSE 0 END), 0) AS total_in_quantity_e6')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_delta_e6 < 0 THEN -quantity_delta_e6 ELSE 0 END), 0) AS total_out_quantity_e6')
            ->selectRaw('COALESCE(SUM(CASE WHEN value_delta_minor > 0 THEN value_delta_minor ELSE 0 END), 0) AS total_in_value_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN value_delta_minor < 0 THEN -value_delta_minor ELSE 0 END), 0) AS total_out_value_minor')
            ->first();

        $totalInQty = (int) ($rangeAmounts->total_in_quantity_e6 ?? 0);
        $totalOutQty = (int) ($rangeAmounts->total_out_quantity_e6 ?? 0);
        $totalInValue = (int) ($rangeAmounts->total_in_value_minor ?? 0);
        $totalOutValue = (int) ($rangeAmounts->total_out_value_minor ?? 0);

        return [
            'entity' => $this->entitySummary($dimension, $filters),
            'filters' => [
                'product_id' => $filters['product_id'] ?? null,
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'currency' => $currency,
            ],
            'single_product' => $singleProduct,
            'opening_balance_quantity_e6' => $singleProduct ? $openingQtyE6 : null,
            'opening_balance_value_minor' => $openingValueMinor,
            'total_in_quantity_e6' => $singleProduct ? $totalInQty : null,
            'total_out_quantity_e6' => $singleProduct ? $totalOutQty : null,
            'total_in_value_minor' => $totalInValue,
            'total_out_value_minor' => $totalOutValue,
            'closing_balance_quantity_e6' => $singleProduct ? $openingQtyE6 + $totalInQty - $totalOutQty : null,
            'closing_balance_value_minor' => $openingValueMinor + $totalInValue - $totalOutValue,
        ];
    }

    /**
     * @param  'product'|'warehouse'  $dimension
     * @param  array<string, string|null>  $filters
     */
    private function entitySummary(string $dimension, array $filters): array
    {
        if ($dimension === 'product') {
            $product = Product::query()->with('unitOfMeasure:id,code')->findOrFail($filters['product_id']);

            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'uom_code' => $product->unitOfMeasure?->code,
            ];
        }

        $warehouse = Warehouse::query()->with('branch:id,code,name')->findOrFail($filters['warehouse_id']);

        return [
            'id' => $warehouse->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'branch_code' => $warehouse->branch?->code,
            'branch_name' => $warehouse->branch?->name,
        ];
    }

    /**
     * Applies the mandatory primary dimension filter plus the optional
     * secondary dimension filter (warehouse for a product statement, product
     * for a warehouse statement) to any query builder over the ledger table.
     *
     * @template TBuilder of Builder|EloquentBuilder
     *
     * @param  TBuilder  $query
     * @param  'product'|'warehouse'  $dimension
     * @param  array<string, string|null>  $filters
     * @return TBuilder
     */
    private function scopedQuery(Builder|EloquentBuilder $query, string $dimension, array $filters): Builder|EloquentBuilder
    {
        if ($dimension === 'product') {
            $query->where('product_id', $filters['product_id']);

            if (! empty($filters['warehouse_id'])) {
                $query->where('warehouse_id', $filters['warehouse_id']);
            }
        } else {
            $query->where('warehouse_id', $filters['warehouse_id']);

            if (! empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }
        }

        return $query;
    }

    /**
     * A running quantity balance only means something when every row shares
     * the same unit of measure, i.e. exactly one product is in scope.
     *
     * @param  'product'|'warehouse'  $dimension
     * @param  array<string, string|null>  $filters
     */
    private function isSingleProduct(string $dimension, array $filters): bool
    {
        return $dimension === 'product' || ! empty($filters['product_id']);
    }

    private function localizedNameExpression(string $column): string
    {
        return "COALESCE(NULLIF({$column}->>'en', ''), NULLIF({$column}->>'ar', ''))";
    }

    /** @return array<string, string> */
    private function sortableColumns(string $dimension): array
    {
        $columns = [
            'date' => 'statement_rows.date',
            'type' => 'statement_rows.movement_type',
            'reference' => 'statement_rows.reference',
            'description' => 'statement_rows.source_type',
            'quantity_delta_e6' => 'statement_rows.quantity_delta_e6',
            'value_delta_minor' => 'statement_rows.value_delta_minor',
            'balance_quantity_e6' => 'statement_rows.balance_quantity_e6',
            'balance_valuation_amount_minor' => 'statement_rows.balance_valuation_amount_minor',
        ];

        return $dimension === 'product'
            ? $columns + ['warehouse_name' => 'statement_rows.warehouse_code']
            : $columns + ['product_name' => 'statement_rows.product_code'];
    }

    private function typeLabel(?string $movementType): string
    {
        return match ($movementType) {
            'receipt' => 'Receipt',
            'issue' => 'Issue',
            'reversal' => 'Reversal',
            'scrap' => 'Scrap',
            'transfer_out' => 'Transfer Out',
            'transfer_in' => 'Transfer In',
            'adjustment' => 'Adjustment',
            'landed_cost' => 'Landed Cost',
            null, '' => 'Stock Movement',
            default => Str::headline($movementType),
        };
    }

    private function descriptionLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'goods_receipt', 'goods_receipt_line' => 'Goods Receipt',
            'delivery_note' => 'Delivery Note',
            'sales_return' => 'Sales Return',
            'stock_transfer' => 'Stock Transfer (Out)',
            'stock_transfer_receipt' => 'Stock Transfer (In)',
            'stock_adjustment' => 'Stock Adjustment',
            'stock_count' => 'Stock Count',
            'landed_cost_allocation' => 'Landed Cost Allocation',
            null, '' => 'Stock Movement',
            default => Str::headline($sourceType),
        };
    }
}
