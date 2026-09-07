<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FixedAssetReportDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $schema = $this->columnSchema();
        $booleanValues = [true, false, 0, 1, '0', '1', 'true', 'false'];
        $falseValues = [false, 0, '0', 'false'];

        return [
            'category_id' => ['bail', 'nullable', 'uuid', 'exists:fixed_asset_category,id'],
            'period_id' => ['bail', 'nullable', 'uuid', 'exists:financial_period,id'],
            'status' => ['nullable', 'string', Rule::in($this->allowedStatuses())],
            'disposal_type' => ['nullable', 'string', Rule::in(['sale', 'scrap', 'retirement'])],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in($falseValues)],
            'columns' => ['nullable', 'array', 'max:'.count($schema)],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in(array_keys($schema))],
            'columns.*.name' => ['required_with:columns', 'string', Rule::in(array_values(array_unique(array_column($schema, 'name'))))],
            'columns.*.searchable' => ['required_with:columns', Rule::in($booleanValues)],
            'columns.*.orderable' => ['required_with:columns', Rule::in($booleanValues)],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in($falseValues)],
            'order' => ['nullable', 'array', 'max:3'],
            'order.*.column' => ['required_with:order', 'integer', 'min:0'],
            'order.*.dir' => ['required_with:order', Rule::in(['asc', 'desc'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schema = $this->columnSchema();
            $columns = $this->input('columns', []);

            if (! is_array($columns)) {
                return;
            }

            foreach ($columns as $index => $column) {
                if (! is_array($column) || ! isset($schema[$column['data'] ?? ''])) {
                    continue;
                }

                $definition = $schema[$column['data']];

                if (($column['name'] ?? null) !== $definition['name']) {
                    $validator->errors()->add("columns.$index.name", 'The column name does not match the fixed-asset report schema.');
                }

                foreach (['searchable', 'orderable'] as $capability) {
                    if ($this->booleanValue($column[$capability] ?? null) !== $definition[$capability]) {
                        $validator->errors()->add("columns.$index.$capability", "The column $capability capability does not match the fixed-asset report schema.");
                    }
                }
            }

            foreach ((array) $this->input('order', []) as $index => $order) {
                $columnIndex = is_array($order) ? filter_var($order['column'] ?? null, FILTER_VALIDATE_INT) : false;

                if ($columnIndex === false || ! array_key_exists($columnIndex, $columns)) {
                    $validator->errors()->add("order.$index.column", 'The order column must reference a submitted column.');

                    continue;
                }

                $data = is_array($columns[$columnIndex]) ? ($columns[$columnIndex]['data'] ?? '') : '';

                if (! isset($schema[$data]) || ! $schema[$data]['orderable']) {
                    $validator->errors()->add("order.$index.column", 'The selected fixed-asset report column is not orderable.');
                }
            }
        }];
    }

    /** @return array<string, string|null> */
    public function reportFilters(): array
    {
        $validated = $this->validated();
        $allowed = match ($this->routeName()) {
            'reports.fixed-asset-register.data',
            'reports.fixed-asset-net-book-values.data' => ['category_id', 'status'],
            'reports.fixed-asset-depreciation.data' => ['status'],
            'reports.fixed-asset-depreciation-runs.data' => ['period_id', 'status'],
            'reports.fixed-asset-disposals.data' => ['disposal_type', 'status'],
            default => [],
        };

        return array_filter(
            array_intersect_key($validated, array_flip($allowed)),
            fn ($value): bool => $value !== null && $value !== '',
        );
    }

    /** @return list<string> */
    private function allowedStatuses(): array
    {
        return match ($this->routeName()) {
            'reports.fixed-asset-register.data',
            'reports.fixed-asset-net-book-values.data' => ['draft', 'active', 'fully_depreciated', 'disposed'],
            'reports.fixed-asset-depreciation.data' => ['planned', 'posted', 'reversed', 'skipped'],
            'reports.fixed-asset-depreciation-runs.data',
            'reports.fixed-asset-disposals.data' => ['posted', 'reversed'],
            default => [],
        };
    }

    /** @return array<string, array{name: string, searchable: bool, orderable: bool}> */
    private function columnSchema(): array
    {
        return match ($this->routeName()) {
            'reports.fixed-asset-register.data' => [
                'asset_number' => $this->column('asset_number'),
                'name' => $this->column('name', orderable: false),
                'category' => $this->column('category', orderable: false),
                'cost_minor' => $this->column('cost_minor', searchable: false),
                'total_accumulated_depreciation_minor' => $this->column('total_accumulated_depreciation_minor', searchable: false),
                'net_book_value_minor' => $this->column('net_book_value_minor', searchable: false),
                'status' => $this->column('status'),
            ],
            'reports.fixed-asset-net-book-values.data' => [
                'asset_number' => $this->column('asset_number'),
                'name' => $this->column('name', orderable: false),
                'cost_minor' => $this->column('cost_minor', searchable: false),
                'opening_accumulated_depreciation_minor' => $this->column('opening_accumulated_depreciation_minor', searchable: false),
                'posted_accumulated_depreciation_minor' => $this->column('posted_accumulated_depreciation_minor', searchable: false),
                'total_accumulated_depreciation_minor' => $this->column('total_accumulated_depreciation_minor', searchable: false),
                'net_book_value_minor' => $this->column('net_book_value_minor', searchable: false),
                'status' => $this->column('status'),
            ],
            'reports.fixed-asset-depreciation.data' => [
                'asset' => $this->column('asset', orderable: false),
                'period_number' => $this->column('period_number', searchable: false),
                'period_start_date' => $this->column('period_start_date'),
                'period_end_date' => $this->column('period_end_date'),
                'depreciation_minor' => $this->column('depreciation_minor', searchable: false),
                'accumulated_depreciation_minor' => $this->column('accumulated_depreciation_minor', searchable: false),
                'net_book_value_minor' => $this->column('net_book_value_minor', searchable: false),
                'status' => $this->column('status'),
            ],
            'reports.fixed-asset-depreciation-runs.data' => [
                'number' => $this->column('number'),
                'run_date' => $this->column('run_date'),
                'financial_period' => $this->column('financial_period', orderable: false),
                'asset_count' => $this->column('asset_count', searchable: false),
                'total_depreciation_minor' => $this->column('total_depreciation_minor', searchable: false),
                'journal_number' => $this->column('journal_number', orderable: false),
                'status' => $this->column('status'),
            ],
            'reports.fixed-asset-disposals.data' => [
                'number' => $this->column('number'),
                'asset' => $this->column('asset', orderable: false),
                'disposal_date' => $this->column('disposal_date'),
                'disposal_type' => $this->column('disposal_type'),
                'proceeds_minor' => $this->column('proceeds_minor', searchable: false),
                'net_book_value_minor' => $this->column('net_book_value_minor', searchable: false),
                'gain_loss_minor' => $this->column('gain_loss_minor', searchable: false),
                'status' => $this->column('status'),
            ],
            default => [],
        };
    }

    /** @return array{name: string, searchable: bool, orderable: bool} */
    private function column(string $name, bool $searchable = true, bool $orderable = true): array
    {
        return compact('name', 'searchable', 'orderable');
    }

    private function booleanValue(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }

    private function routeName(): string
    {
        return (string) $this->route()?->getName();
    }
}
