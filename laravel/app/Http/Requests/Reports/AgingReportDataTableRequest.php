<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AgingReportDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $columns = $this->allowedColumns();

        return [
            'as_of_date' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'currency' => ['bail', 'nullable', 'string', 'size:3', 'exists:currency,code'],
            'customer_id' => ['bail', 'nullable', 'uuid', 'exists:customer,id'],
            'supplier_id' => ['bail', 'nullable', 'uuid', 'exists:supplier,id'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'columns' => ['nullable', 'array', 'max:8'],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in($columns)],
            'columns.*.name' => ['nullable', 'string', Rule::in($columns)],
            'order' => ['nullable', 'array', 'max:2'],
            'order.*.column' => ['required_with:order', 'integer', 'between:0,7'],
            'order.*.dir' => ['required_with:order', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->query('currency');

        if (is_string($currency)) {
            $this->merge(['currency' => strtoupper(trim($currency))]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $columns = $this->input('columns', []);

                foreach ((array) $this->input('order', []) as $position => $order) {
                    $index = is_array($order) ? ($order['column'] ?? null) : null;

                    if (! is_numeric($index) || ! is_array($columns) || ! array_key_exists((int) $index, $columns)) {
                        $validator->errors()->add("order.{$position}.column", __('The selected order column is invalid.'));
                    }
                }
            },
        ];
    }

    /** @return array<string, string|null> */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'as_of_date' => $validated['as_of_date'] ?? date('Y-m-d'),
            'currency' => $validated['currency'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
        ];
    }

    /** @return list<string> */
    private function allowedColumns(): array
    {
        $partner = $this->route()?->getName() === 'reports.ar-aging.data' ? 'customer' : 'supplier';

        return [
            "{$partner}_name",
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
