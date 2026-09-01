<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ArApReconciliationDataTableRequest extends FormRequest
{
    private const COLUMN_SCHEMA = [
        'partner_code' => ['searchable' => true, 'orderable' => true],
        'partner_name' => ['searchable' => true, 'orderable' => true],
        'subledger_balance_minor' => ['searchable' => false, 'orderable' => true],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'columns' => ['nullable', 'array', 'max:3'],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in(array_keys(self::COLUMN_SCHEMA))],
            'columns.*.name' => ['required_with:columns', 'string', Rule::in(array_keys(self::COLUMN_SCHEMA))],
            'columns.*.searchable' => ['required_with:columns', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.orderable' => ['required_with:columns', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
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

            $columns = $this->input('columns', []);

            if (! is_array($columns)) {
                return;
            }

            foreach ($columns as $index => $column) {
                if (! is_array($column) || ! isset(self::COLUMN_SCHEMA[$column['data'] ?? ''])) {
                    continue;
                }

                $data = $column['data'];
                $schema = self::COLUMN_SCHEMA[$data];

                if (($column['name'] ?? null) !== $data) {
                    $validator->errors()->add("columns.$index.name", 'The column name does not match the reconciliation schema.');
                }

                foreach (['searchable', 'orderable'] as $capability) {
                    if ($this->booleanValue($column[$capability] ?? null) !== $schema[$capability]) {
                        $validator->errors()->add("columns.$index.$capability", "The column $capability capability does not match the reconciliation schema.");
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

                if (! isset(self::COLUMN_SCHEMA[$data]) || ! self::COLUMN_SCHEMA[$data]['orderable']) {
                    $validator->errors()->add("order.$index.column", 'The selected reconciliation column is not orderable.');
                }
            }
        }];
    }

    /** @return array{as_of_date: string, currency: string|null} */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'as_of_date' => $validated['as_of_date'] ?? date('Y-m-d'),
            'currency' => $validated['currency'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->query('currency');

        if (is_string($currency)) {
            $this->merge(['currency' => strtoupper(trim($currency))]);
        }
    }

    private function booleanValue(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }
}
