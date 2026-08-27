<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PayrollComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PayrollComponentSeeder extends Seeder
{
    public function run(): void
    {
        $expenseAccount = Account::query()->where('code', '5700')->first();
        $deductionAccount = Account::query()->where('code', '2610')->first();

        $components = [
            [
                'code' => 'TRANSPORT_ALLOWANCE',
                'name' => ['en' => 'Transport Allowance', 'ar' => 'بدل انتقال'],
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount_minor' => 0,
                'rate_bps' => null,
                'expense_account_id' => $expenseAccount?->id,
                'liability_account_id' => null,
                'sort_order' => 20,
            ],
            [
                'code' => 'HOUSING_ALLOWANCE',
                'name' => ['en' => 'Housing Allowance', 'ar' => 'بدل سكن'],
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount_minor' => 0,
                'rate_bps' => null,
                'expense_account_id' => $expenseAccount?->id,
                'liability_account_id' => null,
                'sort_order' => 30,
            ],
            [
                'code' => 'PERCENT_ALLOWANCE',
                'name' => ['en' => 'Percent Allowance', 'ar' => 'بدل بنسبة من الأساسي'],
                'type' => 'earning',
                'calculation_type' => 'percent_of_base',
                'default_amount_minor' => null,
                'rate_bps' => 0,
                'expense_account_id' => $expenseAccount?->id,
                'liability_account_id' => null,
                'sort_order' => 40,
            ],
            [
                'code' => 'EMPLOYEE_DEDUCTION',
                'name' => ['en' => 'Employee Deduction', 'ar' => 'استقطاع موظف'],
                'type' => 'deduction',
                'calculation_type' => 'fixed',
                'default_amount_minor' => 0,
                'rate_bps' => null,
                'expense_account_id' => null,
                'liability_account_id' => $deductionAccount?->id,
                'sort_order' => 50,
            ],
            [
                'code' => 'PERCENT_DEDUCTION',
                'name' => ['en' => 'Percent Deduction', 'ar' => 'استقطاع بنسبة من الأساسي'],
                'type' => 'deduction',
                'calculation_type' => 'percent_of_base',
                'default_amount_minor' => null,
                'rate_bps' => 0,
                'expense_account_id' => null,
                'liability_account_id' => $deductionAccount?->id,
                'sort_order' => 60,
            ],
        ];

        foreach ($components as $data) {
            $existing = PayrollComponent::query()->where('code', $data['code'])->first();
            PayrollComponent::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'id' => $existing?->id ?? (string) Str::uuid(),
                    ...$data,
                    'is_system' => true,
                    'is_active' => true,
                    'lock_version' => $existing?->lock_version ?? 1,
                ]
            );
        }
    }
}
