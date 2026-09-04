<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialRatiosReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', Rule::in(['single', 'trend'])],
            'period_id' => ['bail', 'nullable', 'uuid', 'exists:financial_period,id'],
            'period_ids' => ['nullable', 'array', 'max:24'],
            'period_ids.*' => ['bail', 'uuid', 'exists:financial_period,id'],
        ];
    }
}
