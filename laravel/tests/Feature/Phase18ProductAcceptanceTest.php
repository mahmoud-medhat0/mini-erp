<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class Phase18ProductAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_projects_index_page_does_not_contain_dangerously_set_inner_html(): void
    {
        $filePath = resource_path('js/Pages/Projects/Index.tsx');
        $this->assertFileExists($filePath);

        $content = (string) file_get_contents($filePath);
        $this->assertStringNotContainsString(
            'dangerouslySetInnerHTML',
            $content,
            'Projects/Index.tsx must not contain dangerouslySetInnerHTML.'
        );
    }

    public function test_cost_centers_index_page_does_not_contain_dangerously_set_inner_html(): void
    {
        $filePath = resource_path('js/Pages/CostCenters/Index.tsx');
        $this->assertFileExists($filePath);

        $content = (string) file_get_contents($filePath);
        $this->assertStringNotContainsString(
            'dangerouslySetInnerHTML',
            $content,
            'CostCenters/Index.tsx must not contain dangerouslySetInnerHTML.'
        );
    }

    public function test_no_dangerously_set_inner_html_remains_anywhere_under_pages(): void
    {
        $pagesDir = resource_path('js/Pages');
        $this->assertDirectoryExists($pagesDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $scannedCount = 0;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['ts', 'tsx', 'js', 'jsx'], true)) {
                continue;
            }

            $scannedCount++;
            $content = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace(resource_path('js/Pages/'), '', $file->getPathname());

            $this->assertStringNotContainsString(
                'dangerouslySetInnerHTML',
                $content,
                "Page [{$relativePath}] contains dangerouslySetInnerHTML, which is strictly forbidden."
            );
        }

        $this->assertGreaterThan(20, $scannedCount, 'Expected to scan multiple React page files.');
    }

    public function test_pagination_primitive_does_not_use_dangerously_set_inner_html(): void
    {
        $primitivesPath = resource_path('js/Components/Primitives.tsx');
        $this->assertFileExists($primitivesPath);

        $content = (string) file_get_contents($primitivesPath);

        $this->assertStringContainsString('export function PaginationControls', $content);
        $this->assertStringContainsString('export function decodePaginationLabel', $content);
        $this->assertStringNotContainsString(
            'dangerouslySetInnerHTML',
            $content,
            'Primitives.tsx pagination controls must not use dangerouslySetInnerHTML.'
        );
    }

    public function test_projects_and_cost_centers_pages_use_pagination_controls_and_pagination_link_type(): void
    {
        $projectSource = (string) file_get_contents(resource_path('js/Pages/Projects/Index.tsx'));
        $costCenterSource = (string) file_get_contents(resource_path('js/Pages/CostCenters/Index.tsx'));

        $this->assertStringContainsString('PaginationControls', $projectSource);
        $this->assertStringContainsString('PaginationLink', $projectSource);
        $this->assertStringContainsString('<PaginationControls', $projectSource);
        $this->assertStringContainsString('links={projects.links}', $projectSource);
        $this->assertStringContainsString('total={projects.total}', $projectSource);

        $this->assertStringContainsString('PaginationControls', $costCenterSource);
        $this->assertStringContainsString('PaginationLink', $costCenterSource);
        $this->assertStringContainsString('<PaginationControls', $costCenterSource);
        $this->assertStringContainsString('links={costCenters.links}', $costCenterSource);
        $this->assertStringContainsString('total={costCenters.total}', $costCenterSource);
    }

    public function test_no_banned_unsafe_ui_controls_introduced_in_slice1_files(): void
    {
        $files = [
            resource_path('js/Pages/Projects/Index.tsx'),
            resource_path('js/Pages/CostCenters/Index.tsx'),
            resource_path('js/Components/Primitives.tsx'),
        ];

        $bannedTokens = ['<select', '<option', 'type="date"', "type='date'", 'window.location.href'];

        foreach ($files as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) file_get_contents($filePath);
            $fileName = basename($filePath);

            foreach ($bannedTokens as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $content,
                    "File [{$fileName}] contains banned unsafe UI token: [{$token}]."
                );
            }
        }
    }

    public function test_no_multi_tenant_or_company_scope_terms_introduced_in_slice1_files(): void
    {
        $slice1Files = [
            resource_path('js/Pages/Projects/Index.tsx'),
            resource_path('js/Pages/CostCenters/Index.tsx'),
            resource_path('js/Components/Primitives.tsx'),
        ];

        $bannedTerms = [
            'tenant_id',
            'company_id',
            'currentCompany',
            'currentTenant',
            'setTenant',
            'setCompany',
            'Spatie\\Multitenancy',
            'MultiTenant',
        ];

        foreach ($slice1Files as $filePath) {
            $content = (string) file_get_contents($filePath);
            $fileName = basename($filePath);

            foreach ($bannedTerms as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $content,
                    "Slice 1 file [{$fileName}] contains banned multi-tenancy term: [{$term}]."
                );
            }
        }
    }

    public function test_projects_and_cost_centers_inertia_endpoints_return_valid_pagination_structure(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.view', 'costCenters.view']);

        for ($i = 1; $i <= 5; $i++) {
            Project::query()->create([
                'code' => "PRJ-PAG-{$i}",
                'name' => ['en' => "Project {$i}"],
                'status' => 'active',
            ]);

            CostCenter::query()->create([
                'code' => "CC-PAG-{$i}",
                'name' => ['en' => "Cost Center {$i}"],
            ]);
        }

        $this->actingAs($user)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 5)
                ->has('projects.links')
                ->where('projects.total', 5)
                ->etc()
            );

        $this->actingAs($user)
            ->get('/cost-centers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CostCenters/Index')
                ->has('costCenters.data', 5)
                ->has('costCenters.links')
                ->where('costCenters.total', 5)
                ->etc()
            );
    }

    public function test_every_controller_under_app_http_controllers_is_within_150_lines_limit(): void
    {
        $controllersDir = app_path('Http/Controllers');
        $this->assertDirectoryExists($controllersDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllersDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $scannedCount = 0;
        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $scannedCount++;
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
            $lineCount = count($lines);
            $relativePath = str_replace(app_path('Http/Controllers/'), '', $file->getPathname());

            if ($lineCount > 150) {
                $violations[$relativePath] = $lineCount;
            }
        }

        $this->assertGreaterThan(50, $scannedCount, 'Expected to scan many controller files.');
        $this->assertEmpty(
            $violations,
            'The following controllers exceed the 150-line clean boundary limit: '.json_encode($violations, JSON_PRETTY_PRINT)
        );
    }

    public function test_controllers_do_not_contain_forbidden_heavy_query_or_csv_or_posting_math_fragments(): void
    {
        $controllersDir = app_path('Http/Controllers');
        $this->assertDirectoryExists($controllersDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllersDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $forbiddenPatterns = [
            'DB::table(' => 'Raw DB::table query composition is forbidden in controllers.',
            'DB::raw(' => 'Raw DB::raw expressions are forbidden in controllers.',
            'DB::statement(' => 'Direct DB statements are forbidden in controllers.',
            'DB::unprepared(' => 'Direct unprepared DB statements are forbidden in controllers.',
            'DB::insert(' => 'Direct DB insert queries are forbidden in controllers.',
            'DB::update(' => 'Direct DB update queries are forbidden in controllers.',
            'DB::delete(' => 'Direct DB delete queries are forbidden in controllers.',
            'fputcsv(' => 'Direct CSV formatting/looping is forbidden in controllers.',
            'fgetcsv(' => 'Direct CSV parsing is forbidden in controllers.',
            'fopen(' => 'Direct file stream handle opening is forbidden in controllers.',
            '->join(' => 'Direct table joins are forbidden in controllers.',
            '->leftJoin(' => 'Direct table joins are forbidden in controllers.',
            '->rightJoin(' => 'Direct table joins are forbidden in controllers.',
            '->crossJoin(' => 'Direct table joins are forbidden in controllers.',
            '->groupBy(' => 'Direct aggregation grouping is forbidden in controllers.',
            '->having(' => 'Direct query having clauses are forbidden in controllers.',
            'bcadd(' => 'Posting arithmetic math helpers are forbidden in controllers.',
            'bcmul(' => 'Posting arithmetic math helpers are forbidden in controllers.',
            'bcdiv(' => 'Posting arithmetic math helpers are forbidden in controllers.',
            'bcsub(' => 'Posting arithmetic math helpers are forbidden in controllers.',
        ];

        // Allowlisted exceptions: HealthCheckController DB ping
        $allowlist = [
            'HealthCheckController.php' => ['DB::select'],
        ];

        $scannedCount = 0;
        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $scannedCount++;
            $fileName = $file->getFilename();
            $relativePath = str_replace(app_path('Http/Controllers/'), '', $file->getPathname());
            $content = (string) file_get_contents($file->getPathname());

            foreach ($forbiddenPatterns as $pattern => $message) {
                if (str_contains($content, $pattern)) {
                    $violations[] = "{$relativePath}: {$message} Found pattern '{$pattern}'.";
                }
            }

            // Also check DB:: usage in non-allowlisted files
            if (! isset($allowlist[$fileName]) && str_contains($content, 'DB::')) {
                $violations[] = "{$relativePath}: Direct DB facade usage is forbidden in controllers.";
            }
        }

        $this->assertGreaterThan(50, $scannedCount);
        $this->assertEmpty(
            $violations,
            'Forbidden patterns found in controllers:'.PHP_EOL.implode(PHP_EOL, $violations)
        );
    }

    public function test_controllers_orchestration_patterns_and_delegation_integrity(): void
    {
        $controllersDir = app_path('Http/Controllers');
        $this->assertDirectoryExists($controllersDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllersDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $scannedCount = 0;
        $forbiddenLoops = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $scannedCount++;
            $relativePath = str_replace(app_path('Http/Controllers/'), '', $file->getPathname());
            $content = (string) file_get_contents($file->getPathname());

            // Controllers must not have business loop processing
            if (preg_match('/\b(foreach|while)\s*\(/i', $content)) {
                $forbiddenLoops[] = "{$relativePath} contains inline loop control structures (foreach/while).";
            }
        }

        $this->assertGreaterThan(50, $scannedCount);
        $this->assertEmpty(
            $forbiddenLoops,
            'Controllers containing business loops:'.PHP_EOL.implode(PHP_EOL, $forbiddenLoops)
        );
    }

    public function test_known_service_authorized_controllers_remain_thin_and_authorized(): void
    {
        $attachmentControllerPath = app_path('Http/Controllers/AttachmentController.php');
        $notificationControllerPath = app_path('Http/Controllers/NotificationController.php');

        $this->assertFileExists($attachmentControllerPath);
        $this->assertFileExists($notificationControllerPath);

        $attachmentLines = file($attachmentControllerPath, FILE_IGNORE_NEW_LINES);
        $notificationLines = file($notificationControllerPath, FILE_IGNORE_NEW_LINES);

        $this->assertLessThanOrEqual(100, count($attachmentLines), 'AttachmentController must remain thin (<= 100 lines).');
        $this->assertLessThanOrEqual(100, count($notificationLines), 'NotificationController must remain thin (<= 100 lines).');

        $attachmentContent = (string) file_get_contents($attachmentControllerPath);
        $notificationContent = (string) file_get_contents($notificationControllerPath);

        // AttachmentController uses AttachmentService, FormRequests, and session authorization
        $this->assertStringContainsString('AttachmentService', $attachmentContent);
        $this->assertStringContainsString('ListAttachmentRequest', $attachmentContent);
        $this->assertStringContainsString('StoreAttachmentRequest', $attachmentContent);
        $this->assertStringContainsString('$request->user()', $attachmentContent);

        // NotificationController uses NotificationService and user-scoped authorization
        $this->assertStringContainsString('NotificationService', $notificationContent);
        $this->assertStringContainsString('$request->user()->getAuthIdentifier()', $notificationContent);
    }

    public function test_no_multi_tenant_or_company_scope_terms_in_controllers(): void
    {
        $controllersDir = app_path('Http/Controllers');
        $this->assertDirectoryExists($controllersDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllersDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $bannedTerms = [
            'tenant_id',
            'company_id',
            'currentCompany',
            'currentTenant',
            'setTenant',
            'setCompany',
            'Spatie\\Multitenancy',
            'MultiTenant',
        ];

        $scannedCount = 0;
        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $scannedCount++;
            $relativePath = str_replace(app_path('Http/Controllers/'), '', $file->getPathname());
            $content = (string) file_get_contents($file->getPathname());

            foreach ($bannedTerms as $term) {
                if (str_contains($content, $term)) {
                    $violations[] = "{$relativePath} contains banned multi-tenancy term [{$term}].";
                }
            }
        }

        $this->assertGreaterThan(50, $scannedCount);
        $this->assertEmpty(
            $violations,
            'Controllers containing banned multi-tenancy terms:'.PHP_EOL.implode(PHP_EOL, $violations)
        );
    }

    public function test_product_acceptance_smoke_matrix_file_exists_and_contains_all_required_sections_in_ar_and_en(): void
    {
        $matrixPath = base_path('../PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md');
        $this->assertFileExists($matrixPath, 'PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md must exist in the repository root.');

        $content = (string) file_get_contents($matrixPath);

        // Verify bilingual sections
        $this->assertStringContainsString('# Mini ERP - Product Acceptance and Accountant Smoke Matrix', $content);
        $this->assertStringContainsString('# مصفوفة القبول النهائي واختبارات الدخان للمحاسبين وأصحاب الأعمال', $content);
        $this->assertStringContainsString('## English Section: Product Acceptance & Smoke Testing Matrix', $content);
        $this->assertStringContainsString('## القسم العربي: مصفوفة القبول النهائي واختبارات الدخان للمحاسبين', $content);

        // Verify required areas in English
        $requiredEnglishAreas = [
            'Authentication, Sessions & RBAC Governance',
            'Dashboard, Navigation & Diagnostic Baseline',
            'Company Settings, Branch Definitions & Numbering Sequences',
            'Chart of Accounts, Categories, Types, Currencies & FX Rates',
            'Fiscal Years, Periods, Opening Balances & Journal Lifecycle',
            'General Ledger, Trial Balance & Financial Statements',
            'Customers, Suppliers & AR/AP Opening Balances',
            'Receipts, Payments, Allocations, Cheques & Bank Reconciliation',
            'Products, Units of Measure, Warehouses & Stock Operations',
            'Sales Orders, Delivery Notes, Invoices, Returns & Credit Notes',
            'Purchase Orders, Goods Receipts, Bills, Returns, Adjustment Notes & Landed Costs',
            'VAT / Tax Codes, Rates, Periods, Filing & GL Reconciliation',
            'Fixed Assets, Capitalization, Depreciation & Disposals',
            'Expenses, Prepaid Amortization & Accrual Recognition',
            'Payroll Employees, Components, Runs & GL Posting',
            'Rentals: Items, Contracts, Handovers, Invoices & Returns',
            'Projects, Cost Centers, Budgets & Variance Analysis',
            'Attachments, Notifications & Audit Log Integrity',
            'Branch & Warehouse Operational Workflows (Non-Tenancy)',
            'Phase 17 Security Controls Verification',
        ];

        foreach ($requiredEnglishAreas as $area) {
            $this->assertStringContainsString(
                $area,
                $content,
                "Acceptance matrix is missing required English area: [{$area}]."
            );
        }

        // Verify required areas in Arabic
        $requiredArabicTerms = [
            'المصادقة والجلسات وإدارة الصلاحيات',
            'لوحة التحكم والتنقل والفحص التشخيصي',
            'إعدادات المنشأة، الفروع التشغيلية، والترقيم التلقائي',
            'دليل الحسابات، تصنيفات وأنواع الحسابات، العملات وأسعار الصرف',
            'السنوات والفترات المالية، الأرصدة الافتتاحية، ودورة قيود اليومية',
            'دفتر الأستاذ العام، ميزان المراجعة، والقوائم المالية الختامية',
            'العملاء والموردون والأرصدة الافتتاحية للمساعدين',
            'سندات القبض والصرف، التخصيصات، الشيكات، والتسوية البنكية',
            'المنتجات، وحدات القياس، المستودعات، وعمليات المخزون',
            'دورة المبيعات: أوامر البيع، أذون التسليم، الفواتير، المرتجعات، والإشعارات الدائنة',
            'دورة المشتريات: أوامر الشراء، أذون الاستلام، فواتير الموردين، وتكاليف الشحن',
            'ضريبة القيمة المضافة، الإقرارات الضريبية، ومطابقة الأستاذ العام',
            'الأصول الثابتة، الرأسمالية، جداول وإهلاك الأصول، واستبعاد الأصول',
            'المصروفات، المصروفات المدفوعة مقدماً، والمصروفات المستحقة',
            'الرواتب، بنود الأجور، مسيرات الرواتب، والترحيل للحسابات',
            'الإيجارات: المعدات، العقود، محاضر التسليم والإرجاع، والفواتير',
            'المشاريع، مراكز التكلفة، الموازنات التقديرية، وانحراف الموازنة',
            'المرفقات، الإشعارات، وسجل التدقيق والرقابة',
            'العمليات التشغيلية للفروع والمستودعات كأبعاد تشغيلية',
            'التحقق من الضوابط الأمنية المطبقة',
        ];

        foreach ($requiredArabicTerms as $term) {
            $this->assertStringContainsString(
                $term,
                $content,
                "Acceptance matrix is missing required Arabic term: [{$term}]."
            );
        }

        // Verify standard matrix column headers in both languages
        $this->assertStringContainsString('| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |', $content);
        $this->assertStringContainsString('| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |', $content);
        $this->assertStringContainsString('Owner / Head Accountant Sign-Off Block', $content);
        $this->assertStringContainsString('محضر اعتماد واستلام النظام من المالك والمحاسب القانوني', $content);
    }

    public function test_authenticated_super_admin_can_access_all_representative_inertia_pages(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        $endpoints = [
            // Dashboard & Settings
            '/dashboard' => 'Dashboard',
            '/settings' => 'Settings/Index',
            '/settings/company' => 'Settings/Company',
            '/settings/branches' => 'Settings/Branches',
            '/settings/numbering' => 'Settings/Numbering',
            '/settings/users' => 'Settings/Users',
            '/settings/branch-approval-rules' => 'Settings/BranchApprovalRules',
            '/notifications' => 'Notifications',
            '/audit-log' => 'AuditLog/Index',
            '/foundation' => 'Foundation',

            // Accounting Core
            '/accounting' => 'Accounting/Index',
            '/accounting/coa' => 'Accounting/ChartOfAccounts',
            '/accounting/journal' => 'Accounting/GeneralJournal',
            '/accounting/journal/create' => 'Accounting/JournalForm',
            '/accounting/ledger' => 'Accounting/GeneralLedger',
            '/accounting/trial-balance' => 'Accounting/TrialBalance',
            '/accounting/periods' => 'Accounting/Periods',
            '/accounting/opening-balances' => 'Accounting/OpeningBalances',
            '/accounting/fx-rates' => 'Accounting/ExchangeRates',
            '/accounting/currencies' => 'Accounting/Currencies',
            '/accounting/account-types' => 'Accounting/AccountTypes',
            '/accounting/account-categories' => 'Accounting/AccountCategories',
            '/accounting/statement-mappings' => 'Accounting/FinancialStatementMappings',
            '/accounting/account-mappings' => 'Accounting/AccountMappings',

            // AR / AP / Subledgers / Treasury
            '/customers' => 'Customers/Index',
            '/suppliers' => 'Suppliers/Index',
            '/cash-accounts' => 'CashAccounts/Index',
            '/bank-accounts' => 'BankAccounts/Index',
            '/treasury-transfers' => 'TreasuryTransfers/Index',
            '/customer-opening-balances' => 'CustomerOpeningBalances/Index',
            '/supplier-opening-balances' => 'SupplierOpeningBalances/Index',
            '/customer-receipts' => 'CustomerReceipts/Index',
            '/supplier-payments' => 'SupplierPayments/Index',
            '/receivable-allocations' => 'ReceivableAllocations/Index',
            '/payable-allocations' => 'PayableAllocations/Index',
            '/incoming-cheques' => 'IncomingCheques/Index',
            '/outgoing-cheques' => 'OutgoingCheques/Index',
            '/bank-reconciliations' => 'BankReconciliations/Index',

            // Catalog
            '/catalog/uoms' => 'Catalog/UnitsOfMeasure',
            '/catalog/categories' => 'Catalog/ProductCategories',
            '/catalog/products' => 'Catalog/Products',

            // Sales
            '/sales/orders' => 'Sales/SalesOrders',
            '/sales/delivery-notes' => 'Sales/DeliveryNotes',
            '/sales/invoices' => 'Sales/CustomerInvoices',
            '/sales/returns' => 'Sales/SalesReturns',
            '/sales/credit-notes' => 'Sales/CustomerCreditNotes',
            '/sales/invoice-revisions' => 'Sales/InvoiceRevisions',
            '/sales/receivable-settlements' => 'Sales/ReceivableSettlements',

            // Purchasing
            '/purchasing/orders' => 'Purchasing/PurchaseOrders',
            '/purchasing/goods-receipts' => 'Purchasing/GoodsReceipts',
            '/purchasing/bills' => 'Purchasing/SupplierBills',
            '/purchasing/landed-costs' => 'Purchasing/LandedCosts',
            '/purchasing/returns' => 'Purchasing/PurchaseReturns',
            '/purchasing/adjustment-notes' => 'Purchasing/SupplierAdjustmentNotes',
            '/purchasing/payable-settlements' => 'Purchasing/PayableSettlements',

            // Inventory
            '/inventory/stock-balances' => 'Inventory/StockBalances',
            '/inventory/warehouses' => 'Inventory/Warehouses',
            '/inventory/transfers' => 'Inventory/StockTransfers',
            '/inventory/stock-counts' => 'Inventory/StockCounts',
            '/inventory/adjustments' => 'Inventory/StockAdjustments',

            // Expenses
            '/expenses/categories' => 'Expenses/Categories',
            '/expenses' => 'Expenses/Index',
            '/expenses/prepaids' => 'Expenses/Prepaids',
            '/expenses/accruals' => 'Expenses/Accruals',

            // Payroll
            '/payroll/employees' => 'Payroll/Employees',
            '/payroll/components' => 'Payroll/Components',
            '/payroll/runs' => 'Payroll/Runs',

            // Rentals
            '/rentals/items' => 'Rentals/RentableItems',
            '/rentals/contracts' => 'Rentals/Contracts',
            '/rentals/invoices' => 'Rentals/Invoices',
            '/rentals/handovers' => 'Rentals/Handovers',
            '/rentals/returns' => 'Rentals/Returns',

            // Fixed Assets
            '/fixed-asset-categories' => 'FixedAssets/Categories',
            '/fixed-asset-locations' => 'FixedAssets/Locations',
            '/fixed-assets' => 'FixedAssets/Index',
            '/fixed-assets/create' => 'FixedAssets/Create',
            '/fixed-assets-depreciation-runs' => 'FixedAssets/DepreciationRuns/Index',
            '/fixed-assets-disposals' => 'FixedAssets/Disposals/Index',

            // Taxes
            '/taxes/codes' => 'Taxes/Codes/Index',
            '/taxes/rates' => 'Taxes/Rates/Index',
            '/taxes/periods' => 'Taxes/Periods/Index',

            // Projects & Cost Centers
            '/projects' => 'Projects/Index',
            '/cost-centers' => 'CostCenters/Index',

            // Budgeting
            '/budgeting/budgets' => 'Budgeting/Budgets',
            '/budgeting/variance' => 'Budgeting/Variance',

            // Reports Hub & Reports
            '/reports' => 'Reports/Index',
            '/reports/customer-statement' => 'Reports/CustomerStatement',
            '/reports/supplier-statement' => 'Reports/SupplierStatement',
            '/reports/ar-aging' => 'Reports/ArAging',
            '/reports/ap-aging' => 'Reports/ApAging',
            '/reports/cash-book' => 'Reports/CashBook',
            '/reports/bank-book' => 'Reports/BankBook',
            '/reports/cheque-register' => 'Reports/ChequeRegister',
            '/reports/bank-reconciliations' => 'Reports/BankReconciliation',
            '/reports/ar-gl-reconciliation' => 'Reports/ArGlReconciliation',
            '/reports/ap-gl-reconciliation' => 'Reports/ApGlReconciliation',
            '/reports/balance-sheet' => 'Reports/BalanceSheet',
            '/reports/income-statement' => 'Reports/IncomeStatement',
            '/reports/cash-flow' => 'Reports/CashFlow',
            '/reports/vat-register' => 'Reports/VatRegister',
            '/reports/vat-summary' => 'Reports/VatSummary',
            '/reports/vat-gl-reconciliation' => 'Reports/VatGlReconciliation',
            '/reports/fixed-asset-register' => 'Reports/FixedAssetRegisterReport',
            '/reports/fixed-asset-net-book-values' => 'Reports/FixedAssetNetBookValueReport',
            '/reports/fixed-asset-depreciation' => 'Reports/FixedAssetDepreciationReport',
            '/reports/fixed-asset-depreciation-runs' => 'Reports/FixedAssetDepreciationRunReport',
            '/reports/fixed-asset-disposals' => 'Reports/FixedAssetDisposalReport',
            '/reports/sales-orders' => 'Reports/SalesOrdersReport',
            '/reports/purchase-orders' => 'Reports/PurchaseOrdersReport',
            '/reports/delivery-notes' => 'Reports/DeliveryNotesReport',
            '/reports/goods-receipts' => 'Reports/GoodsReceiptsReport',
            '/reports/customer-invoices' => 'Reports/CustomerInvoicesReport',
            '/reports/supplier-bills' => 'Reports/SupplierBillsReport',
            '/reports/stock-movements' => 'Reports/StockMovementsReport',
            '/reports/branch-operations' => 'Reports/BranchOperations',
            '/reports/branch-profitability' => 'Reports/BranchProfitability',
            '/reports/project-profitability' => 'Reports/ProjectProfitability',
            '/reports/cost-center-actuals' => 'Reports/CostCenterActuals',
            '/reports/rentals' => 'Reports/RentalOperationsReport',
        ];

        $testedCount = 0;

        foreach ($endpoints as $uri => $expectedComponent) {
            $testedCount++;
            $response = $this->actingAs($user)->get($uri);

            $this->assertEquals(
                200,
                $response->status(),
                "Endpoint [{$uri}] failed with status {$response->status()}."
            );

            $response->assertInertia(fn (Assert $page) => $page
                ->component($expectedComponent)
                ->etc()
            );
        }

        $this->assertGreaterThanOrEqual(75, $testedCount, 'Expected to smoke test >= 75 representative Inertia endpoints.');
    }

    public function test_unauthenticated_guests_are_redirected_to_login(): void
    {
        $sampleProtectedRoutes = [
            '/dashboard',
            '/accounting',
            '/sales/invoices',
            '/purchasing/bills',
            '/inventory/stock-balances',
            '/reports',
            '/payroll/runs',
            '/settings',
        ];

        foreach ($sampleProtectedRoutes as $route) {
            $this->get($route)->assertRedirect('/login');
        }
    }
}
