<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockStatementDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $productStatement = $this->route()?->getName() === 'reports.product-statement.data';
        $columns = $this->allowedColumns($productStatement);

        return [
            'product_id' => $productStatement
                ? ['bail', 'required', 'uuid', 'exists:product,id']
                : ['bail', 'nullable', 'uuid', 'exists:product,id'],
            'warehouse_id' => $productStatement
                ? ['bail', 'nullable', 'uuid', 'exists:warehouse,id']
                : ['bail', 'required', 'uuid', 'exists:warehouse,id'],
            'date_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'currency' => ['bail', 'required', 'string', 'size:3', 'exists:currency,code'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'columns' => ['nullable', 'required_with:order', 'array', 'max:'.count($columns)],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in($columns)],
            'columns.*.name' => ['nullable', 'string', Rule::in($columns)],
            'columns.*.searchable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.orderable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'order' => ['nullable', 'array', 'max:1'],
            'order.*.column' => ['required_with:order', 'integer', 'between:0,'.(count($columns) - 1)],
            'order.*.dir' => ['required_with:order', Rule::in(['asc', 'desc'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $columns = $this->input('columns', []);

            foreach ((array) $this->input('order', []) as $position => $order) {
                $columnIndex = is_array($order)
                    ? filter_var($order['column'] ?? null, FILTER_VALIDATE_INT)
                    : false;

                if ($columnIndex === false || ! is_array($columns) || ! array_key_exists($columnIndex, $columns)) {
                    $validator->errors()->add(
                        "order.{$position}.column",
                        __('The selected order column is invalid.'),
                    );
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->query('currency');

        if (is_string($currency)) {
            $this->merge(['currency' => strtoupper(trim($currency))]);
        }
    }

    /** @return array<string, string|null> */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'product_id' => $validated['product_id'] ?? null,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'currency' => $validated['currency'],
        ];
    }

    /** @return list<string> */
    private function allowedColumns(bool $productStatement): array
    {
        $shared = ['date', 'type', 'reference', 'description', 'quantity_delta_e6', 'value_delta_minor', 'balance_quantity_e6', 'balance_valuation_amount_minor'];

        return $productStatement
            ? [...$shared, 'warehouse_name']
            : [...$shared, 'product_name'];
    }
}
