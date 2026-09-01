<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Report routes retain their existing permission middleware and gates.
        return true;
    }

    /**
     * Validate the shared query-string boundary before report services bind
     * values to PostgreSQL UUID/date columns.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'as_of_date' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'date_from' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'from_date' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],

            'customer_id' => ['bail', 'nullable', 'uuid', 'exists:customer,id'],
            'supplier_id' => ['bail', 'nullable', 'uuid', 'exists:supplier,id'],
            'product_id' => ['bail', 'nullable', 'uuid', 'exists:product,id'],
            'warehouse_id' => ['bail', 'nullable', 'uuid', 'exists:warehouse,id'],
            'bank_account_id' => ['bail', 'nullable', 'uuid', 'exists:bank_account,id'],
            'cash_account_id' => ['bail', 'nullable', 'uuid', 'exists:cash_account,id'],
            'period_id' => ['bail', 'nullable', 'uuid', 'exists:financial_period,id'],
            'category_id' => ['bail', 'nullable', 'uuid', 'exists:fixed_asset_category,id'],
            'tax_code_id' => ['bail', 'nullable', 'uuid', 'exists:tax_codes,id'],

            'status' => ['nullable', 'string', Rule::in($this->allowedStatuses())],
            'movement_type' => ['nullable', 'string', Rule::in([
                'receipt',
                'issue',
                'reversal',
                'scrap',
                'transfer_out',
                'transfer_in',
                'adjustment',
                'landed_cost',
            ])],
            'direction' => ['nullable', 'string', Rule::in(['all', 'incoming', 'outgoing'])],
            'disposal_type' => ['nullable', 'string', Rule::in(['sale', 'scrap', 'retirement'])],
            'type' => ['nullable', 'string', Rule::in(['all', 'output', 'input'])],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->query('currency');

        if (is_string($currency)) {
            $this->merge(['currency' => strtoupper(trim($currency))]);
        }
    }

    /** @return list<string> */
    private function allowedStatuses(): array
    {
        $routeName = (string) $this->route()?->getName();

        return match (true) {
            in_array($routeName, ['reports.sales-orders', 'reports.purchase-orders'], true) => [
                'draft', 'submitted', 'confirmed', 'cancelled',
            ],
            in_array($routeName, ['reports.customer-invoices', 'reports.supplier-bills'], true) => [
                'draft', 'submitted', 'approved', 'posted', 'cancelled',
            ],
            in_array($routeName, ['reports.delivery-notes', 'reports.goods-receipts'], true) => [
                'draft', 'confirmed', 'cancelled',
            ],
            $routeName === 'reports.bank-reconciliations' => [
                'draft', 'in_progress', 'reconciled',
            ],
            str_starts_with($routeName, 'reports.cheque-register') => [
                'draft', 'received', 'deposited', 'issued', 'cleared', 'bounced', 'returned', 'cancelled',
            ],
            str_starts_with($routeName, 'reports.fixed-asset-depreciation-runs'),
            str_starts_with($routeName, 'reports.fixed-asset-disposals') => [
                'posted', 'reversed',
            ],
            str_starts_with($routeName, 'reports.fixed-asset-depreciation') => [
                'planned', 'posted', 'reversed', 'skipped',
            ],
            str_starts_with($routeName, 'reports.fixed-asset-register'),
            str_starts_with($routeName, 'reports.fixed-asset-net-book-values') => [
                'draft', 'active', 'fully_depreciated', 'disposed',
            ],
            default => [
                'draft',
                'submitted',
                'approved',
                'confirmed',
                'posted',
                'active',
                'completed',
                'cancelled',
                'reversed',
                'planned',
                'skipped',
                'in_progress',
                'reconciled',
                'fully_depreciated',
                'disposed',
            ],
        };
    }
}
