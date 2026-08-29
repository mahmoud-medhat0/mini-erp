<?php

namespace App\Support\Security;

class SensitiveActionRegistry
{
    /**
     * Map of sensitive route names to confirmation code, reason requirement, and description.
     *
     * @var array<string, array{confirmation_code: string, reason_required: bool, description: string}>
     */
    public const ACTIONS = [
        // Accounting
        'accounting.journal.post' => [
            'confirmation_code' => 'POST_JOURNAL_ENTRY',
            'reason_required' => false,
            'description' => 'Post journal entry to general ledger',
        ],
        'accounting.journal.reverse' => [
            'confirmation_code' => 'REVERSE_JOURNAL_ENTRY',
            'reason_required' => true,
            'description' => 'Reverse posted journal entry',
        ],
        'accounting.opening_balances.post' => [
            'confirmation_code' => 'POST_OPENING_BALANCES',
            'reason_required' => false,
            'description' => 'Post opening balance batch to general ledger',
        ],
        'accounting.periods.close' => [
            'confirmation_code' => 'CLOSE_FINANCIAL_PERIOD',
            'reason_required' => true,
            'description' => 'Close financial period',
        ],
        'accounting.periods.reopen' => [
            'confirmation_code' => 'REOPEN_FINANCIAL_PERIOD',
            'reason_required' => true,
            'description' => 'Reopen closed financial period',
        ],

        // Phase 3 AR/AP, Cash, Bank
        'customer-opening-balances.post' => [
            'confirmation_code' => 'POST_CUSTOMER_OPENING_BALANCE',
            'reason_required' => false,
            'description' => 'Post customer opening balance invoice to AR/GL',
        ],
        'supplier-opening-balances.post' => [
            'confirmation_code' => 'POST_SUPPLIER_OPENING_BALANCE',
            'reason_required' => false,
            'description' => 'Post supplier opening balance bill to AP/GL',
        ],
        'customer-receipts.post' => [
            'confirmation_code' => 'POST_CUSTOMER_RECEIPT',
            'reason_required' => false,
            'description' => 'Post customer receipt voucher to cash/bank and AR',
        ],
        'supplier-payments.post' => [
            'confirmation_code' => 'POST_SUPPLIER_PAYMENT',
            'reason_required' => false,
            'description' => 'Post supplier payment voucher to cash/bank and AP',
        ],
        'receivable-allocations.reverse' => [
            'confirmation_code' => 'REVERSE_RECEIVABLE_ALLOCATION',
            'reason_required' => true,
            'description' => 'Reverse customer payment/credit allocation',
        ],
        'payable-allocations.reverse' => [
            'confirmation_code' => 'REVERSE_PAYABLE_ALLOCATION',
            'reason_required' => true,
            'description' => 'Reverse supplier payment/debit allocation',
        ],
        'treasury-transfers.post' => [
            'confirmation_code' => 'POST_TREASURY_TRANSFER',
            'reason_required' => false,
            'description' => 'Post treasury fund transfer between cash/bank accounts',
        ],
        'bank-reconciliations.finalize' => [
            'confirmation_code' => 'FINALIZE_BANK_RECONCILIATION',
            'reason_required' => true,
            'description' => 'Finalize bank account reconciliation statement',
        ],

        // Phase 4 Sales, Purchasing, Inventory
        'landed-costs.post' => [
            'confirmation_code' => 'POST_LANDED_COST',
            'reason_required' => false,
            'description' => 'Post landed cost allocation to inventory cost layers',
        ],
        'customer-invoices.post' => [
            'confirmation_code' => 'POST_CUSTOMER_INVOICE',
            'reason_required' => false,
            'description' => 'Post customer invoice to AR, GL, and stock dispatch',
        ],
        'supplier-bills.post' => [
            'confirmation_code' => 'POST_SUPPLIER_BILL',
            'reason_required' => false,
            'description' => 'Post supplier bill to AP and GL expense/asset',
        ],
        'sales-returns.post' => [
            'confirmation_code' => 'POST_SALES_RETURN',
            'reason_required' => false,
            'description' => 'Post customer sales return to inventory and GL',
        ],
        'customer-credit-notes.post' => [
            'confirmation_code' => 'POST_CUSTOMER_CREDIT_NOTE',
            'reason_required' => false,
            'description' => 'Post customer credit note to AR and GL revenue reversal',
        ],
        'purchase-returns.post' => [
            'confirmation_code' => 'POST_PURCHASE_RETURN',
            'reason_required' => false,
            'description' => 'Post purchase return to inventory deduction and AP/GL',
        ],
        'supplier-adjustment-notes.post' => [
            'confirmation_code' => 'POST_SUPPLIER_ADJUSTMENT_NOTE',
            'reason_required' => false,
            'description' => 'Post supplier adjustment note to AP and GL adjustment',
        ],
        'receivable-settlements.reverse' => [
            'confirmation_code' => 'REVERSE_RECEIVABLE_SETTLEMENT',
            'reason_required' => true,
            'description' => 'Reverse customer invoice credit settlement',
        ],
        'payable-settlements.reverse' => [
            'confirmation_code' => 'REVERSE_PAYABLE_SETTLEMENT',
            'reason_required' => true,
            'description' => 'Reverse supplier bill adjustment settlement',
        ],
        'stock-transfers.issue' => [
            'confirmation_code' => 'ISSUE_STOCK_TRANSFER',
            'reason_required' => false,
            'description' => 'Issue stock transfer dispatch from source warehouse',
        ],
        'stock-transfers.receive' => [
            'confirmation_code' => 'RECEIVE_STOCK_TRANSFER',
            'reason_required' => false,
            'description' => 'Receive stock transfer arrival at destination warehouse',
        ],
        'stock-counts.post' => [
            'confirmation_code' => 'POST_STOCK_COUNT',
            'reason_required' => true,
            'description' => 'Post physical stock count variance reconciliation',
        ],
        'stock-adjustments.post' => [
            'confirmation_code' => 'POST_STOCK_ADJUSTMENT',
            'reason_required' => true,
            'description' => 'Post manual stock quantity and cost adjustment',
        ],

        // Phase 5/7/13/14/16
        'taxes.returns.file' => [
            'confirmation_code' => 'FILE_TAX_RETURN',
            'reason_required' => true,
            'description' => 'File statutory tax return for tax period',
        ],
        'payroll.runs.post' => [
            'confirmation_code' => 'POST_PAYROLL_RUN',
            'reason_required' => true,
            'description' => 'Post monthly employee payroll run to GL ledger',
        ],
        'rentals.invoices.post' => [
            'confirmation_code' => 'POST_RENTAL_INVOICE',
            'reason_required' => false,
            'description' => 'Post equipment rental billing invoice to AR/GL',
        ],
        'budgeting.budgets.activate' => [
            'confirmation_code' => 'ACTIVATE_BUDGET',
            'reason_required' => true,
            'description' => 'Activate approved fiscal budget for operational tracking',
        ],
        'budgeting.budgets.archive' => [
            'confirmation_code' => 'ARCHIVE_BUDGET',
            'reason_required' => true,
            'description' => 'Archive historical budget version',
        ],
        'budgeting.budgets.cancel' => [
            'confirmation_code' => 'CANCEL_BUDGET',
            'reason_required' => true,
            'description' => 'Cancel active or draft budget',
        ],

        // Phase 6 Fixed Assets
        'fixed-assets.capitalize' => [
            'confirmation_code' => 'CAPITALIZE_FIXED_ASSET',
            'reason_required' => true,
            'description' => 'Capitalize fixed asset into active service',
        ],
        'fixed-assets.reverse_capitalization' => [
            'confirmation_code' => 'REVERSE_FIXED_ASSET_CAPITALIZATION',
            'reason_required' => true,
            'description' => 'Reverse capitalized asset back to draft state',
        ],
        'fixed-assets.depreciation-runs.store' => [
            'confirmation_code' => 'STORE_FIXED_ASSET_DEPRECIATION_RUN',
            'reason_required' => true,
            'description' => 'Post asset depreciation run to general ledger',
        ],
        'fixed-assets.depreciation-runs.reverse' => [
            'confirmation_code' => 'REVERSE_FIXED_ASSET_DEPRECIATION_RUN',
            'reason_required' => true,
            'description' => 'Reverse posted asset depreciation run',
        ],
        'fixed-assets.disposals.store' => [
            'confirmation_code' => 'STORE_FIXED_ASSET_DISPOSAL',
            'reason_required' => true,
            'description' => 'Post fixed asset disposal and derecognition to GL',
        ],
        'fixed-assets-disposals.reverse' => [
            'confirmation_code' => 'REVERSE_FIXED_ASSET_DISPOSAL',
            'reason_required' => true,
            'description' => 'Reverse fixed asset disposal record',
        ],
    ];

    /**
     * @return array<string, array{confirmation_code: string, reason_required: bool, description: string}>
     */
    public static function all(): array
    {
        return self::ACTIONS;
    }

    /**
     * @return array{confirmation_code: string, reason_required: bool, description: string}|null
     */
    public static function get(string $routeName): ?array
    {
        return self::ACTIONS[$routeName] ?? null;
    }

    public static function has(string $routeName): bool
    {
        return isset(self::ACTIONS[$routeName]);
    }

    public static function getConfirmationCode(string $routeName): ?string
    {
        return self::ACTIONS[$routeName]['confirmation_code'] ?? null;
    }

    public static function isReasonRequired(string $routeName): bool
    {
        return self::ACTIONS[$routeName]['reason_required'] ?? false;
    }

    /**
     * @return list<string>
     */
    public static function routes(): array
    {
        return array_keys(self::ACTIONS);
    }
}
