<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VatRegisterDataTableRequest extends FormRequest
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
            'from_date' => ['bail', 'required', 'date_format:Y-m-d'],
            'to_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'type' => ['nullable', 'string', Rule::in(['all', 'output', 'input'])],
            'tax_code_id' => ['bail', 'nullable', 'uuid', 'exists:tax_codes,id'],
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
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'type' => $validated['type'] ?? 'all',
            'tax_code_id' => $validated['tax_code_id'] ?? null,
        ];
    }

    /** @return list<string> */
    private function allowedColumns(): array
    {
        return [
            'document_date',
            'document_type',
            'document_number',
            'entity_name',
            'tax_category',
            'tax_code',
            'subtotal_minor',
            'tax_amount_minor',
            'gross_amount_minor',
        ];
    }
}
