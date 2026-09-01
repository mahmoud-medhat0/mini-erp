<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChequeRegisterDataTableRequest extends FormRequest
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
            'direction' => ['nullable', 'string', Rule::in(['all', 'incoming', 'outgoing'])],
            'status' => ['nullable', 'string', Rule::in([
                'draft',
                'received',
                'deposited',
                'issued',
                'cleared',
                'bounced',
                'returned',
                'cancelled',
            ])],
            'customer_id' => ['bail', 'nullable', 'uuid', 'exists:customer,id'],
            'supplier_id' => ['bail', 'nullable', 'uuid', 'exists:supplier,id'],
            'bank_account_id' => ['bail', 'nullable', 'uuid', 'exists:bank_account,id'],
            'date_from' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'currency' => ['bail', 'required', 'string', 'size:3', 'exists:currency,code'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns' => ['nullable', 'required_with:order', 'array', 'max:'.count($columns)],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in($columns)],
            'columns.*.name' => ['nullable', 'string', Rule::in($columns)],
            'columns.*.searchable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.orderable' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'order' => ['nullable', 'array', 'max:3'],
            'order.*.column' => ['required_with:order', 'integer', 'min:0', 'max:'.(count($columns) - 1)],
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
            'direction' => $validated['direction'] ?? 'all',
            'status' => $validated['status'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'currency' => $validated['currency'],
        ];
    }

    /** @return list<string> */
    private function allowedColumns(): array
    {
        return [
            'party_name',
            'cheque_number',
            'due_date',
            'bank_account_name',
            'status',
            'amount_minor',
        ];
    }
}
