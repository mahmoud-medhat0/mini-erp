<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartnerStatementDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $customerStatement = $this->route()?->getName() === 'reports.customer-statement.data';
        $columns = ['date', 'type', 'reference', 'description', 'debit_minor', 'credit_minor', 'running_balance_minor'];

        return [
            'customer_id' => $customerStatement
                ? ['bail', 'required', 'uuid', 'exists:customer,id']
                : ['prohibited'],
            'supplier_id' => $customerStatement
                ? ['prohibited']
                : ['bail', 'required', 'uuid', 'exists:supplier,id'],
            'date_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'currency' => ['bail', 'required', 'string', 'size:3', 'exists:currency,code'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'columns' => ['nullable', 'required_with:order', 'array', 'max:7'],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in($columns)],
            'columns.*.name' => ['nullable', 'string', Rule::in($columns)],
            'columns.*.searchable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.orderable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'order' => ['nullable', 'array', 'max:1'],
            'order.*.column' => ['required_with:order', 'integer', 'between:0,6'],
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
            'customer_id' => $validated['customer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'currency' => $validated['currency'],
        ];
    }
}
