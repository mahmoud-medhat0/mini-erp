<?php

namespace Database\Seeders;

use App\Application\Support\BaseCurrencyResolver;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingAccountMapping;
use App\Models\AccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
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
            [
                'code' => '1300',
                'name' => ['en' => 'Input Tax Receivable', 'ar' => 'ضريبة المدخلات القابلة للاسترداد'],
                'type' => 'asset',
                'type_code' => 'ASSET_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1400',
                'name' => ['en' => 'Inventory Asset', 'ar' => 'أصول المخزون'],
                'type' => 'asset',
                'type_code' => 'ASSET_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1500',
                'name' => ['en' => 'Cheques Under Collection', 'ar' => 'شيكات تحت التحصيل'],
                'type' => 'asset',
                'type_code' => 'ASSET_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '2200',
                'name' => ['en' => 'Output Tax Payable', 'ar' => 'ضريبة المخرجات المستحقة'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '2300',
                'name' => ['en' => 'GRNI Clearing', 'ar' => 'مقاصة المشتريات غير المستلمة'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '2400',
                'name' => ['en' => 'Cheques Payable', 'ar' => 'شيكات الدفع'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '3200',
                'name' => ['en' => 'Opening Balance Offset', 'ar' => 'تعويض الرصيد الافتتاحي'],
                'type' => 'equity',
                'type_code' => 'EQUITY',
                'nature' => 'credit',
                'group_code' => '3000',
                'is_control' => false,
            ],
            [
                'code' => '4200',
                'name' => ['en' => 'Sales Returns & Allowances', 'ar' => 'مرتجعات وخصومات المبيعات'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'nature' => 'debit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '4300',
                'name' => ['en' => 'Rental Revenue', 'ar' => 'إيرادات الإيجار'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '4310',
                'name' => ['en' => 'Rental Damage Revenue', 'ar' => 'إيرادات أضرار الإيجار'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '4320',
                'name' => ['en' => 'Rental Late Fee Revenue', 'ar' => 'إيرادات غرامات تأخير الإيجار'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '4330',
                'name' => ['en' => 'Rental Other Revenue', 'ar' => 'إيرادات إيجار أخرى'],
                'type' => 'revenue',
                'type_code' => 'REVENUE_OPERATING',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '5200',
                'name' => ['en' => 'Inventory Return Variance', 'ar' => 'فرق مرتجعات المخزون'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '5300',
                'name' => ['en' => 'Inventory Scrap Loss', 'ar' => 'خسائر الهالك من المخزون'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '4920',
                'name' => ['en' => 'Inventory Adjustment Gain', 'ar' => 'أرباح تسوية المخزون'],
                'type' => 'revenue',
                'type_code' => 'INCOME_OTHER',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '5600',
                'name' => ['en' => 'Inventory Adjustment Loss', 'ar' => 'خسائر تسوية المخزون'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '5400',
                'name' => ['en' => 'Purchase Returns & Allowances', 'ar' => 'مرتجعات وخصومات المشتريات'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '5500',
                'name' => ['en' => 'Cost of Goods Sold', 'ar' => 'تكلفة البضاعة المباعة'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '1600',
                'name' => ['en' => 'Fixed Assets Cost', 'ar' => 'تكلفة الأصول الثابتة'],
                'type' => 'asset',
                'type_code' => 'ASSET_NON_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1690',
                'name' => ['en' => 'Accumulated Depreciation', 'ar' => 'مجمع الإهلاك'],
                'type' => 'asset',
                'type_code' => 'ASSET_NON_CURRENT',
                'nature' => 'credit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1699',
                'name' => ['en' => 'Fixed Asset Clearing', 'ar' => 'حساب وسيط أصول ثابتة'],
                'type' => 'asset',
                'type_code' => 'ASSET_NON_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '1800',
                'name' => ['en' => 'Prepaid Expenses', 'ar' => 'مصروفات مدفوعة مقدماً'],
                'type' => 'asset',
                'type_code' => 'ASSET_CURRENT',
                'nature' => 'debit',
                'group_code' => '1000',
                'is_control' => false,
            ],
            [
                'code' => '2500',
                'name' => ['en' => 'Accrued Expenses Payable', 'ar' => 'مصروفات مستحقة الدفع'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '2600',
                'name' => ['en' => 'Payroll Payable', 'ar' => 'مرتبات مستحقة الدفع'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '2610',
                'name' => ['en' => 'Payroll Deductions Payable', 'ar' => 'استقطاعات مرتبات مستحقة'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '2620',
                'name' => ['en' => 'Rental Deposits Liability', 'ar' => 'التزامات ودائع الإيجار'],
                'type' => 'liability',
                'type_code' => 'LIABILITY_CURRENT',
                'nature' => 'credit',
                'group_code' => '2000',
                'is_control' => false,
            ],
            [
                'code' => '4910',
                'name' => ['en' => 'Gain on Asset Disposal', 'ar' => 'أرباح استبعاد الأصول'],
                'type' => 'revenue',
                'type_code' => 'INCOME_OTHER',
                'nature' => 'credit',
                'group_code' => '4000',
                'is_control' => false,
            ],
            [
                'code' => '5250',
                'name' => ['en' => 'Depreciation Expense', 'ar' => 'مصروف الإهلاك'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '5910',
                'name' => ['en' => 'Loss on Asset Disposal', 'ar' => 'خسائر استبعاد الأصول'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OTHER',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
            [
                'code' => '5700',
                'name' => ['en' => 'Salaries and Wages Expense', 'ar' => 'مصروف الأجور والمرتبات'],
                'type' => 'expense',
                'type_code' => 'EXPENSE_OPERATING',
                'nature' => 'debit',
                'group_code' => '5000',
                'is_control' => false,
            ],
        ];

        $currency = app(BaseCurrencyResolver::class)->resolve();

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
                    'currency' => $currency,
                ]
            );
        }

        if (Schema::hasTable('accounting_account_mapping')) {
            $mappings = [
                'ar_control' => ['account_code' => '1200', 'description' => 'Default Accounts Receivable control account'],
                'ap_control' => ['account_code' => '2100', 'description' => 'Default Accounts Payable control account'],
                'opening_balance_offset' => ['account_code' => '3200', 'description' => 'Default opening balance offset account'],
                'cheques_under_collection' => ['account_code' => '1500', 'description' => 'Default cheques under collection account'],
                'cheques_payable' => ['account_code' => '2400', 'description' => 'Default cheques payable account'],
                'sales_revenue' => ['account_code' => '4100', 'description' => 'Default sales revenue account'],
                'sales_returns' => ['account_code' => '4200', 'description' => 'Default sales returns and allowances account'],
                'purchase_expense' => ['account_code' => '5100', 'description' => 'Default purchase expense account'],
                'purchase_returns_allowances' => ['account_code' => '5400', 'description' => 'Default purchase returns and allowances account'],
                'inventory_asset' => ['account_code' => '1400', 'description' => 'Default inventory asset account'],
                'grni_clearing' => ['account_code' => '2300', 'description' => 'Default GRNI clearing account'],
                'cogs' => ['account_code' => '5500', 'description' => 'Default cost of goods sold account'],
                'inventory_return_variance' => ['account_code' => '5200', 'description' => 'Default inventory return variance account'],
                'inventory_scrap_loss' => ['account_code' => '5300', 'description' => 'Default inventory scrap loss account'],
                'inventory_adjustment_gain' => ['account_code' => '4920', 'description' => 'Default inventory adjustment gain account'],
                'inventory_adjustment_loss' => ['account_code' => '5600', 'description' => 'Default inventory adjustment loss account'],
                'output_tax_payable' => ['account_code' => '2200', 'description' => 'Default output tax payable account'],
                'input_tax_receivable' => ['account_code' => '1300', 'description' => 'Default input tax receivable account'],
                'fixed_asset_cost' => ['account_code' => '1600', 'description' => 'Default fixed assets cost account'],
                'accumulated_depreciation' => ['account_code' => '1690', 'description' => 'Default accumulated depreciation account'],
                'depreciation_expense' => ['account_code' => '5250', 'description' => 'Default depreciation expense account'],
                'fixed_asset_disposal_gain' => ['account_code' => '4910', 'description' => 'Default gain on asset disposal account'],
                'fixed_asset_disposal_loss' => ['account_code' => '5910', 'description' => 'Default loss on asset disposal account'],
                'fixed_asset_clearing' => ['account_code' => '1699', 'description' => 'Default fixed asset clearing account'],
                'prepaid_expense_asset' => ['account_code' => '1800', 'description' => 'Default prepaid expenses asset account'],
                'accrued_expense_liability' => ['account_code' => '2500', 'description' => 'Default accrued expenses payable account'],
                'payroll_expense' => ['account_code' => '5700', 'description' => 'Default payroll salaries and wages expense account'],
                'payroll_payable' => ['account_code' => '2600', 'description' => 'Default payroll net payable account'],
                'payroll_deductions_payable' => ['account_code' => '2610', 'description' => 'Default payroll deductions payable account'],
                'rental_revenue' => ['account_code' => '4300', 'description' => 'Default rental revenue account'],
                'rental_damage_revenue' => ['account_code' => '4310', 'description' => 'Default rental damage revenue account'],
                'rental_late_fee_revenue' => ['account_code' => '4320', 'description' => 'Default rental late fee revenue account'],
                'rental_other_revenue' => ['account_code' => '4330', 'description' => 'Default rental other revenue account'],
                'rental_deposit_liability' => ['account_code' => '2620', 'description' => 'Default refundable rental deposits liability account'],
            ];

            foreach ($mappings as $key => $mappingData) {
                $account = Account::where('code', $mappingData['account_code'])->first();
                if (! $account) {
                    continue;
                }

                $existingMapping = AccountingAccountMapping::where('key', $key)->whereNull('branch_id')->first();
                AccountingAccountMapping::updateOrCreate(
                    ['key' => $key, 'branch_id' => null],
                    [
                        'account_id' => $account->id,
                        'description' => $existingMapping?->description ?? $mappingData['description'],
                        'is_system' => true,
                    ]
                );
            }

            $this->call(FinancialStatementLineSeeder::class);
        }
    }
}
