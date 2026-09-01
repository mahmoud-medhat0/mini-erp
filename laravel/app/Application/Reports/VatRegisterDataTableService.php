<?php

namespace App\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class VatRegisterDataTableService
{
    /** @param array<string, string|null> $filters */
    public function data(array $filters): JsonResponse
    {
        return $this->dataTable($this->combinedQuery($this->normalizeFilters($filters)))->toJson();
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $totals = DB::query()
            ->fromSub($this->combinedQuery($filters), 'vat_summary')
            ->selectRaw("COALESCE(SUM(CASE WHEN tax_category = 'output' THEN subtotal_minor ELSE 0 END), 0) AS total_output_subtotal_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN tax_category = 'output' THEN tax_amount_minor ELSE 0 END), 0) AS total_output_tax_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN tax_category = 'output' THEN gross_amount_minor ELSE 0 END), 0) AS total_output_gross_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN tax_category = 'input' THEN subtotal_minor ELSE 0 END), 0) AS total_input_subtotal_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN tax_category = 'input' THEN tax_amount_minor ELSE 0 END), 0) AS total_input_tax_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN tax_category = 'input' THEN gross_amount_minor ELSE 0 END), 0) AS total_input_gross_minor")
            ->first();
        $outputTax = (int) ($totals->total_output_tax_minor ?? 0);
        $inputTax = (int) ($totals->total_input_tax_minor ?? 0);

        return [
            'from_date' => $filters['from_date'],
            'to_date' => $filters['to_date'],
            'type' => $filters['type'],
            'tax_code_id' => $filters['tax_code_id'],
            'summary' => [
                'total_output_subtotal_minor' => (int) ($totals->total_output_subtotal_minor ?? 0),
                'total_output_tax_minor' => $outputTax,
                'total_output_gross_minor' => (int) ($totals->total_output_gross_minor ?? 0),
                'total_input_subtotal_minor' => (int) ($totals->total_input_subtotal_minor ?? 0),
                'total_input_tax_minor' => $inputTax,
                'total_input_gross_minor' => (int) ($totals->total_input_gross_minor ?? 0),
                'net_vat_payable_minor' => $outputTax - $inputTax,
            ],
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
                    'document_date',
                    'document_type',
                    'document_number',
                    'entity_name',
                    'tax_category',
                    'tax_code',
                    'tax_rate_bps',
                    'subtotal_minor',
                    'tax_amount_minor',
                    'gross_amount_minor',
                ] as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $nested->{$method}("LOWER(COALESCE(CAST(vat_rows.{$column} AS TEXT), '')) LIKE ?", [$like]);
                }
            });
        });

        $dataTable->editColumn(
            'entity_name',
            fn (stdClass $row): array|string => $this->translatableName($row->entity_name),
        );

        foreach (['tax_rate_bps', 'subtotal_minor', 'tax_amount_minor', 'gross_amount_minor'] as $column) {
            $dataTable->editColumn($column, fn (stdClass $row): int => (int) $row->{$column});
        }

        foreach ([
            'document_date',
            'document_type',
            'document_number',
            'entity_name',
            'tax_category',
            'tax_code',
            'subtotal_minor',
            'tax_amount_minor',
            'gross_amount_minor',
        ] as $column) {
            $dataTable->orderColumn(
                $column,
                "{$column} \$1, document_date ASC, document_number ASC, document_type ASC, document_id ASC, line_id ASC",
            );
        }

        return $dataTable;
    }

    /** @param array<string, string|null> $filters */
    private function combinedQuery(array $filters): Builder
    {
        $queries = [];

        if (in_array($filters['type'], ['all', 'output'], true)) {
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'customer_invoice',
                lineTable: 'customer_invoice_line',
                lineForeignKey: 'customer_invoice_id',
                entityTable: 'customer',
                entityForeignKey: 'customer_id',
                dateColumn: 'invoice_date',
                documentType: 'customer_invoice',
                entityType: 'customer',
                taxCategory: 'output',
                subtotalExpression: 'vat_line.line_total_minor',
                taxExpression: 'vat_line.tax_amount_minor',
                grossExpression: $this->positiveGross('vat_line.line_total_minor', 'vat_line.tax_amount_minor'),
            );
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'customer_credit_note',
                lineTable: 'customer_credit_note_line',
                lineForeignKey: 'customer_credit_note_id',
                entityTable: 'customer',
                entityForeignKey: 'customer_id',
                dateColumn: 'credit_date',
                documentType: 'customer_credit_note',
                entityType: 'customer',
                taxCategory: 'output',
                subtotalExpression: '-ABS(vat_line.line_subtotal_minor)',
                taxExpression: '-ABS(vat_line.tax_minor)',
                grossExpression: '-ABS(CASE WHEN COALESCE(vat_line.line_total_minor, 0) = 0
                    THEN ABS(vat_line.line_subtotal_minor) + ABS(vat_line.tax_minor)
                    ELSE vat_line.line_total_minor END)',
            );
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'sales_return',
                lineTable: 'sales_return_line',
                lineForeignKey: 'sales_return_id',
                entityTable: 'customer',
                entityForeignKey: 'customer_id',
                dateColumn: 'return_date',
                documentType: 'sales_return',
                entityType: 'customer',
                taxCategory: 'output',
                subtotalExpression: '-ABS(vat_line.stock_value_minor)',
                taxExpression: '-ABS(vat_line.tax_amount_minor)',
                grossExpression: '-ABS(CASE WHEN COALESCE(vat_line.gross_amount_minor, 0) = 0
                    THEN ABS(vat_line.stock_value_minor) + ABS(vat_line.tax_amount_minor)
                    ELSE vat_line.gross_amount_minor END)',
            );
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'rental_invoice',
                lineTable: 'rental_invoice_line',
                lineForeignKey: 'rental_invoice_id',
                entityTable: 'customer',
                entityForeignKey: 'customer_id',
                dateColumn: 'invoice_date',
                documentType: 'rental_invoice',
                entityType: 'customer',
                taxCategory: 'output',
                subtotalExpression: 'vat_line.line_total_minor',
                taxExpression: 'vat_line.tax_amount_minor',
                grossExpression: $this->positiveGross('vat_line.line_total_minor', 'vat_line.tax_amount_minor'),
            );
        }

        if (in_array($filters['type'], ['all', 'input'], true)) {
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'supplier_bill',
                lineTable: 'supplier_bill_line',
                lineForeignKey: 'supplier_bill_id',
                entityTable: 'supplier',
                entityForeignKey: 'supplier_id',
                dateColumn: 'bill_date',
                documentType: 'supplier_bill',
                entityType: 'supplier',
                taxCategory: 'input',
                subtotalExpression: 'vat_line.line_total_minor',
                taxExpression: 'vat_line.tax_amount_minor',
                grossExpression: $this->positiveGross('vat_line.line_total_minor', 'vat_line.tax_amount_minor'),
            );

            $adjustmentTax = '(CASE WHEN COALESCE(vat_line.tax_amount_minor, 0) <> 0
                THEN vat_line.tax_amount_minor ELSE vat_line.tax_minor END)';
            $adjustmentGross = "(CASE WHEN COALESCE(vat_line.gross_amount_minor, 0) = 0
                THEN vat_line.line_subtotal_minor + {$adjustmentTax}
                ELSE vat_line.gross_amount_minor END)";
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'supplier_adjustment_note',
                lineTable: 'supplier_adjustment_note_line',
                lineForeignKey: 'supplier_adjustment_note_id',
                entityTable: 'supplier',
                entityForeignKey: 'supplier_id',
                dateColumn: 'adjustment_date',
                documentType: 'supplier_adjustment_note',
                entityType: 'supplier',
                taxCategory: 'input',
                subtotalExpression: $this->adjustmentSign('vat_line.line_subtotal_minor'),
                taxExpression: $this->adjustmentSign($adjustmentTax),
                grossExpression: $this->adjustmentSign($adjustmentGross),
            );
            $queries[] = $this->documentLines(
                filters: $filters,
                documentTable: 'purchase_return',
                lineTable: 'purchase_return_line',
                lineForeignKey: 'purchase_return_id',
                entityTable: 'supplier',
                entityForeignKey: 'supplier_id',
                dateColumn: 'return_date',
                documentType: 'purchase_return',
                entityType: 'supplier',
                taxCategory: 'input',
                subtotalExpression: '-ABS(vat_line.original_receipt_cost_minor)',
                taxExpression: '-ABS(vat_line.tax_amount_minor)',
                grossExpression: '-ABS(CASE WHEN COALESCE(vat_line.gross_amount_minor, 0) = 0
                    THEN ABS(vat_line.original_receipt_cost_minor) + ABS(vat_line.tax_amount_minor)
                    ELSE vat_line.gross_amount_minor END)',
            );
        }

        /** @var Builder $union */
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'vat_rows')->select('vat_rows.*');
    }

    /**
     * @param  array<string, string|null>  $filters
     */
    private function documentLines(
        array $filters,
        string $documentTable,
        string $lineTable,
        string $lineForeignKey,
        string $entityTable,
        string $entityForeignKey,
        string $dateColumn,
        string $documentType,
        string $entityType,
        string $taxCategory,
        string $subtotalExpression,
        string $taxExpression,
        string $grossExpression,
    ): Builder {
        return DB::table("{$documentTable} as vat_document")
            ->join("{$lineTable} as vat_line", "vat_line.{$lineForeignKey}", '=', 'vat_document.id')
            ->join("{$entityTable} as vat_entity", 'vat_entity.id', '=', "vat_document.{$entityForeignKey}")
            ->join('tax_codes as vat_tax', 'vat_tax.id', '=', 'vat_line.tax_code_id')
            ->where('vat_document.status', 'posted')
            ->whereBetween("vat_document.{$dateColumn}", [$filters['from_date'], $filters['to_date']])
            ->when(
                $filters['tax_code_id'],
                fn (Builder $query, string $taxCodeId): Builder => $query->where('vat_line.tax_code_id', $taxCodeId),
            )
            ->selectRaw('CAST(? AS TEXT) AS document_type', [$documentType])
            ->selectRaw('CAST(vat_document.id AS TEXT) AS document_id')
            ->selectRaw('CAST(vat_line.id AS TEXT) AS line_id')
            ->selectRaw("COALESCE(vat_document.number, 'DRAFT') AS document_number")
            ->addSelect("vat_document.{$dateColumn} as document_date")
            ->selectRaw('CAST(? AS TEXT) AS entity_type', [$entityType])
            ->selectRaw('CAST(vat_entity.name AS TEXT) AS entity_name')
            ->selectRaw('CAST(? AS TEXT) AS tax_category', [$taxCategory])
            ->selectRaw('CAST(vat_line.tax_code_id AS TEXT) AS tax_code_id')
            ->addSelect('vat_tax.code as tax_code', 'vat_line.tax_rate_bps')
            ->selectRaw("{$subtotalExpression} AS subtotal_minor")
            ->selectRaw("{$taxExpression} AS tax_amount_minor")
            ->selectRaw("{$grossExpression} AS gross_amount_minor");
    }

    private function positiveGross(string $subtotalExpression, string $taxExpression): string
    {
        return "CASE WHEN COALESCE(vat_line.gross_amount_minor, 0) = 0
            THEN {$subtotalExpression} + {$taxExpression}
            ELSE vat_line.gross_amount_minor END";
    }

    private function adjustmentSign(string $expression): string
    {
        return "CASE WHEN vat_document.direction = 'decrease_payable'
            THEN -ABS({$expression}) ELSE ABS({$expression}) END";
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array{from_date: string, to_date: string, type: string, tax_code_id: string|null}
     */
    private function normalizeFilters(array $filters): array
    {
        $now = CarbonImmutable::now();

        return [
            'from_date' => $filters['from_date'] ?? $now->startOfMonth()->format('Y-m-d'),
            'to_date' => $filters['to_date'] ?? $now->endOfMonth()->format('Y-m-d'),
            'type' => $filters['type'] ?? 'all',
            'tax_code_id' => $filters['tax_code_id'] ?? null,
        ];
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
