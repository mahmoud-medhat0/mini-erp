<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use Illuminate\Database\Seeder;

class AccountCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'ASSET',
                'name' => ['en' => 'Asset', 'ar' => 'أصول'],
                'normal_balance' => 'debit',
                'statement_type' => 'balance_sheet',
                'is_contra' => false,
                'sort_order' => 10,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'LIABILITY',
                'name' => ['en' => 'Liability', 'ar' => 'التزامات'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'is_contra' => false,
                'sort_order' => 20,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EQUITY',
                'name' => ['en' => 'Equity', 'ar' => 'حقوق ملكية'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'is_contra' => false,
                'sort_order' => 30,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'REVENUE',
                'name' => ['en' => 'Revenue', 'ar' => 'إيرادات'],
                'normal_balance' => 'credit',
                'statement_type' => 'income_statement',
                'is_contra' => false,
                'sort_order' => 40,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EXPENSE',
                'name' => ['en' => 'Expense', 'ar' => 'مصروفات'],
                'normal_balance' => 'debit',
                'statement_type' => 'income_statement',
                'is_contra' => false,
                'sort_order' => 50,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_ASSET',
                'name' => ['en' => 'Contra Asset', 'ar' => 'أصول مقابلة'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'is_contra' => true,
                'sort_order' => 60,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_LIABILITY',
                'name' => ['en' => 'Contra Liability', 'ar' => 'خصم التزامات'],
                'normal_balance' => 'debit',
                'statement_type' => 'balance_sheet',
                'is_contra' => true,
                'sort_order' => 70,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_REVENUE',
                'name' => ['en' => 'Contra Revenue', 'ar' => 'مردودات مبيعات'],
                'normal_balance' => 'debit',
                'statement_type' => 'income_statement',
                'is_contra' => true,
                'sort_order' => 80,
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $cat) {
            AccountCategory::updateOrCreate(
                ['code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'normal_balance' => $cat['normal_balance'],
                    'statement_type' => $cat['statement_type'],
                    'is_contra' => $cat['is_contra'],
                    'sort_order' => $cat['sort_order'],
                    'is_system' => $cat['is_system'],
                    'is_active' => $cat['is_active'],
                ]
            );
        }
    }
}
