<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\CashAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\FixedAssetCategory;
use App\Models\Product;
use App\Models\Project;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccountantAcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase20HandsOnAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountantAcceptanceSeeder::class);

        $this->superAdminUser = User::query()->where('email', 'accept.accountant@example.com')->first()
            ?? User::query()->firstOrFail();
    }

    public function test_product_acceptance_defect_log_exists_and_contains_required_sections_columns_and_definitions(): void
    {
        $logPath = dirname(base_path()).DIRECTORY_SEPARATOR.'PRODUCT_ACCEPTANCE_DEFECT_LOG.md';
        $this->assertFileExists($logPath, 'PRODUCT_ACCEPTANCE_DEFECT_LOG.md must exist in the repository root.');

        $content = (string) file_get_contents($logPath);

        // 1. Bilingual Purpose
        $this->assertStringContainsString('Purpose & Overview', $content, 'Defect log must contain English purpose heading.');
        $this->assertStringContainsString('الهدف ونظرة عامة', $content, 'Defect log must contain Arabic purpose heading.');

        // 2. Severities
        $requiredSeverities = ['Blocker', 'High', 'Medium', 'Low'];
        foreach ($requiredSeverities as $severity) {
            $this->assertStringContainsString($severity, $content, "Defect log must define severity: {$severity}");
        }

        // 3. Statuses
        $requiredStatuses = ['New', 'Confirmed', 'Fixed', 'Retest Passed', 'Deferred', 'Rejected'];
        foreach ($requiredStatuses as $status) {
            $this->assertStringContainsString($status, $content, "Defect log must define status: {$status}");
        }

        // 4. Required Columns
        $requiredColumns = [
            'ID',
            'Date',
            'Reporter',
            'Persona/Role',
            'Module/Page',
            'Route',
            'Severity',
            'Status',
            'Steps to Reproduce',
            'Expected Result',
            'Actual Result',
            'Evidence',
            'Fix Summary',
            'Retest Result',
            'Owner Sign-Off',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertStringContainsString($column, $content, "Defect log table must include column: {$column}");
        }

        // 5. Initial baseline row & no open defects claim
        $this->assertStringContainsString('No open defects', $content, 'Defect log must state no open defects are recorded yet in baseline.');
        $this->assertStringContainsString('Pending Live Owner Walkthrough', $content, 'Defect log must state owner sign-off is pending live owner walkthrough.');

        // 6. Deployment remains parked policy
        $this->assertStringContainsString('Deployment Remains Parked', $content, 'Defect log must state deployment remains parked.');
        $this->assertStringContainsString('PARKED', $content, 'Defect log must include PARKED deployment policy.');
    }

    public function test_owner_acceptance_execution_script_contains_15_step_walkthrough_and_safeguards(): void
    {
        $scriptPath = dirname(base_path()).DIRECTORY_SEPARATOR.'OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md';
        $this->assertFileExists($scriptPath, 'OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md must exist in the repository root.');

        $content = (string) file_get_contents($scriptPath);

        // Verify all 15 step headings exist
        for ($i = 1; $i <= 15; $i++) {
            $this->assertStringContainsString("Step {$i}:", $content, "Execution script must contain Step {$i} heading.");
        }

        // Verify Pre-Session Setup & Seeding
        $this->assertStringContainsString('AccountantAcceptanceSeeder', $content, 'Execution script must reference AccountantAcceptanceSeeder.');
        $this->assertStringContainsString('Sign-Off Form', $content, 'Execution script must contain Sign-Off Form.');
        $this->assertStringContainsString('Issue Classification Guidelines', $content, 'Execution script must contain Issue Classification Guidelines.');
        $this->assertStringContainsString('Production Operating Safeguards', $content, 'Execution script must contain Production Operating Safeguards.');
    }

    public function test_accountant_acceptance_seeder_runs_and_provides_walkthrough_baseline_fixtures(): void
    {
        // 1. User
        $this->assertNotNull($this->superAdminUser);
        $this->assertTrue((bool) $this->superAdminUser->is_active);

        // 2. Core Posting GL Accounts
        $requiredAccountCodes = [
            '1100' => 'Cash Clearing GL',
            '1110' => 'Bank Account GL',
            '1200' => 'AR Control GL',
            '1300' => 'VAT Input GL',
            '1400' => 'Inventory Asset GL',
            '2100' => 'AP Control GL',
            '2200' => 'VAT Output GL',
            '2300' => 'GRNI Clearing GL',
            '4100' => 'Sales Revenue GL',
            '4200' => 'Sales Returns GL',
            '5500' => 'COGS GL',
        ];

        foreach ($requiredAccountCodes as $code => $name) {
            $account = Account::query()->where('code', $code)->first();
            $this->assertNotNull($account, "GL Account {$code} ({$name}) must exist for walkthrough.");
        }

        // 3. Fiscal Year & Periods
        $currentYear = (int) date('Y');
        $fiscalYear = FiscalYear::query()->where('year', $currentYear)->first();
        $this->assertNotNull($fiscalYear, "Fiscal Year {$currentYear} must exist.");
        $this->assertEquals('open', $fiscalYear->status);

        $openPeriods = FinancialPeriod::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('status', ['open', 'reopened'])
            ->count();
        $this->assertGreaterThanOrEqual(1, $openPeriods, 'At least one open financial period must exist.');

        // 4. Operational Dimensions: Branches & Warehouses
        $branchHO = Branch::query()->where('code', 'ACC-HO')->first();
        $branchAlex = Branch::query()->where('code', 'ACC-ALX')->first();
        $this->assertNotNull($branchHO, 'Branch ACC-HO must exist.');
        $this->assertNotNull($branchAlex, 'Branch ACC-ALX must exist.');

        $whMain = Warehouse::query()->where('code', 'ACC-WH-MAIN')->first();
        $whAlex = Warehouse::query()->where('code', 'ACC-WH-ALX')->first();
        $this->assertNotNull($whMain, 'Warehouse ACC-WH-MAIN must exist.');
        $this->assertNotNull($whAlex, 'Warehouse ACC-WH-ALX must exist.');

        $locMain = StockLocation::query()->where('code', 'ACC-LOC-MAIN-01')->first();
        $locAlex = StockLocation::query()->where('code', 'ACC-LOC-ALX-01')->first();
        $this->assertNotNull($locMain, 'Location ACC-LOC-MAIN-01 must exist.');
        $this->assertNotNull($locAlex, 'Location ACC-LOC-ALX-01 must exist.');

        // 5. Customer & Supplier
        $customer = Customer::query()->where('code', 'ACC-CUST-001')->first();
        $supplier = Supplier::query()->where('code', 'ACC-SUPP-001')->first();
        $this->assertNotNull($customer, 'Customer ACC-CUST-001 must exist.');
        $this->assertNotNull($supplier, 'Supplier ACC-SUPP-001 must exist.');

        // 6. Products
        $stockProd = Product::query()->where('code', 'ACC-PRD-STOCK-01')->first();
        $servProd = Product::query()->where('code', 'ACC-PRD-SERV-01')->first();
        $nonStockProd = Product::query()->where('code', 'ACC-PRD-NONSTOCK-01')->first();
        $this->assertNotNull($stockProd, 'Stock product ACC-PRD-STOCK-01 must exist.');
        $this->assertNotNull($servProd, 'Service product ACC-PRD-SERV-01 must exist.');
        $this->assertNotNull($nonStockProd, 'Non-stock product ACC-PRD-NONSTOCK-01 must exist.');

        // 7. Tax & Treasury
        $taxCode = TaxCode::query()->where('code', 'VAT_STD_14')->first();
        $this->assertNotNull($taxCode, 'Tax code VAT_STD_14 must exist.');
        $this->assertTrue($taxCode->is_active);

        $cash = CashAccount::query()->where('code', 'ACC-CASH-01')->first();
        $bank = BankAccount::query()->where('code', 'ACC-BANK-01')->first();
        $this->assertNotNull($cash, 'Cash account ACC-CASH-01 must exist.');
        $this->assertNotNull($bank, 'Bank account ACC-BANK-01 must exist.');

        // 8. Projects, Cost Centers, Budgets, Fixed Assets, Payroll
        $prj = Project::query()->where('code', 'ACC-PRJ-01')->first();
        $cc = CostCenter::query()->where('code', 'ACC-CC-01')->first();
        $bdg = Budget::query()->where('code', 'ACC-BDG-2026')->first();
        $fac = FixedAssetCategory::query()->where('code', 'ACC-FAC-01')->first();
        $emp = Employee::query()->where('code', 'ACC-EMP-001')->first();

        $this->assertNotNull($prj, 'Project ACC-PRJ-01 must exist.');
        $this->assertNotNull($cc, 'Cost center ACC-CC-01 must exist.');
        $this->assertNotNull($bdg, 'Budget ACC-BDG-2026 must exist.');
        $this->assertNotNull($fac, 'Fixed asset category ACC-FAC-01 must exist.');
        $this->assertNotNull($emp, 'Employee ACC-EMP-001 must exist.');
    }

    public function test_representative_walkthrough_routes_load_for_super_admin(): void
    {
        $this->withoutVite();

        $walkthroughRoutes = [
            '/dashboard',
            '/accounting/coa',
            '/accounting/account-mappings',
            '/accounting/periods',
            '/purchasing/orders',
            '/purchasing/goods-receipts',
            '/purchasing/bills',
            '/supplier-payments',
            '/sales/orders',
            '/sales/delivery-notes',
            '/sales/invoices',
            '/sales/returns',
            '/sales/credit-notes',
            '/customer-receipts',
            '/accounting/trial-balance',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
            '/reports/vat-gl-reconciliation',
            '/reports/balance-sheet',
            '/reports/income-statement',
        ];

        foreach ($walkthroughRoutes as $route) {
            $response = $this->actingAs($this->superAdminUser)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Super Admin must receive 200 OK on walkthrough route [{$route}], got {$response->status()}."
            );
        }
    }

    public function test_representative_accountant_and_auditor_routes_load_for_authorized_personas(): void
    {
        $this->withoutVite();

        // 1. Accountant Persona
        $accountant = $this->createPersonaUser('ACCOUNTANT');

        $accountantAllowedRoutes = [
            '/dashboard',
            '/accounting/coa',
            '/accounting/account-mappings',
            '/accounting/periods',
            '/supplier-payments',
            '/customer-receipts',
            '/accounting/trial-balance',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
            '/reports/vat-gl-reconciliation',
            '/reports/balance-sheet',
            '/reports/income-statement',
        ];

        foreach ($accountantAllowedRoutes as $route) {
            $response = $this->actingAs($accountant)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Accountant persona must receive 200 OK on [{$route}], got {$response->status()}."
            );
        }

        $accountantForbiddenRoutes = [
            '/settings/company',
            '/payroll/runs',
        ];

        foreach ($accountantForbiddenRoutes as $route) {
            $response = $this->actingAs($accountant)->get($route);
            $this->assertEquals(
                403,
                $response->status(),
                "Accountant persona must receive 403 Forbidden on [{$route}], got {$response->status()}."
            );
        }

        // 2. Auditor Persona (Read-Only)
        $auditor = $this->createPersonaUser('AUDITOR');

        $auditorAllowedRoutes = [
            '/dashboard',
            '/accounting/trial-balance',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
            '/reports/vat-gl-reconciliation',
            '/reports/balance-sheet',
            '/reports/income-statement',
        ];

        foreach ($auditorAllowedRoutes as $route) {
            $response = $this->actingAs($auditor)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Auditor persona must receive 200 OK on read-only route [{$route}], got {$response->status()}."
            );
        }

        // Auditor cannot perform mutating post actions
        $response = $this->actingAs($auditor)->post('/accounting/journal', []);
        $this->assertEquals(403, $response->status(), 'Auditor persona must be forbidden from POST /accounting/journal');

        $response = $this->actingAs($auditor)->post('/sales/orders', []);
        $this->assertEquals(403, $response->status(), 'Auditor persona must be forbidden from POST /sales/orders');
    }

    public function test_guest_users_are_redirected_from_all_walkthrough_routes(): void
    {
        $walkthroughRoutes = [
            '/dashboard',
            '/accounting/coa',
            '/accounting/account-mappings',
            '/accounting/periods',
            '/purchasing/orders',
            '/purchasing/goods-receipts',
            '/purchasing/bills',
            '/supplier-payments',
            '/sales/orders',
            '/sales/delivery-notes',
            '/sales/invoices',
            '/sales/returns',
            '/sales/credit-notes',
            '/customer-receipts',
            '/accounting/trial-balance',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
            '/reports/vat-gl-reconciliation',
            '/reports/balance-sheet',
            '/reports/income-statement',
        ];

        foreach ($walkthroughRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->isRedirect(route('login')),
                "Guest user accessing walkthrough route [{$route}] must redirect to login, got status {$response->status()}."
            );
        }
    }

    public function test_no_forbidden_scope_assumptions_are_introduced_by_phase_20_slice_1(): void
    {
        $defectLogPath = dirname(base_path()).DIRECTORY_SEPARATOR.'PRODUCT_ACCEPTANCE_DEFECT_LOG.md';
        $this->assertFileExists($defectLogPath);
        $defectLogContent = (string) file_get_contents($defectLogPath);

        $forbiddenTerms = [
            'company_id',
            'tenant_id',
            'currentCompany',
            'currentTenant',
            'Spatie\Multitenancy',
            'spatie/laravel-multitenancy',
            'spatie/laravel-teams',
        ];

        foreach ($forbiddenTerms as $term) {
            $this->assertStringNotContainsString($term, $defectLogContent, "Defect log must not contain forbidden scope term: {$term}");
        }

        // Verify branch table has no tenancy columns
        $this->assertFalse(Schema::hasColumn('branch', 'company_id'), 'branch table must not contain company_id');
        $this->assertFalse(Schema::hasColumn('branch', 'tenant_id'), 'branch table must not contain tenant_id');
    }

    public function test_no_raw_secrets_are_stored_in_phase_20_files_and_seeders(): void
    {
        $filesToCheck = [
            dirname(base_path()).DIRECTORY_SEPARATOR.'PRODUCT_ACCEPTANCE_DEFECT_LOG.md',
            database_path('seeders/AccountantAcceptanceSeeder.php'),
            __FILE__,
        ];

        $forbiddenSecretPatterns = [
            '/api[_-]?key\s*=\s*[\'"][^\'"]+[\'"]/i',
            '/bearer\s+[A-Za-z0-9_\-\.]{20,}/i',
            '/bot[_-]?token\s*=\s*[\'"][^\'"]+[\'"]/i',
            '/telegram[_-]?bot/i',
            '/aws[_-]?secret/i',
        ];

        foreach ($filesToCheck as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) file_get_contents($filePath);

            foreach ($forbiddenSecretPatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $content,
                    "File [{$filePath}] must not contain secret matching {$pattern}"
                );
            }
        }
    }

    public function test_phase_20_slice_2_accountant_facing_pages_render_cleanly(): void
    {
        $this->withoutVite();

        $accountant = $this->createPersonaUser('ACCOUNTANT');

        $accountantPages = [
            '/accounting/journal',
            '/accounting/trial-balance',
            '/accounting/opening-balances',
            '/accounting/coa',
            '/accounting/ledger',
            '/reports/cheque-register',
            '/reports/customer-statement',
            '/reports/supplier-statement',
            '/reports/cash-book',
            '/reports/bank-book',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
            '/reports/vat-gl-reconciliation',
            '/reports/vat-register',
            '/reports/vat-summary',
            '/reports/ar-aging',
            '/reports/ap-aging',
            '/reports/bank-reconciliations',
        ];

        foreach ($accountantPages as $route) {
            $response = $this->actingAs($accountant)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Accountant facing page [{$route}] must render 200 OK without errors, got {$response->status()}."
            );
        }
    }

    public function test_phase_20_slice_2_general_journal_datatable_contract(): void
    {
        $this->withoutVite();

        $accountant = $this->createPersonaUser('ACCOUNTANT');

        $response = $this->actingAs($accountant)->get('/accounting/journal');
        $response->assertStatus(200);

        // The grid streams its own rows via /accounting/journal/data, so the
        // index page itself no longer carries an inline `journals` paginator.
        $page = $response->viewData('page');
        $this->assertIsArray($page, 'Inertia response must contain page payload');
        $props = $page['props'] ?? [];

        $this->assertArrayNotHasKey('journals', $props, 'General Journal no longer needs an inline journals prop');
        $this->assertArrayHasKey('periods', $props, 'General Journal must provide periods for its filters');

        $feed = $this->actingAs($accountant)->getJson('/accounting/journal/data');
        $feed->assertOk();
        $feed->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_phase_20_slice_2_dictionary_full_parity_and_new_keys(): void
    {
        $enPath = resource_path('js/locales/en.json');
        $arPath = resource_path('js/locales/ar.json');

        $this->assertFileExists($enPath);
        $this->assertFileExists($arPath);

        $en = json_decode((string) file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
        $ar = json_decode((string) file_get_contents($arPath), true, 512, JSON_THROW_ON_ERROR);

        $enKeys = $this->flattenArrayKeys($en);
        $arKeys = $this->flattenArrayKeys($ar);

        $missingInAr = array_diff($enKeys, $arKeys);
        $missingInEn = array_diff($arKeys, $enKeys);

        $this->assertEmpty($missingInAr, 'Keys in en.json missing from ar.json: '.implode(', ', $missingInAr));
        $this->assertEmpty($missingInEn, 'Keys in ar.json missing from en.json: '.implode(', ', $missingInEn));

        // Required newly added accountant friction keys
        $requiredKeys = [
            'app.actions.printVoucher',
            'app.accounting.statusCleared',
            'app.accounting.statusBounced',
            'app.accounting.statusReturned',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertContains($key, $enKeys, "en.json must contain key {$key}");
            $this->assertContains($key, $arKeys, "ar.json must contain key {$key}");
        }
    }

    public function test_phase_20_slice_2_no_unsafe_ui_controls_in_frontend(): void
    {
        $jsDir = resource_path('js');
        $this->assertDirectoryExists($jsDir);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($jsDir));
        $unsafePatterns = [
            '/<select[\s>]/i' => 'native <select>',
            '/<option[\s>]/i' => 'native <option>',
            '/type=["\']date["\']/i' => 'native type="date"',
            '/dangerouslySetInnerHTML/i' => 'dangerouslySetInnerHTML',
            '/window\.location\.href\s*=/i' => 'window.location.href assignment',
            '/\balert\s*\(/i' => 'browser alert dialog',
        ];

        $violations = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['ts', 'tsx', 'js', 'jsx'])) {
                continue;
            }

            $relativePath = str_replace($jsDir, '', $file->getPathname());
            $content = (string) file_get_contents($file->getPathname());

            foreach ($unsafePatterns as $pattern => $description) {
                if (preg_match($pattern, $content)) {
                    $violations[] = "File [{$relativePath}] contains unsafe control: {$description}";
                }
            }
        }

        $this->assertEmpty($violations, 'Unsafe UI control violations detected in frontend code: '.implode("\n", $violations));
    }

    public function test_phase_20_slice_2_financial_print_and_export_controls_require_complete_permissions(): void
    {
        $files = [
            resource_path('js/Pages/Accounting/GeneralLedger.tsx'),
            resource_path('js/Pages/Accounting/TrialBalance.tsx'),
            resource_path('js/Pages/Reports/ChequeRegister.tsx'),
            resource_path('js/Pages/Reports/BankBook.tsx'),
            resource_path('js/Pages/Reports/CashBook.tsx'),
            resource_path('js/Pages/Reports/CustomerStatement.tsx'),
            resource_path('js/Pages/Reports/SupplierStatement.tsx'),
            resource_path('js/Pages/Reports/ArAging.tsx'),
            resource_path('js/Pages/Reports/ApAging.tsx'),
            resource_path('js/Pages/Reports/ArGlReconciliation.tsx'),
            resource_path('js/Pages/Reports/ApGlReconciliation.tsx'),
            resource_path('js/Pages/Reports/BankReconciliation.tsx'),
            resource_path('js/Pages/Reports/BankReconciliationDetail.tsx'),
            resource_path('js/Pages/Reports/VatGlReconciliation.tsx'),
            resource_path('js/Pages/Reports/VatRegister.tsx'),
            resource_path('js/Pages/Reports/VatSummary.tsx'),
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                "can('reports.print') || can('view_financials')",
                $source,
                "Financial print controls in [{$file}] must not use permission OR logic."
            );
            $this->assertStringNotContainsString(
                "const canExport = can('reports.export');",
                $source,
                "Financial export controls in [{$file}] must include the financial-viewing gate where export exists."
            );
        }
    }

    public function test_phase_20_slice_3_acceptance_actions_are_permission_aware_and_confirmation_backed(): void
    {
        $receivableSettlements = (string) file_get_contents(resource_path('js/Pages/Sales/ReceivableSettlements.tsx'));
        $payableSettlements = (string) file_get_contents(resource_path('js/Pages/Purchasing/PayableSettlements.tsx'));
        $accountMappings = (string) file_get_contents(resource_path('js/Pages/Accounting/AccountMappings.tsx'));
        $chartOfAccounts = (string) file_get_contents(resource_path('js/Pages/Accounting/ChartOfAccounts.tsx'));
        $financialStatementMappings = (string) file_get_contents(resource_path('js/Pages/Accounting/FinancialStatementMappings.tsx'));
        $salesReturns = (string) file_get_contents(resource_path('js/Pages/Sales/SalesReturns.tsx'));

        foreach ([$receivableSettlements, $payableSettlements] as $source) {
            $this->assertStringContainsString('SensitiveActionModal', $source);
            $this->assertStringContainsString('reasonRequired={true}', $source);
            $this->assertStringContainsString('if (!canManageSettlements)', $source);
            $this->assertStringNotContainsString('alert(', $source);
        }

        $this->assertStringContainsString('confirmCode="REVERSE_RECEIVABLE_SETTLEMENT"', $receivableSettlements);
        $this->assertStringContainsString('confirmCode="REVERSE_PAYABLE_SETTLEMENT"', $payableSettlements);

        $this->assertStringContainsString("const canManageMappings = can('accounting.mappings') || can('settings.configure');", $accountMappings);
        $this->assertStringContainsString('disabled={!canManageMappings', $accountMappings);
        $this->assertStringContainsString('dict.app.actions.restricted', $accountMappings);

        $this->assertStringContainsString("const canManageCoa = can('accounting.create') || can('settings.configure');", $chartOfAccounts);
        $this->assertStringContainsString('canManageCoa ? (', $chartOfAccounts);

        $this->assertStringContainsString('pageError', $financialStatementMappings);
        $this->assertStringContainsString('setPageError(accDict.cannotDeleteSystemLine)', $financialStatementMappings);
        $this->assertStringContainsString('setPageError(accDict.cannotDeleteInUseLine)', $financialStatementMappings);
        $this->assertStringNotContainsString('alert(', $financialStatementMappings);

        $this->assertStringContainsString('canManageSalesReturns ? (', $salesReturns);
        $this->assertStringContainsString('formErrors.lines', $salesReturns);
        $this->assertStringContainsString('errors.reason', $salesReturns);
    }

    private function flattenArrayKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenArrayKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }

    private function createPersonaUser(string $roleName): User
    {
        /** @var User $user */
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user->assignRole($role);

        return $user;
    }
}
