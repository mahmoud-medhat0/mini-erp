<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(AccountCategorySeeder::class);

        $categoriesByCode = AccountCategory::all()->keyBy('code');

        $types = [
            [
                'code' => 'ASSET_CURRENT',
                'category_code' => 'ASSET',
                'name' => ['en' => 'Current Assets', 'ar' => 'الأصول المتداولة'],
                'normal_balance' => 'debit',
                'statement_type' => 'balance_sheet',
                'category' => 'asset',
                'is_contra' => false,
                'sort_order' => 10,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_NON_CURRENT',
                'category_code' => 'ASSET',
                'name' => ['en' => 'Non-Current Assets', 'ar' => 'الأصول غير المتداولة'],
                'normal_balance' => 'debit',
                'statement_type' => 'balance_sheet',
                'category' => 'asset',
                'is_contra' => false,
                'sort_order' => 20,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'LIABILITY_CURRENT',
                'category_code' => 'LIABILITY',
                'name' => ['en' => 'Current Liabilities', 'ar' => 'الالتزامات المتداولة'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'category' => 'liability',
                'is_contra' => false,
                'sort_order' => 30,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'LIABILITY_NON_CURRENT',
                'category_code' => 'LIABILITY',
                'name' => ['en' => 'Non-Current Liabilities', 'ar' => 'الالتزامات غير المتداولة'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'category' => 'liability',
                'is_contra' => false,
                'sort_order' => 40,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EQUITY',
                'category_code' => 'EQUITY',
                'name' => ['en' => 'Equity', 'ar' => 'حقوق الملكية'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'category' => 'equity',
                'is_contra' => false,
                'sort_order' => 50,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'REVENUE_OPERATING',
                'category_code' => 'REVENUE',
                'name' => ['en' => 'Operating Revenue', 'ar' => 'الإيرادات التشغيلية'],
                'normal_balance' => 'credit',
                'statement_type' => 'income_statement',
                'category' => 'revenue',
                'is_contra' => false,
                'sort_order' => 60,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EXPENSE_OPERATING',
                'category_code' => 'EXPENSE',
                'name' => ['en' => 'Operating Expenses', 'ar' => 'المصروفات التشغيلية'],
                'normal_balance' => 'debit',
                'statement_type' => 'income_statement',
                'category' => 'expense',
                'is_contra' => false,
                'sort_order' => 61,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EXPENSE_ADMIN',
                'category_code' => 'EXPENSE',
                'name' => ['en' => 'Administrative Expenses', 'ar' => 'المصروفات الإدارية والعمومية'],
                'normal_balance' => 'debit',
                'statement_type' => 'income_statement',
                'category' => 'expense',
                'is_contra' => false,
                'sort_order' => 62,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_ASSET',
                'category_code' => 'CONTRA_ASSET',
                'name' => ['en' => 'Accumulated Depreciation', 'ar' => 'مجمع الإهلاك'],
                'normal_balance' => 'credit',
                'statement_type' => 'balance_sheet',
                'category' => 'contra_asset',
                'is_contra' => true,
                'sort_order' => 64,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_LIABILITY',
                'category_code' => 'CONTRA_LIABILITY',
                'name' => ['en' => 'Discount on Liabilities', 'ar' => 'خصم الالتزامات'],
                'normal_balance' => 'debit',
                'statement_type' => 'balance_sheet',
                'category' => 'contra_liability',
                'is_contra' => true,
                'sort_order' => 65,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTRA_REVENUE',
                'category_code' => 'CONTRA_REVENUE',
                'name' => ['en' => 'Sales Returns', 'ar' => 'مردودات المبيعات'],
                'normal_balance' => 'debit',
                'statement_type' => 'income_statement',
                'category' => 'contra_revenue',
                'is_contra' => true,
                'sort_order' => 66,
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($types as $typeData) {
            $catCode = $typeData['category_code'];
            unset($typeData['category_code']);

            $categoryRecord = $categoriesByCode->get($catCode) ?? AccountCategory::where('code', $catCode)->first();
            $typeData['account_category_id'] = $categoryRecord?->id;
            if ($categoryRecord) {
                $typeData['category'] = strtolower($categoryRecord->code);
            }

            AccountType::updateOrCreate(
                ['code' => $typeData['code']],
                $typeData
            );
        }

        // Backfill any unlinked account_type rows by category string
        $allCategories = AccountCategory::all();
        foreach ($allCategories as $cat) {
            AccountType::whereNull('account_category_id')
                ->where('category', strtolower($cat->code))
                ->update(['account_category_id' => $cat->id]);
        }

        // Backfill existing account groups and accounts
        $this->backfillRelations();
    }

    private function backfillRelations(): void
    {
        $typeMap = [
            'asset' => AccountType::where('code', 'ASSET_CURRENT')->first()?->id,
            'liability' => AccountType::where('code', 'LIABILITY_CURRENT')->first()?->id,
            'equity' => AccountType::where('code', 'EQUITY')->first()?->id,
            'revenue' => AccountType::where('code', 'REVENUE_OPERATING')->first()?->id,
            'expense' => AccountType::where('code', 'EXPENSE_OPERATING')->first()?->id,
            'contra_asset' => AccountType::where('code', 'CONTRA_ASSET')->first()?->id,
            'contra_liability' => AccountType::where('code', 'CONTRA_LIABILITY')->first()?->id,
            'contra_revenue' => AccountType::where('code', 'CONTRA_REVENUE')->first()?->id,
        ];

        foreach ($typeMap as $stringCategory => $typeId) {
            if ($typeId) {
                AccountGroup::whereNull('account_type_id')
                    ->where('type', $stringCategory)
                    ->update(['account_type_id' => $typeId]);

                Account::whereNull('account_type_id')
                    ->where('type', $stringCategory)
                    ->update(['account_type_id' => $typeId]);
            }
        }
    }
}
