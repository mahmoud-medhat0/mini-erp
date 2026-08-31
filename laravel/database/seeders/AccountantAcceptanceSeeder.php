<?php

namespace Database\Seeders;

use App\Application\Accounting\PeriodService;
use App\Application\Support\BaseCurrencyResolver;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\CashAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\FinancialStatementLine;
use App\Models\FiscalYear;
use App\Models\FixedAssetCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AccountantAcceptanceSeeder extends Seeder
{
    /**
     * Run the accountant acceptance data pack seeder idempotently.
     */
    public function run(): void
    {
        // 1. Ensure core master seeders are loaded first
        $this->call([
            CurrencySeeder::class,
            RbacSeeder::class,
            PermissionSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
            AccountingCoreSeeder::class,
            FinancialStatementLineSeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
            WarehouseSeeder::class,
            TaxCodeSeeder::class,
            ExpenseCategorySeeder::class,
            PayrollComponentSeeder::class,
        ]);

        // 2. Acceptance User (random locked credential, no plaintext secret, no existing password changes)
        $acceptanceUser = User::query()->where('email', 'accept.accountant@example.com')->first();
        if (! $acceptanceUser) {
            $acceptanceUser = User::query()->first();
        }
        if (! $acceptanceUser) {
            $acceptanceUser = User::query()->create([
                'name' => 'Acceptance Lead Accountant',
                'email' => 'accept.accountant@example.com',
                'password' => Hash::make(Str::random(64)),
                'locale' => 'en',
                'theme' => 'system',
                'is_active' => true,
                'mfa_enabled' => false,
            ]);
        }

        $superAdminRole = Role::query()
            ->where('name', 'SUPER_ADMIN')
            ->where('guard_name', config('erp_rbac.guard', 'web'))
            ->first();

        if ($superAdminRole && ! $acceptanceUser->hasRole($superAdminRole)) {
            $acceptanceUser->assignRole($superAdminRole);
        }

        // 3. Resolve Base Currency and Standard GL Accounts
        $currency = app(BaseCurrencyResolver::class)->resolve();
        $currentAssetsGroup = AccountGroup::query()->where('code', '1000')->first();
        $assetCurrentType = AccountType::query()->where('code', 'ASSET_CURRENT')->first()
            ?? AccountType::query()->where('category', 'asset')->first();

        $assetCurrentLine = FinancialStatementLine::query()->where('code', 'ASSET_CURRENT')->first();
        $bankGlExisting = Account::query()->where('code', '1110')->first();
        $bankGlAccount = Account::query()->updateOrCreate(
            ['code' => '1110'],
            [
                'id' => $bankGlExisting?->id ?? (string) Str::uuid(),
                'name' => ['en' => 'Acceptance Operating Bank Account GL', 'ar' => 'حساب الأستاذ العام للبنك التشغيلي للاعتماد'],
                'account_type_id' => $assetCurrentType?->id,
                'financial_statement_line_id' => $assetCurrentLine?->id,
                'type' => 'asset',
                'nature' => 'debit',
                'account_group_id' => $currentAssetsGroup?->id,
                'is_control' => false,
                'allow_manual_posting' => true,
                'currency' => $currency,
            ]
        );

        $cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();

        // 4. Ensure an Open Fiscal Year and Monthly Periods
        $currentYear = (int) date('Y');
        $fiscalYear = FiscalYear::query()->where('year', $currentYear)->first();
        if (! $fiscalYear) {
            $periodService = app(PeriodService::class);
            $fiscalYear = $periodService->createFiscalYear(
                $currentYear,
                "{$currentYear}-01-01",
                "{$currentYear}-12-31"
            );
        }

        $openPeriod = FinancialPeriod::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('status', ['open', 'reopened'])
            ->orderBy('month')
            ->first();

        if (! $openPeriod) {
            $openPeriod = FinancialPeriod::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->orderBy('month')
                ->first();

            if ($openPeriod) {
                $openPeriod->update(['status' => 'open']);
            }
        }

        // 5. Operational Branches (Strictly operational & reporting dimensions, not tenant scopes)
        $branchHO = Branch::query()->updateOrCreate(
            ['code' => 'ACC-HO'],
            [
                'name' => ['en' => 'Acceptance Head Office Branch', 'ar' => 'فرع الإدارة العامة للاعتماد'],
                'is_active' => true,
            ]
        );

        $branchAlex = Branch::query()->updateOrCreate(
            ['code' => 'ACC-ALX'],
            [
                'name' => ['en' => 'Acceptance Alexandria Branch', 'ar' => 'فرع الإسكندرية للاعتماد'],
                'is_active' => true,
            ]
        );

        // 6. Warehouses and Stock Locations
        $warehouseMain = Warehouse::query()->updateOrCreate(
            ['code' => 'ACC-WH-MAIN'],
            [
                'name' => ['en' => 'Acceptance Central Warehouse', 'ar' => 'مستودع الاعتماد المركزي'],
                'branch_id' => $branchHO->id,
                'warehouse_type' => 'standard',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        $warehouseAlex = Warehouse::query()->updateOrCreate(
            ['code' => 'ACC-WH-ALX'],
            [
                'name' => ['en' => 'Acceptance Alexandria Warehouse', 'ar' => 'مستودع الإسكندرية للاعتماد'],
                'branch_id' => $branchAlex->id,
                'warehouse_type' => 'standard',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        StockLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouseMain->id, 'code' => 'ACC-LOC-MAIN-01'],
            [
                'name' => ['en' => 'Main Receiving Bay', 'ar' => 'منطقة الاستلام الرئيسية'],
                'location_type' => 'standard',
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        StockLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouseAlex->id, 'code' => 'ACC-LOC-ALX-01'],
            [
                'name' => ['en' => 'Alexandria Aisle 1', 'ar' => 'ممر الإسكندرية 1'],
                'location_type' => 'standard',
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        // 7. Customer & Supplier Master Data
        Customer::query()->updateOrCreate(
            ['code' => 'ACC-CUST-001'],
            [
                'name' => ['en' => 'Acceptance Prime Commercial Customer', 'ar' => 'عميل الاعتماد التجاري الرئيسي'],
                'status' => 'active',
                'email' => 'customer@accept.local',
                'phone' => '+201000000001',
                'address' => 'Cairo Commercial District, Building 10',
                'tax_number' => 'TRN-100-200-300',
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        Supplier::query()->updateOrCreate(
            ['code' => 'ACC-SUPP-001'],
            [
                'name' => ['en' => 'Acceptance Global Wholesale Supplier', 'ar' => 'مورد الاعتماد الدولي للجملة'],
                'status' => 'active',
                'email' => 'supplier@accept.local',
                'phone' => '+201000000002',
                'address' => 'Alexandria Industrial Zone, Sector 4',
                'tax_number' => 'TRN-900-800-700',
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        // 8. Products: Stock, Service, and Non-Stock
        $pcsUom = UnitOfMeasure::query()->where('code', 'PCS')->first() ?? UnitOfMeasure::query()->first();
        $fgCategory = ProductCategory::query()->where('code', 'FG')->first() ?? ProductCategory::query()->first();
        $servCategory = ProductCategory::query()->where('code', 'SERVICES')->first() ?? ProductCategory::query()->first();

        Product::query()->updateOrCreate(
            ['code' => 'ACC-PRD-STOCK-01'],
            [
                'name' => ['en' => 'Acceptance Physical Finished Good', 'ar' => 'منتج تام مادي للاعتماد'],
                'description' => ['en' => 'Acceptance test inventory stock product', 'ar' => 'منتج مخزني لاختبارات الاعتماد'],
                'type' => 'stock',
                'unit_of_measure_id' => $pcsUom?->id,
                'product_category_id' => $fgCategory?->id,
                'status' => 'active',
                'is_sales_enabled' => true,
                'is_purchase_enabled' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        Product::query()->updateOrCreate(
            ['code' => 'ACC-PRD-SERV-01'],
            [
                'name' => ['en' => 'Acceptance Implementation Consulting Service', 'ar' => 'خدمة استشارية وتنفيذية للاعتماد'],
                'description' => ['en' => 'Acceptance test service product', 'ar' => 'خدمة استشارية لاختبارات الاعتماد'],
                'type' => 'service',
                'unit_of_measure_id' => $pcsUom?->id,
                'product_category_id' => $servCategory?->id,
                'status' => 'active',
                'is_sales_enabled' => true,
                'is_purchase_enabled' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        Product::query()->updateOrCreate(
            ['code' => 'ACC-PRD-NONSTOCK-01'],
            [
                'name' => ['en' => 'Acceptance Annual Software License', 'ar' => 'ترخيص برنامج سنوي للاعتماد'],
                'description' => ['en' => 'Acceptance test non-stock product', 'ar' => 'منتج غير مخزني لاختبارات الاعتماد'],
                'type' => 'non_stock',
                'unit_of_measure_id' => $pcsUom?->id,
                'product_category_id' => $servCategory?->id,
                'status' => 'active',
                'is_sales_enabled' => true,
                'is_purchase_enabled' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        // 9. VAT / Tax Codes (Ensure Standard 14% is active)
        $stdTaxCode = TaxCode::query()->where('code', 'VAT_STD_14')->first();
        if ($stdTaxCode && ! $stdTaxCode->is_active) {
            $stdTaxCode->update(['is_active' => true]);
        }

        // 10. Cash and Bank Accounts
        CashAccount::query()->updateOrCreate(
            ['code' => 'ACC-CASH-01'],
            [
                'name' => ['en' => 'Acceptance Central Cash Safe', 'ar' => 'خزينة النقدية الرئيسية للاعتماد'],
                'branch_id' => $branchHO->id,
                'gl_account_id' => $cashGlAccount->id,
                'currency' => $currency,
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        BankAccount::query()->updateOrCreate(
            ['code' => 'ACC-BANK-01'],
            [
                'name' => ['en' => 'Acceptance Commercial Bank Account', 'ar' => 'حساب البنك التجاري للاعتماد'],
                'bank_name' => ['en' => 'National Commercial Bank', 'ar' => 'البنك التجاري الوطني'],
                'branch_id' => $branchHO->id,
                'account_number' => 'EG990001000200030004',
                'iban' => 'EG9900010002000300040005',
                'swift' => 'NCBKEGCX',
                'gl_account_id' => $bankGlAccount->id,
                'currency' => $currency,
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        // 11. Projects, Cost Centers, and Budget Fixtures
        Project::query()->updateOrCreate(
            ['code' => 'ACC-PRJ-01'],
            [
                'name' => ['en' => 'Acceptance Digital Transformation Project', 'ar' => 'مشروع التحول الرقمي للاعتماد'],
                'description' => 'Acceptance test project for multi-dimensional tracking',
                'status' => 'active',
                'start_date' => "{$currentYear}-01-01",
                'end_date' => "{$currentYear}-12-31",
                'is_billable' => true,
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        CostCenter::query()->updateOrCreate(
            ['code' => 'ACC-CC-01'],
            [
                'name' => ['en' => 'Acceptance Corporate Operations Cost Center', 'ar' => 'مركز تكلفة العمليات العامة للاعتماد'],
                'description' => 'Acceptance test cost center for operational overhead',
                'category' => 'operations',
                'is_active' => true,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );

        Budget::query()->updateOrCreate(
            ['code' => 'ACC-BDG-2026'],
            [
                'fiscal_year_id' => $fiscalYear->id,
                'version_code' => 'V1',
                'name' => ['en' => "Acceptance Annual Operating Budget {$currentYear}", 'ar' => "الموازنة التقديرية التشغيلية السنوية {$currentYear}"],
                'description' => 'Acceptance test baseline annual operating budget',
                'status' => 'approved',
                'default_currency' => $currency,
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'approved_by' => $acceptanceUser->id,
                'approved_at' => now(),
                'lock_version' => 1,
            ]
        );

        // 12. Supporting Fixed Asset Categories and Employee Fixtures
        FixedAssetCategory::query()->updateOrCreate(
            ['code' => 'ACC-FAC-01'],
            [
                'name' => ['en' => 'Acceptance IT Equipment & Hardware', 'ar' => 'أجهزة ومعدات تكنولوجيا المعلومات للاعتماد'],
                'useful_life_months' => 36,
                'salvage_value_minor' => 0,
                'is_active' => true,
            ]
        );

        Employee::query()->updateOrCreate(
            ['code' => 'ACC-EMP-001'],
            [
                'name' => ['en' => 'Acceptance Lead Senior Accountant', 'ar' => 'محاسب أول للاعتماد'],
                'branch_id' => $branchHO->id,
                'status' => 'active',
                'hire_date' => "{$currentYear}-01-01",
                'currency' => $currency,
                'base_salary_minor' => 1500000,
                'payment_method' => 'bank',
                'notes' => 'Acceptance fixture employee record',
                'created_by' => $acceptanceUser->id,
                'updated_by' => $acceptanceUser->id,
                'lock_version' => 1,
            ]
        );
    }
}
