<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
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
                'type_code' => 'ASSET_CURRENT',
                'statement_section' => 'balance_sheet',
                'sort_order' => 1,
            ],
            [
                'code' => '2000',
                'name' => ['en' => 'Current Liabilities', 'ar' => 'الالتزامات المتداولة'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'statement_section' => 'balance_sheet',
                'sort_order' => 2,
            ],
            [
                'code' => '3000',
                'name' => ['en' => 'Equity & Capital', 'ar' => 'حقوق الملكية ورأس المال'],
                'type' => 'equity',
                'type_code' => 'EQUITY',
                'statement_section' => 'balance_sheet',
                'sort_order' => 3,
            ],
            [
                'code' => '4000',
                'name' => ['en' => 'Operating Revenue', 'ar' => 'الإيرادات التشغيلية'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'statement_section' => 'income_statement',
                'sort_order' => 4,
            ],
            [
                'code' => '5000',
                'name' => ['en' => 'Operating Expenses', 'ar' => 'المصروفات التشغيلية'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'statement_section' => 'income_statement',
                'sort_order' => 5,
            ],
        ];

        $createdGroups = [];
        foreach ($groups as $gData) {
            $accountType = AccountType::where('code', $gData['type_code'])->first()
                ?? AccountType::where('category', $gData['type'])->first();

            $existing = AccountGroup::where('code', $gData['code'])->first();
            $createdGroups[$gData['code']] = AccountGroup::updateOrCreate(
                ['code' => $gData['code']],
                [
                    'id' => $existing?->id ?? (string) Str::uuid(),
                    'name' => $gData['name'],
                    'account_type_id' => $accountType?->id,
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
                'type_code' => 'ASSET_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1200',
                'name' => ['en' => 'Accounts Receivable Control (AR)', 'ar' => 'مراقبة العملاء وحسابات المدينين'],
                'type' => 'asset',
                'type_code' => 'ASSET_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => true,
                'allow_manual_posting' => false,
            ],
            [
                'code' => '2100',
                'name' => ['en' => 'Accounts Payable Control (AP)', 'ar' => 'مراقبة الموردين وحسابات الدائنين'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => true,
                'allow_manual_posting' => false,
            ],
            [
                'code' => '3100',
                'name' => ['en' => 'Retained Earnings', 'ar' => 'الأرباح المرحلة والمدورة'],
                'type' => 'equity',
                'type_code' => 'EQUITY',
                'nature' => 'credit',
                'group_code' => '3000',
                'is_control' => true,
                'allow_manual_posting' => false,
            ],
            [
                'code' => '4100',
                'name' => ['en' => 'Sales Revenue', 'ar' => 'إيرادات المبيعات والخدمات'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '5100',
                'name' => ['en' => 'General & Administrative Expenses', 'ar' => 'المصاريف العمومية والإدارية'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
        ];

        foreach ($accounts as $aData) {
            $accountType = AccountType::where('code', $aData['type_code'])->first()
                ?? AccountType::where('category', $aData['type'])->first();

            $existingAccount = Account::where('code', $aData['code'])->first();
            Account::updateOrCreate(
                ['code' => $aData['code']],
                [
                    'id' => $existingAccount?->id ?? (string) Str::uuid(),
                    'name' => $aData['name'],
                    'account_type_id' => $accountType?->id,
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
