<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FinancialStatementLine;
use Illuminate\Database\Seeder;

class FinancialStatementLineSeeder extends Seeder
{
    public function run(): void
    {
        $lines = [
            [
                'code' => 'ASSET_CURRENT',
                'statement_type' => 'balance_sheet',
                'cash_flow_activity' => 'operating',
                'section_code' => 'current_assets',
                'name' => ['en' => 'Current Assets', 'ar' => 'أصول متداولة'],
                'normal_balance' => 'debit',
                'sort_order' => 10,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_NON_CURRENT',
                'statement_type' => 'balance_sheet',
                'cash_flow_activity' => 'investing',
                'section_code' => 'non_current_assets',
                'name' => ['en' => 'Non-Current Assets', 'ar' => 'أصول غير متداولة'],
                'normal_balance' => 'debit',
                'sort_order' => 20,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'LIABILITY_CURRENT',
                'statement_type' => 'balance_sheet',
                'cash_flow_activity' => 'operating',
                'section_code' => 'current_liabilities',
                'name' => ['en' => 'Current Liabilities', 'ar' => 'التزامات متداولة'],
                'normal_balance' => 'credit',
                'sort_order' => 30,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'LIABILITY_NON_CURRENT',
                'statement_type' => 'balance_sheet',
                'cash_flow_activity' => 'financing',
                'section_code' => 'non_current_liabilities',
                'name' => ['en' => 'Non-Current Liabilities', 'ar' => 'التزامات غير متداولة'],
                'normal_balance' => 'credit',
                'sort_order' => 40,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EQUITY',
                'statement_type' => 'balance_sheet',
                'cash_flow_activity' => 'financing',
                'section_code' => 'equity',
                'name' => ['en' => 'Equity', 'ar' => 'حقوق الملكية'],
                'normal_balance' => 'credit',
                'sort_order' => 50,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'REVENUE',
                'statement_type' => 'income_statement',
                'cash_flow_activity' => 'operating',
                'section_code' => 'revenue',
                'name' => ['en' => 'Revenue', 'ar' => 'الإيرادات التشغيلية'],
                'normal_balance' => 'credit',
                'sort_order' => 60,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_REVENUE',
                'statement_type' => 'income_statement',
                'cash_flow_activity' => 'operating',
                'section_code' => 'contra_revenue',
                'name' => ['en' => 'Sales Returns & Allowances', 'ar' => 'مردودات ومسموحات المبيعات'],
                'normal_balance' => 'debit',
                'sort_order' => 70,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'COGS',
                'statement_type' => 'income_statement',
                'cash_flow_activity' => 'operating',
                'section_code' => 'cogs',
                'name' => ['en' => 'Cost of Goods Sold', 'ar' => 'تكلفة البضاعة المباعة'],
                'normal_balance' => 'debit',
                'sort_order' => 80,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EXPENSE_OPERATING',
                'statement_type' => 'income_statement',
                'cash_flow_activity' => 'operating',
                'section_code' => 'operating_expenses',
                'name' => ['en' => 'Operating Expenses', 'ar' => 'المصروفات التشغيلية'],
                'normal_balance' => 'debit',
                'sort_order' => 90,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'INCOME_OTHER',
                'statement_type' => 'income_statement',
                'cash_flow_activity' => 'operating',
                'section_code' => 'other_income',
                'name' => ['en' => 'Other Income', 'ar' => 'إيرادات أخرى'],
                'normal_balance' => 'credit',
                'sort_order' => 100,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EXPENSE_OTHER',
                'statement_type' => 'income_statement',
                'cash_flow_activity' => 'operating',
                'section_code' => 'other_expenses',
                'name' => ['en' => 'Other Expenses', 'ar' => 'مصروفات أخرى'],
                'normal_balance' => 'debit',
                'sort_order' => 110,
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        $createdLines = [];
        foreach ($lines as $lineData) {
            $createdLines[$lineData['code']] = FinancialStatementLine::updateOrCreate(
                ['code' => $lineData['code']],
                $lineData
            );
        }

        // Auto-assign obvious starter accounts to statement lines if not assigned
        $assignments = [
            'ASSET_CURRENT' => ['1010', '1020', '1100', '1110', '1200', '1300', '1400', '1500', '1800'],
            'ASSET_NON_CURRENT' => ['1590', '1600', '1690', '1699'],
            'LIABILITY_CURRENT' => ['2100', '2200', '2300', '2400', '2500', '2600', '2610', '2620'],
            'EQUITY' => ['3000', '3100', '3200', '3900'],
            'REVENUE' => ['4100', '4300', '4310', '4320', '4330'],
            'CONTRA_REVENUE' => ['4200'],
            'COGS' => ['5000', '5200', '5300', '5400', '5500'],
            'EXPENSE_OPERATING' => ['5100', '5250', '5600', '5700'],
            'INCOME_OTHER' => ['4910', '4920'],
            'EXPENSE_OTHER' => ['5910'],
        ];

        foreach ($assignments as $lineCode => $accountCodes) {
            $line = $createdLines[$lineCode] ?? null;
            if ($line) {
                Account::whereIn('code', $accountCodes)
                    ->whereNull('financial_statement_line_id')
                    ->update(['financial_statement_line_id' => $line->id]);
            }
        }
    }
}
