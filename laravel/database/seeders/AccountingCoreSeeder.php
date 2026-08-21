<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccountingCoreSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'code' => '1000',
                'name' => ['en' => 'Current Assets', 'ar' => 'الأصول المتداولة'],
                'type' => 'asset',
                'statement_section' => 'balance_sheet',
                'sort_order' => 1,
            ],
            [
                'code' => '2000',
                'name' => ['en' => 'Current Liabilities', 'ar' => 'الالتزامات المتداولة'],
                'type' => 'liability',
                'statement_section' => 'balance_sheet',
                'sort_order' => 2,
            ],
            [
                'code' => '3000',
                'name' => ['en' => 'Equity & Capital', 'ar' => 'حقوق الملكية ورأس المال'],
                'type' => 'equity',
                'statement_section' => 'balance_sheet',
                'sort_order' => 3,
            ],
            [
                'code' => '4000',
                'name' => ['en' => 'Operating Revenue', 'ar' => 'الإيرادات التشغيلية'],
                'type' => 'revenue',
                'statement_section' => 'income_statement',
                'sort_order' => 4,
            ],
            [
                'code' => '5000',
                'name' => ['en' => 'Operating Expenses', 'ar' => 'المصروفات التشغيلية'],
                'type' => 'expense',
                'statement_section' => 'income_statement',
                'sort_order' => 5,
            ],
        ];

        $createdGroups = [];
        foreach ($groups as $gData) {
            $createdGroups[$gData['code']] = AccountGroup::updateOrCreate(
                ['code' => $gData['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $gData['name'],
                    'type' => $gData['type'],
                    'statement_section' => $gData['statement_section'],
                    'sort_order' => $gData['sort_order'],
                ]
            );
        }

        $accounts = [
            [
                'code' => '1100',
                'name' => ['en' => 'Main Cash Clearing', 'ar' => 'الصندوق الرئيسي'],
                'type' => 'asset',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1200',
                'name' => ['en' => 'Accounts Receivable Control (AR)', 'ar' => 'مراقبة العملاء وحسابات المدينين'],
                'type' => 'asset',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => true,
                'allow_manual_posting' => false,
            ],
            [
                'code' => '2100',
                'name' => ['en' => 'Accounts Payable Control (AP)', 'ar' => 'مراقبة الموردين وحسابات الدائنين'],
                'type' => 'liability',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => true,
                'allow_manual_posting' => false,
            ],
            [
                'code' => '3100',
                'name' => ['en' => 'Retained Earnings', 'ar' => 'الأرباح المرحلة والمدورة'],
                'type' => 'equity',
                'nature' => 'credit',
                'group_code' => '3000',
                'is_control' => true,
                'allow_manual_posting' => false,
            ],
            [
                'code' => '4100',
                'name' => ['en' => 'Sales Revenue', 'ar' => 'إيرادات المبيعات والخدمات'],
                'type' => 'revenue',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '5100',
                'name' => ['en' => 'General & Administrative Expenses', 'ar' => 'المصاريف العمومية والإدارية'],
                'type' => 'expense',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
        ];

        foreach ($accounts as $aData) {
            Account::updateOrCreate(
                ['code' => $aData['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $aData['name'],
                    'type' => $aData['type'],
                    'nature' => $aData['nature'],
                    'account_group_id' => $createdGroups[$aData['group_code']]->id,
                    'is_control' => $aData['is_control'],
                    'allow_manual_posting' => $aData['allow_manual_posting'] ?? true,
                    'currency' => 'EGP',
                ]
            );
        }
    }
}
