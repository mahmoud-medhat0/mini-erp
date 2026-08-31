<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceMappingKeyConstraint([
            'ar_control',
            'ap_control',
            'opening_balance_offset',
            'cheques_under_collection',
            'cheques_payable',
            'sales_revenue',
            'purchase_expense',
            'inventory_asset',
            'grni_clearing',
            'cogs',
            'sales_returns',
            'inventory_return_variance',
            'inventory_scrap_loss',
            'purchase_returns_allowances',
            'output_tax_payable',
            'input_tax_receivable',
            'fixed_asset_cost',
            'accumulated_depreciation',
            'depreciation_expense',
            'fixed_asset_disposal_gain',
            'fixed_asset_disposal_loss',
            'fixed_asset_clearing',
            'inventory_adjustment_gain',
            'inventory_adjustment_loss',
            'prepaid_expense_asset',
            'accrued_expense_liability',
            'payroll_expense',
            'payroll_payable',
            'payroll_deductions_payable',
        ]);
    }

    public function down(): void
    {
        $this->replaceMappingKeyConstraint([
            'ar_control',
            'ap_control',
            'opening_balance_offset',
            'cheques_under_collection',
            'cheques_payable',
            'sales_revenue',
            'purchase_expense',
            'inventory_asset',
            'grni_clearing',
            'cogs',
            'sales_returns',
            'inventory_return_variance',
            'inventory_scrap_loss',
            'purchase_returns_allowances',
            'output_tax_payable',
            'input_tax_receivable',
            'fixed_asset_cost',
            'accumulated_depreciation',
            'depreciation_expense',
            'fixed_asset_disposal_gain',
            'fixed_asset_disposal_loss',
            'fixed_asset_clearing',
            'inventory_adjustment_gain',
            'inventory_adjustment_loss',
            'prepaid_expense_asset',
            'accrued_expense_liability',
        ]);
    }

    private function replaceMappingKeyConstraint(array $keys): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $quotedKeys = collect($keys)
            ->map(fn (string $key): string => "'{$key}'")
            ->implode(', ');

        DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
        DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ({$quotedKeys}))");
    }
};
