<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaultAccountId = Account::query()->where('code', '5100')->value('id');

        $categories = [
            [
                'code' => 'GENERAL_ADMIN',
                'name' => ['en' => 'General Administration', 'ar' => 'مصروفات إدارية عامة'],
                'requires_attachment' => false,
            ],
            [
                'code' => 'UTILITIES',
                'name' => ['en' => 'Utilities', 'ar' => 'مرافق وخدمات'],
                'requires_attachment' => true,
            ],
            [
                'code' => 'TRAVEL',
                'name' => ['en' => 'Travel & Transportation', 'ar' => 'انتقالات وسفر'],
                'requires_attachment' => true,
            ],
            [
                'code' => 'OFFICE_SUPPLIES',
                'name' => ['en' => 'Office Supplies', 'ar' => 'مستلزمات مكتبية'],
                'requires_attachment' => true,
            ],
            [
                'code' => 'MAINTENANCE',
                'name' => ['en' => 'Maintenance', 'ar' => 'صيانة'],
                'requires_attachment' => true,
            ],
        ];

        foreach ($categories as $category) {
            $existing = ExpenseCategory::query()->where('code', $category['code'])->first();

            ExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'id' => $existing?->id ?? (string) Str::uuid(),
                    'name' => $category['name'],
                    'default_expense_account_id' => $defaultAccountId,
                    'default_tax_code_id' => null,
                    'requires_attachment' => $category['requires_attachment'],
                    'is_active' => true,
                    'lock_version' => $existing?->lock_version ?? 1,
                ]
            );
        }
    }
}
