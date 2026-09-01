<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CashBankBookDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $columns = $this->allowedColumns();
        $isCashBook = $this->isCashBook();

        return [
            'cash_account_id' => $isCashBook
                ? ['bail', 'required', 'uuid', 'exists:cash_account,id']
                : ['prohibited'],
            'bank_account_id' => $isCashBook
                ? ['prohibited']
                : ['bail', 'required', 'uuid', 'exists:bank_account,id'],
            'date_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
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

    /** @return array{account_id: string, date_from: string, date_to: string} */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'account_id' => (string) ($validated[$this->isCashBook() ? 'cash_account_id' : 'bank_account_id'] ?? ''),
            'date_from' => (string) ($validated['date_from'] ?? ''),
            'date_to' => (string) ($validated['date_to'] ?? ''),
        ];
    }

    /** @return list<string> */
    private function allowedColumns(): array
    {
        $columns = [
            'entry_date',
            'journal_number',
            'description',
            'debit_minor',
            'credit_minor',
            'balance_after_minor',
        ];

        if (! $this->isCashBook()) {
            array_splice($columns, 3, 0, ['is_reconciled']);
        }

        return $columns;
    }

    private function isCashBook(): bool
    {
        return $this->route()?->getName() === 'reports.cash-book.data';
    }
}
