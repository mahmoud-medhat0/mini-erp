<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RentalOperationsDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $columns = $this->allowedColumns();
        $falseValues = [false, 0, '0', 'false'];

        return [
            'as_of_date' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'branch_id' => ['bail', 'nullable', 'uuid', 'exists:branch,id'],
            'customer_id' => ['bail', 'nullable', 'uuid', 'exists:customer,id'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'])],
            'currency' => ['bail', 'nullable', 'string', 'size:3', 'exists:currency,code'],
            'date_from' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in($falseValues)],
            'columns' => ['nullable', 'required_with:order', 'array', 'max:'.count($columns)],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in($columns)],
            'columns.*.name' => ['nullable', 'string', Rule::in($columns)],
            'columns.*.searchable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.orderable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in($falseValues)],
            'order' => ['nullable', 'array', 'max:3'],
            'order.*.column' => ['required_with:order', 'integer', 'min:0', 'max:'.(count($columns) - 1)],
            'order.*.dir' => ['required_with:order', Rule::in(['asc', 'desc'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $columnCount = count((array) $this->input('columns', []));

            foreach ((array) $this->input('order', []) as $order) {
                $index = is_array($order) ? ($order['column'] ?? null) : null;

                if ($columnCount > 0 && is_numeric($index) && (int) $index >= $columnCount) {
                    $validator->errors()->add('order', __('The selected ordering column is invalid.'));

                    return;
                }
            }
        });
    }

    /** @return array<string, string|null> */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'as_of_date' => $validated['as_of_date'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'status' => $validated['status'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];
    }

    /** @return list<string> */
    private function allowedColumns(): array
    {
        return [
            'contract_number',
            'customer_name',
            'branch_name',
            'status',
            'due_state',
            'start_date',
            'expected_end_date',
            'currency',
            'line_count',
            'open_item_count',
            'unbilled_line_count',
            'open_invoice_count',
            'total_billed_minor',
            'open_invoice_total_minor',
            'pending_damage_minor',
        ];
    }
}
