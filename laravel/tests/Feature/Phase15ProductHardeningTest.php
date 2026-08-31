<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountCategoryPageData;
use App\Application\Accounting\AccountingAccountMappingPageData;
use App\Application\Accounting\AccountingOverviewPageData;
use App\Application\Accounting\AccountTypePageData;
use App\Application\Accounting\BankReconciliationPageData;
use App\Application\Accounting\ChartOfAccountsPageData;
use App\Application\Accounting\CurrencyPageData;
use App\Application\Accounting\CustomerOpeningBalancePageData;
use App\Application\Accounting\CustomerReceiptPageData;
use App\Application\Accounting\ExchangeRatePageData;
use App\Application\Accounting\ExchangeRateService;
use App\Application\Accounting\FinancialPeriodPageData;
use App\Application\Accounting\FinancialStatementMappingPageData;
use App\Application\Accounting\GeneralLedgerPageData;
use App\Application\Accounting\IncomingChequePageData;
use App\Application\Accounting\JournalPageData;
use App\Application\Accounting\OpeningBalancePageData;
use App\Application\Accounting\OutgoingChequePageData;
use App\Application\Accounting\PayableAllocationPageData;
use App\Application\Accounting\PayableEntrySettlementPageData;
use App\Application\Accounting\ReceivableAllocationPageData;
use App\Application\Accounting\ReceivableEntrySettlementPageData;
use App\Application\Accounting\SupplierOpeningBalancePageData;
use App\Application\Accounting\SupplierPaymentPageData;
use App\Application\Accounting\TreasuryTransferPageData;
use App\Application\Approvals\BranchApprovalRuleService;
use App\Application\Audit\AuditLogQueryService;
use App\Application\Catalog\ProductCategoryPageData;
use App\Application\Catalog\ProductPageData;
use App\Application\Catalog\UnitOfMeasurePageData;
use App\Application\Dashboard\DashboardPageData;
use App\Application\Expenses\AccrualSchedulePageData;
use App\Application\Expenses\ExpenseCategoryPageData;
use App\Application\Expenses\ExpensePageData;
use App\Application\Expenses\PrepaidSchedulePageData;
use App\Application\FixedAssets\FixedAssetDepreciationRunPageData;
use App\Application\FixedAssets\FixedAssetDisposalPageData;
use App\Application\FixedAssets\FixedAssetLocationPageData;
use App\Application\FixedAssets\FixedAssetPageData;
use App\Application\Inventory\StockAdjustmentPageData;
use App\Application\Inventory\StockBalancePageData;
use App\Application\Inventory\StockCountPageData;
use App\Application\Inventory\StockTransferPageData;
use App\Application\Inventory\WarehousePageData;
use App\Application\MasterData\BankAccountPageData;
use App\Application\MasterData\CashAccountPageData;
use App\Application\MasterData\CustomerPageData;
use App\Application\MasterData\SupplierPageData;
use App\Application\Payroll\PayrollComponentPageData;
use App\Application\Payroll\PayrollEmployeePageData;
use App\Application\Payroll\PayrollRunPageData;
use App\Application\Purchasing\GoodsReceiptPageData;
use App\Application\Purchasing\LandedCostAllocationPageData;
use App\Application\Purchasing\PurchaseOrderPageData;
use App\Application\Purchasing\PurchaseReturnPageData;
use App\Application\Purchasing\SupplierAdjustmentNotePageData;
use App\Application\Purchasing\SupplierBillPageData;
use App\Application\Rentals\RentableItemPageData;
use App\Application\Rentals\RentalContractPageData;
use App\Application\Rentals\RentalHandoverPageData;
use App\Application\Rentals\RentalInvoicePageData;
use App\Application\Rentals\RentalReturnPageData;
use App\Application\Reports\ArApCsvReportExporter;
use App\Application\Reports\BranchProfitabilityCsvExporter;
use App\Application\Reports\CashBankBookCsvExporter;
use App\Application\Reports\ChequeRegisterCsvExporter;
use App\Application\Reports\CsvReportResponse;
use App\Application\Reports\FinancialPeriodReportOptions;
use App\Application\Reports\FinancialStatementCsvExporter;
use App\Application\Reports\FixedAssetCsvReportExporter;
use App\Application\Reports\PartnerStatementCsvExporter;
use App\Application\Reports\RentalOperationsCsvExporter;
use App\Application\Reports\RentalOperationsReportPageData;
use App\Application\Reports\ReportCurrencyResolver;
use App\Application\Reports\ReportPageOptions;
use App\Application\Reports\VatCsvReportExporter;
use App\Application\Reports\VatReportPageData;
use App\Application\Sales\CustomerCreditNotePageData;
use App\Application\Sales\CustomerInvoicePageData;
use App\Application\Sales\CustomerInvoiceRevisionPageData;
use App\Application\Sales\DeliveryNotePageData;
use App\Application\Sales\SalesOrderPageData;
use App\Application\Sales\SalesReturnPageData;
use App\Application\Settings\BranchSettingsService;
use App\Application\Settings\CompanySettingsService;
use App\Application\Settings\NumberingSettingsService;
use App\Application\Settings\RoleSettingsService;
use App\Application\Settings\SuperAdminProtection;
use App\Application\Settings\UserRoleAssignmentService;
use App\Application\Settings\UserSettingsService;
use App\Application\Support\CurrencyInput;
use App\Application\Taxes\TaxCodePageData;
use App\Application\Taxes\TaxPeriodPageData;
use App\Application\Taxes\TaxRatePageData;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use SplFileInfo;
use Tests\TestCase;

class Phase15ProductHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports.view', 'view_financials'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_report_hub_and_sensitive_reports_require_financial_visibility(): void
    {
        $this->withoutVite();

        $reportsOnly = User::factory()->create();
        $reportsOnly->givePermissionTo('reports.view');

        foreach ($this->sensitiveReportPaths() as $path => $component) {
            $this->actingAs($reportsOnly)
                ->get($path)
                ->assertForbidden();
        }

        $financialReporter = User::factory()->create();
        $financialReporter->givePermissionTo(['reports.view', 'view_financials']);

        foreach ($this->sensitiveReportPaths() as $path => $component) {
            $this->actingAs($financialReporter)
                ->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component)->etc());
        }
    }

    public function test_cleaned_report_pages_use_dictionary_backed_arabic_text(): void
    {
        foreach ([
            'Reports/SalesOrdersReport.tsx',
            'Reports/PurchaseOrdersReport.tsx',
            'Reports/CustomerInvoicesReport.tsx',
            'Reports/SupplierBillsReport.tsx',
        ] as $relativePath) {
            $source = file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertDoesNotMatchRegularExpression(
                '/[\x{0600}-\x{06FF}]/u',
                $source,
                "{$relativePath} must not contain hardcoded Arabic UI text."
            );
        }
    }

    public function test_operational_report_pages_use_shared_filter_panel_with_visible_currency_filter(): void
    {
        $this->withoutVite();
        $this->seed(CurrencySeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'view_financials']);

        foreach ([
            '/reports/sales-orders' => [
                'component' => 'Reports/SalesOrdersReport',
                'source' => 'Reports/SalesOrdersReport.tsx',
                'controller' => 'SalesOrderReportController.php',
            ],
            '/reports/purchase-orders' => [
                'component' => 'Reports/PurchaseOrdersReport',
                'source' => 'Reports/PurchaseOrdersReport.tsx',
                'controller' => 'PurchaseOrderReportController.php',
            ],
            '/reports/customer-invoices' => [
                'component' => 'Reports/CustomerInvoicesReport',
                'source' => 'Reports/CustomerInvoicesReport.tsx',
                'controller' => 'CustomerInvoiceReportController.php',
            ],
            '/reports/supplier-bills' => [
                'component' => 'Reports/SupplierBillsReport',
                'source' => 'Reports/SupplierBillsReport.tsx',
                'controller' => 'SupplierBillReportController.php',
            ],
        ] as $path => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$case['source']}"));
            $controllerSource = (string) file_get_contents(app_path("Http/Controllers/Reports/{$case['controller']}"));

            $this->assertStringContainsString('ReportFilterPanel', $source);
            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString('pageDict.allCurrencies', $source);
            $this->assertStringContainsString('pageDict.clearFilters', $source);
            $this->assertStringContainsString('activeFilterCount', $source);
            $this->assertStringContainsString('currencies: Array<{ code: string }>', $source);
            $this->assertStringContainsString('const statusOptions', $source);
            $this->assertStringContainsString('const productOptions', $source);
            $this->assertMatchesRegularExpression('/const (customerOptions|supplierOptions)/', $source);
            $this->assertStringContainsString("onChange={(value) => setCurrency(value || '')}", $source);
            $this->assertStringNotContainsString('<select', $source, "{$case['source']} should use shared searchable report filter controls.");
            $this->assertStringContainsString("'currencies' => \$this->options->currencies(columns: ['code']),", $controllerSource);

            $this->actingAs($user)
                ->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($case['component'])
                    ->has('currencies')
                    ->etc()
                );
        }
    }

    public function test_inventory_report_pages_use_shared_filter_panel_and_visible_reset_controls(): void
    {
        $this->withoutVite();
        $this->seed(CurrencySeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'view_financials']);

        foreach ([
            '/reports/delivery-notes' => [
                'component' => 'Reports/DeliveryNotesReport',
                'source' => 'Reports/DeliveryNotesReport.tsx',
                'expects_currencies' => false,
            ],
            '/reports/goods-receipts' => [
                'component' => 'Reports/GoodsReceiptsReport',
                'source' => 'Reports/GoodsReceiptsReport.tsx',
                'expects_currencies' => false,
            ],
            '/reports/stock-movements' => [
                'component' => 'Reports/StockMovementsReport',
                'source' => 'Reports/StockMovementsReport.tsx',
                'expects_currencies' => true,
            ],
        ] as $path => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$case['source']}"));

            $this->assertStringContainsString('ReportFilterPanel', $source);
            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString('activeFilterCount', $source);
            $this->assertStringContainsString('clearFilters', $source);
            $this->assertStringNotContainsString('className="p-4 grid grid-cols-1 md:grid-cols-4 gap-4 items-end"', $source);

            $assertion = $this->actingAs($user)
                ->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($case['component'])
                    ->etc()
                );

            if ($case['expects_currencies']) {
                $assertion->assertInertia(fn (Assert $page) => $page->has('currencies')->etc());

                $this->assertStringContainsString('pageDict.currency', $source);
                $this->assertStringContainsString('pageDict.allCurrencies', $source);
                $this->assertStringContainsString('currencies: Array<{ code: string }>', $source);
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['currency', 'allCurrencies', 'clearFilters', 'activeFilters'] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'pages', 'stockMovementReport', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'pages', 'stockMovementReport', $key], 'AR');
        }
    }

    public function test_payroll_and_expense_filter_clear_actions_are_named_and_guarded(): void
    {
        foreach ([
            'Expenses/Prepaids.tsx' => '/expenses/prepaids',
            'Expenses/Accruals.tsx' => '/expenses/accruals',
            'Payroll/Components.tsx' => '/payroll/components',
            'Payroll/Employees.tsx' => '/payroll/employees',
            'Payroll/Runs.tsx' => '/payroll/runs',
        ] as $relativePath => $route) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('const activeFilterCount', $source);
            $this->assertStringContainsString('function clearFilters()', $source);
            $this->assertStringContainsString('onClick={clearFilters}', $source);
            $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
            $this->assertStringContainsString("router.get('{$route}', {}, { preserveScroll: true, preserveState: true });", $source);
            $this->assertStringNotContainsString('onClick={() => { setSearch', $source);
            $this->assertStringNotContainsString("router.get('{$route}', {}, { preserveScroll: true });", $source);
        }
    }

    public function test_remaining_operational_clear_filter_buttons_are_disabled_when_no_filters_are_active(): void
    {
        foreach ([
            'Inventory/StockAdjustments.tsx' => 'clearFilters',
            'Inventory/StockCounts.tsx' => 'clearFilters',
            'Inventory/Warehouses.tsx' => 'clearFilters',
            'Inventory/StockTransfers.tsx' => 'clearFilters',
            'Inventory/StockBalances.tsx' => 'clearFilter',
            'Expenses/Index.tsx' => 'clearFilters',
            'Expenses/Categories.tsx' => 'clearFilters',
            'Rentals/Handovers.tsx' => 'clearFilters',
            'Rentals/Returns.tsx' => 'clearFilters',
            'Rentals/RentableItems.tsx' => 'clearFilters',
            'Rentals/Contracts.tsx' => 'clearFilters',
            'Rentals/Invoices.tsx' => 'clearFilters',
        ] as $relativePath => $functionName) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('const activeFilterCount', $source);
            $this->assertStringContainsString("function {$functionName}()", $source);
            $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
            $this->assertStringContainsString("onClick={{$functionName}}", $source);

            if ($functionName === 'clearFilters') {
                $this->assertStringNotContainsString('onClick={clearFilters}>{', $source);
            }
        }
    }

    public function test_rental_filter_bars_use_searchable_select_controls(): void
    {
        foreach ([
            'Rentals/Handovers.tsx',
            'Rentals/Returns.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect options={[{ value: \'\', label: pageDict.allStatuses }, ...statusOptions]}', $source);
            $this->assertStringContainsString('onChange={(value) => setStatus(value || \'\')}', $source);
            $this->assertStringNotContainsString('<select className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={status}', $source);
        }

        $invoiceSource = (string) file_get_contents(resource_path('js/Pages/Rentals/Invoices.tsx'));

        $this->assertStringContainsString('SearchableSelect options={[{ value: \'\', label: pageDict.allStatuses }, ...statusOptions]}', $invoiceSource);
        $this->assertStringContainsString('SearchableSelect options={[{ value: \'\', label: pageDict.allTypes }, ...invoiceTypeOptions]}', $invoiceSource);
        $this->assertStringContainsString('onChange={(value) => setInvoiceType(value || \'\')}', $invoiceSource);
        $this->assertStringNotContainsString('<select className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={status}', $invoiceSource);
        $this->assertStringNotContainsString('<select className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={invoiceType}', $invoiceSource);
    }

    public function test_rental_handover_return_line_controls_use_searchable_selects(): void
    {
        foreach ([
            'Rentals/Handovers.tsx' => [
                'label={pageDict.conditionOut}',
                'value={line.condition_out}',
                "onChange={(value) => updateLine(index, { condition_out: value || 'good' })}",
                'options={conditionOptions}',
                'isClearable={false}',
            ],
            'Rentals/Returns.tsx' => [
                'label={pageDict.conditionIn}',
                'value={line.condition_in}',
                "onChange={(value) => updateLine(index, { condition_in: value || 'good' })}",
                'label={pageDict.outcome}',
                'value={line.outcome}',
                "onChange={(value) => updateLine(index, { outcome: value || 'returned' })}",
                'options={conditionOptions}',
                'options={outcomeOptions}',
                'isClearable={false}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should not use native select controls.");
            $this->assertStringNotContainsString('<option', $source, "{$relativePath} should not render native options.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_fixed_asset_filter_bars_use_searchable_select_and_clear_actions(): void
    {
        foreach ([
            'FixedAssets/Index.tsx' => [
                'route' => '/fixed-assets',
                'filter_keys' => ['selectedCat', 'selectedStatus', 'selectedBranch', 'selectedLocation'],
                'dictionary' => 'dict.app.accounting',
            ],
            'FixedAssets/Locations.tsx' => [
                'route' => '/fixed-asset-locations',
                'filter_keys' => ['branchId', 'status'],
                'dictionary' => 'dict.app.accounting',
            ],
            'FixedAssets/Disposals/Index.tsx' => [
                'route' => '/fixed-assets-disposals',
                'filter_keys' => ['statusFilter', 'typeFilter'],
                'dictionary' => 'dict.app.fixedAssetsDisposals',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString('const activeFilterCount', $source);
            $this->assertStringContainsString('function clearFilters()', $source);
            $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
            $this->assertStringContainsString("router.get('{$case['route']}', {},", $source);
            $this->assertStringContainsString('appDict.clearFilters', $source);
            $this->assertStringContainsString($case['dictionary'], $source);

            foreach ($case['filter_keys'] as $filterKey) {
                $this->assertStringContainsString($filterKey, $source);
            }

            $this->assertStringNotContainsString('<select', $source, "{$relativePath} fixed asset filter bar should not use native selects.");
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'accounting', 'clearFilters'],
            ['app', 'fixedAssetsDisposals', 'clearFilters'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_cash_bank_and_cheque_filter_bars_use_searchable_controls(): void
    {
        foreach ([
            'CashAccounts/Index.tsx' => [
                'route' => '/cash-accounts',
                'filter_keys' => ['filters.search', 'filters.status', 'filters.branch_id'],
                'selectors' => ['pageDict.allBranches', 'pageDict.allStatuses'],
            ],
            'BankAccounts/Index.tsx' => [
                'route' => '/bank-accounts',
                'filter_keys' => ['filters.search', 'filters.status', 'filters.branch_id'],
                'selectors' => ['pageDict.allBranches', 'pageDict.allStatuses'],
            ],
            'IncomingCheques/Index.tsx' => [
                'route' => '/incoming-cheques',
                'filter_keys' => ['filters.status', 'filters.customer_id'],
                'selectors' => ['pageDict.allStatuses', 'pageDict.customer'],
            ],
            'OutgoingCheques/Index.tsx' => [
                'route' => '/outgoing-cheques',
                'filter_keys' => ['filters.status', 'filters.supplier_id'],
                'selectors' => ['pageDict.allStatuses', 'pageDict.supplier'],
            ],
            'BankReconciliations/Index.tsx' => [
                'route' => '/bank-reconciliations',
                'filter_keys' => ['filters.status', 'filters.bank_account_id'],
                'selectors' => ['pageDict.allStatuses', 'pageDict.bankAccount'],
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString('const activeFilterCount', $source);
            $this->assertStringContainsString('function clearFilters()', $source);
            $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
            $this->assertStringContainsString("router.get('{$case['route']}',", $source);
            $this->assertStringContainsString('preserveScroll: true, preserveState: true', $source);
            $this->assertStringContainsString('accDict.clearFilters', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} filter bar should not use native selects.");
            $this->assertStringNotContainsString('window.location.href', $source);

            foreach ($case['filter_keys'] as $filterKey) {
                $this->assertStringContainsString($filterKey, $source);
            }

            foreach ($case['selectors'] as $selector) {
                $this->assertStringContainsString($selector, $source);
            }
        }

        foreach ([
            'IncomingCheques/Index.tsx' => 'pageDict.statuses[row.status]',
            'OutgoingCheques/Index.tsx' => 'pageDict.statuses[row.status]',
        ] as $relativePath => $localizedStatusExpression) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString($localizedStatusExpression, $source);
            $this->assertStringNotContainsString('row.status.toUpperCase()', $source);
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'cashAccounts', 'allStatuses'],
            ['app', 'pages', 'bankAccounts', 'allStatuses'],
            ['app', 'pages', 'incomingCheques', 'statuses', 'draft'],
            ['app', 'pages', 'incomingCheques', 'statuses', 'received'],
            ['app', 'pages', 'incomingCheques', 'statuses', 'deposited'],
            ['app', 'pages', 'incomingCheques', 'statuses', 'cleared'],
            ['app', 'pages', 'incomingCheques', 'statuses', 'bounced'],
            ['app', 'pages', 'incomingCheques', 'statuses', 'returned'],
            ['app', 'pages', 'outgoingCheques', 'statuses', 'draft'],
            ['app', 'pages', 'outgoingCheques', 'statuses', 'issued'],
            ['app', 'pages', 'outgoingCheques', 'statuses', 'cleared'],
            ['app', 'pages', 'outgoingCheques', 'statuses', 'returned'],
            ['app', 'pages', 'outgoingCheques', 'statuses', 'cancelled'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_customer_supplier_master_data_filters_use_inertia_and_searchable_status_controls(): void
    {
        foreach ([
            'Customers/Index.tsx' => [
                'route' => '/customers',
                'dictionary' => 'dict.app.pages.customers',
            ],
            'Suppliers/Index.tsx' => [
                'route' => '/suppliers',
                'dictionary' => 'dict.app.pages.suppliers',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString('const activeFilterCount', $source);
            $this->assertStringContainsString('function clearFilters()', $source);
            $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
            $this->assertStringContainsString("router.get('{$case['route']}',", $source);
            $this->assertStringContainsString('preserveScroll: true, preserveState: true', $source);
            $this->assertStringContainsString('filters.search', $source);
            $this->assertStringContainsString('filters.status', $source);
            $this->assertStringContainsString('pageDict.allStatuses', $source);
            $this->assertStringContainsString('accDict.clearFilters', $source);
            $this->assertStringContainsString($case['dictionary'], $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should use SearchableSelect for status controls.");
            $this->assertStringNotContainsString('window.location.href', $source);
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'customers', 'allStatuses'],
            ['app', 'pages', 'suppliers', 'allStatuses'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_ar_ap_allocation_and_settlement_filters_use_inertia_searchable_controls(): void
    {
        foreach ([
            'ReceivableAllocations/Index.tsx' => [
                'route' => '/receivable-allocations',
                'filter_keys' => ['filters.customer_id', 'filters.receipt_id'],
                'selectors' => ['dict.app.pages.receivableAllocations.filterCustomer', 'dict.app.pages.receivableAllocations.allCustomers'],
            ],
            'PayableAllocations/Index.tsx' => [
                'route' => '/payable-allocations',
                'filter_keys' => ['filters.supplier_id', 'filters.payment_id'],
                'selectors' => ['dict.app.pages.payableAllocations.filterSupplier', 'dict.app.pages.payableAllocations.allSuppliers'],
            ],
            'Sales/ReceivableSettlements.tsx' => [
                'route' => '/sales/receivable-settlements',
                'filter_keys' => ['filters.customer_id', 'filters.source_entry_id'],
                'selectors' => ['pageDict.allCustomers', 'pageDict.selectOpenCreditEntry'],
            ],
            'Purchasing/PayableSettlements.tsx' => [
                'route' => '/purchasing/payable-settlements',
                'filter_keys' => ['filters.supplier_id', 'filters.source_entry_id'],
                'selectors' => ['pageDict.allSuppliers', 'pageDict.selectOpenDebitEntry'],
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString('const activeFilterCount', $source);
            $this->assertStringContainsString('function clearFilters()', $source);
            $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
            $this->assertStringContainsString("router.get('{$case['route']}',", $source);
            $this->assertStringContainsString('preserveScroll: true, preserveState: true', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should use searchable selection controls.");
            $this->assertStringNotContainsString('window.location.href', $source);

            foreach ($case['filter_keys'] as $filterKey) {
                $this->assertStringContainsString($filterKey, $source);
            }

            foreach ($case['selectors'] as $selector) {
                $this->assertStringContainsString($selector, $source);
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'receivableAllocations', 'filterCustomer'],
            ['app', 'pages', 'receivableAllocations', 'allCustomers'],
            ['app', 'pages', 'payableAllocations', 'filterSupplier'],
            ['app', 'pages', 'payableAllocations', 'allSuppliers'],
            ['app', 'pages', 'receivableSettlements', 'allCustomers'],
            ['app', 'pages', 'payableSettlements', 'allSuppliers'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_treasury_transfer_filters_and_endpoint_type_controls_use_searchable_inertia_controls(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/TreasuryTransfers/Index.tsx'));

        $this->assertStringContainsString('SearchableSelect', $source);
        $this->assertStringContainsString('const statusOptions', $source);
        $this->assertStringContainsString('const endpointTypeOptions', $source);
        $this->assertStringContainsString('const activeFilterCount', $source);
        $this->assertStringContainsString('function clearFilters()', $source);
        $this->assertStringContainsString('disabled={activeFilterCount === 0}', $source);
        $this->assertStringContainsString("router.get('/treasury-transfers',", $source);
        $this->assertStringContainsString('preserveScroll: true, preserveState: true', $source);
        $this->assertStringContainsString('filters.search', $source);
        $this->assertStringContainsString('filters.status', $source);
        $this->assertStringContainsString('pageDict.allStatuses', $source);
        $this->assertStringContainsString('accDict.clearFilters', $source);
        $this->assertStringContainsString('options={endpointTypeOptions}', $source);
        $this->assertStringNotContainsString('<select', $source);
        $this->assertStringNotContainsString('window.location.href', $source);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'treasuryTransfers', 'allStatuses'],
            ['app', 'pages', 'treasuryTransfers', 'cash'],
            ['app', 'pages', 'treasuryTransfers', 'bank'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_financial_statement_exports_use_links_instead_of_window_redirects(): void
    {
        foreach ([
            'Reports/BalanceSheet.tsx' => '/reports/balance-sheet/export',
            'Reports/CashFlow.tsx' => '/reports/cash-flow/export',
            'Reports/IncomeStatement.tsx' => '/reports/income-statement/export',
        ] as $relativePath => $route) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('const exportUrl', $source);
            $this->assertStringContainsString($route, $source);
            $this->assertStringContainsString('<a', $source);
            $this->assertStringContainsString('href={exportUrl}', $source);
            $this->assertStringContainsString('actionsDict.exportCsv', $source);
            $this->assertStringNotContainsString('handleExportCsv', $source);
            $this->assertStringNotContainsString('window.location.href', $source);
        }
    }

    public function test_ar_ap_receipt_payment_cash_bank_type_controls_use_searchable_selects(): void
    {
        foreach ([
            'CustomerReceipts/Index.tsx' => [
                'options' => 'const destinationTypeOptions',
                'label' => 'dict.app.pages.customerReceipts.destinationType',
            ],
            'SupplierPayments/Index.tsx' => [
                'options' => 'const sourceTypeOptions',
                'label' => 'dict.app.pages.supplierPayments.sourceType',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringContainsString($case['options'], $source);
            $this->assertStringContainsString($case['label'], $source);
            $this->assertStringContainsString('toCashBankDestinationType(val || \'cash\')', $source);
            $this->assertStringContainsString('isClearable={false}', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should use the shared searchable cash/bank type control.");
        }
    }

    public function test_ar_ap_post_action_cells_show_restricted_state_instead_of_blank_actions(): void
    {
        foreach ([
            'CustomerReceipts/Index.tsx' => [
                'permissionCheck' => "const canPostReceipt = can('customers.receipts') && can('view_financials')",
                'oldBlankAction' => "can('customers.receipts') && can('view_financials') ? (",
                'confirmTitle' => 'title={dict.app.pages.customerReceipts.confirmPostReceipt}',
                'allocationLink' => '/receivable-allocations?receipt_id=${row.id}',
            ],
            'SupplierPayments/Index.tsx' => [
                'permissionCheck' => "const canPostPayment = can('suppliers.payments') && can('view_financials')",
                'oldBlankAction' => "can('suppliers.payments') && can('view_financials') ? (",
                'confirmTitle' => 'title={dict.app.pages.supplierPayments.confirmPostPayment}',
                'allocationLink' => '/payable-allocations?payment_id=${row.id}',
            ],
            'CustomerOpeningBalances/Index.tsx' => [
                'permissionCheck' => "const canPostOpeningBalance = can('customers.opening_balances') && can('view_financials')",
                'oldBlankAction' => "can('customers.opening_balances') && can('view_financials') ? (",
                'confirmTitle' => 'title={dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance}',
                'allocationLink' => null,
            ],
            'SupplierOpeningBalances/Index.tsx' => [
                'permissionCheck' => "const canPostOpeningBalance = can('suppliers.opening_balances') && can('view_financials')",
                'oldBlankAction' => "can('suppliers.opening_balances') && can('view_financials') ? (",
                'confirmTitle' => 'title={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}',
                'allocationLink' => null,
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString($case['permissionCheck'], $source);
            $this->assertStringContainsString($case['confirmTitle'], $source);
            $this->assertStringContainsString('StatusBadge tone="muted"', $source);
            $this->assertStringContainsString('dict.app.actions.restricted', $source);
            $this->assertStringNotContainsString($case['oldBlankAction'], $source, "{$relativePath} must show a restricted action state instead of leaving the action cell blank.");

            if ($case['allocationLink'] !== null) {
                $this->assertStringContainsString('<Link', $source);
                $this->assertStringContainsString($case['allocationLink'], $source);
                $this->assertStringNotContainsString('<a', $source, "{$relativePath} should use Inertia Link for allocation navigation.");
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertLocalePathIsNotEmpty($en, ['app', 'actions', 'restricted'], 'EN');
        $this->assertLocalePathIsNotEmpty($ar, ['app', 'actions', 'restricted'], 'AR');
    }

    public function test_accounting_mapping_pages_use_searchable_selection_controls(): void
    {
        $accountMappings = (string) file_get_contents(resource_path('js/Pages/Accounting/AccountMappings.tsx'));
        $statementMappings = (string) file_get_contents(resource_path('js/Pages/Accounting/FinancialStatementMappings.tsx'));

        $this->assertStringContainsString('function toMappingScope', $accountMappings);
        $this->assertStringContainsString('const scopeOptions', $accountMappings);
        $this->assertStringContainsString('SearchableSelect<MappingScope>', $accountMappings);
        $this->assertStringContainsString('onChange={(value) => changeScope(toMappingScope(value))}', $accountMappings);
        $this->assertStringNotContainsString("event.target.value as 'global' | 'branch'", $accountMappings);
        $this->assertStringNotContainsString('<select', $accountMappings);

        foreach ([
            'const statementTypeOptions',
            'const sectionSelectOptions',
            'const normalBalanceOptions',
            'const cashFlowActivityOptions',
            'const accountCashFlowActivityOptions',
            'SearchableSelect<StatementType>',
            'SearchableSelect<NormalBalance>',
            'options={accountCashFlowActivityOptions}',
            'function toStatementType',
            'function toNormalBalance',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $statementMappings);
        }

        $this->assertStringNotContainsString('<select', $statementMappings);
        $this->assertStringNotContainsString('e.target.value as', $statementMappings);
        $this->assertStringNotContainsString('window.location.href', $statementMappings);
    }

    public function test_phase15_report_dictionary_keys_exist_in_both_locales(): void
    {
        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($this->requiredReportDictionaryKeys() as $key) {
            $this->assertNotEmpty($en['app']['pages']['reports'][$key] ?? null, "Missing EN report dictionary key [{$key}].");
            $this->assertNotEmpty($ar['app']['pages']['reports'][$key] ?? null, "Missing AR report dictionary key [{$key}].");
        }
    }

    public function test_reports_hub_uses_tax_dictionary_without_visible_fallbacks(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Reports/Index.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, 'Reports/Index.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString('const taxDict = dict.app.taxes', $source);
        $this->assertStringContainsString('title: taxDict.title', $source);
        $this->assertStringContainsString('name: taxDict.vatRegister.title', $source);
        $this->assertStringContainsString('desc: taxDict.vatRegister.subtitle', $source);
        $this->assertStringContainsString('name: taxDict.vatSummary.title', $source);
        $this->assertStringContainsString('desc: taxDict.vatSummary.subtitle', $source);
        $this->assertStringContainsString('name: taxDict.vatGlReconciliation.title', $source);
        $this->assertStringContainsString('desc: taxDict.vatGlReconciliation.subtitle', $source);

        foreach ([
            "|| 'Tax & VAT Reports'",
            "|| 'VAT Register'",
            "|| 'VAT Summary Report'",
            "|| 'VAT to GL Reconciliation'",
            'Detailed line-item audit trail of output and input tax',
            'Summary of output and input VAT grouped by tax code',
            'Compares posted tax register totals against GL ledger movement',
            '(dict.app.taxes as any)',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Reports hub tax report labels must stay dictionary-backed.');
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'taxes', 'title'],
            ['app', 'taxes', 'vatRegister', 'title'],
            ['app', 'taxes', 'vatRegister', 'subtitle'],
            ['app', 'taxes', 'vatSummary', 'title'],
            ['app', 'taxes', 'vatSummary', 'subtitle'],
            ['app', 'taxes', 'vatGlReconciliation', 'title'],
            ['app', 'taxes', 'vatGlReconciliation', 'subtitle'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_vat_gl_reconciliation_page_uses_dictionary_and_no_hidden_currency_default(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Reports/VatGlReconciliation.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, 'VatGlReconciliation.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString('const t = dict.app.taxes.vatGlReconciliation', $source);
        $this->assertStringContainsString('const tw = dict.app.taxes.warnings', $source);
        $this->assertStringContainsString("useState(filters.currency || report.currency || currencies[0]?.code || '')", $source);
        $this->assertStringContainsString("onChange={(val) => setCurrency(val || '')}", $source);
        $this->assertStringContainsString('{t.taxCategory}', $source);
        $this->assertStringContainsString('{t.registerTaxAmount}', $source);
        $this->assertStringContainsString('{t.glLedgerMovement}', $source);
        $this->assertStringContainsString('{t.signedDifference}', $source);
        $this->assertStringContainsString('{t.netVatPosition}', $source);

        foreach ([
            "|| 'USD'",
            "val || 'USD'",
            "|| 'VAT to GL Reconciliation'",
            "|| 'Export CSV'",
            "|| 'From Date'",
            "|| 'To Date'",
            "|| 'Currency'",
            "|| 'Update Report'",
            "|| 'Output Tax Account'",
            "|| 'Input Tax Account'",
            "|| 'Reconciliation Status'",
            "|| 'Not Configured'",
            "|| 'RECONCILED'",
            "|| 'UNRECONCILED DIFFERENCE'",
            'Tax Category',
            'Register Tax Amount',
            'GL Ledger Movement',
            'Signed Difference',
            'Output VAT</span> (Sales & Revenue)',
            'Input VAT</span> (Purchases & Expenses)',
            'Net VAT Position (Payable / Claimable)',
            'getDictionary(locale) as any',
            'dict.taxes?.vatGlReconciliation',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'VAT to GL reconciliation page must stay dictionary-backed without hidden currency defaults.');
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'taxes', 'vatGlReconciliation', 'title'],
            ['app', 'taxes', 'vatGlReconciliation', 'subtitle'],
            ['app', 'taxes', 'vatGlReconciliation', 'exportCsv'],
            ['app', 'taxes', 'vatGlReconciliation', 'fromDate'],
            ['app', 'taxes', 'vatGlReconciliation', 'toDate'],
            ['app', 'taxes', 'vatGlReconciliation', 'currency'],
            ['app', 'taxes', 'vatGlReconciliation', 'updateReport'],
            ['app', 'taxes', 'vatGlReconciliation', 'status'],
            ['app', 'taxes', 'vatGlReconciliation', 'reconciled'],
            ['app', 'taxes', 'vatGlReconciliation', 'unreconciled'],
            ['app', 'taxes', 'vatGlReconciliation', 'outputAccount'],
            ['app', 'taxes', 'vatGlReconciliation', 'inputAccount'],
            ['app', 'taxes', 'vatGlReconciliation', 'notMapped'],
            ['app', 'taxes', 'vatGlReconciliation', 'taxCategory'],
            ['app', 'taxes', 'vatGlReconciliation', 'registerTaxAmount'],
            ['app', 'taxes', 'vatGlReconciliation', 'glLedgerMovement'],
            ['app', 'taxes', 'vatGlReconciliation', 'signedDifference'],
            ['app', 'taxes', 'vatGlReconciliation', 'outputVatCategory'],
            ['app', 'taxes', 'vatGlReconciliation', 'inputVatCategory'],
            ['app', 'taxes', 'vatGlReconciliation', 'salesRevenueScope'],
            ['app', 'taxes', 'vatGlReconciliation', 'purchasesExpensesScope'],
            ['app', 'taxes', 'vatGlReconciliation', 'netVatPosition'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_accountant_workflow_pages_use_dictionary_backed_ux_text(): void
    {
        foreach ([
            'Accounting/GeneralLedger.tsx' => [
                "|| 'General Ledger'",
                "|| 'Filter Account'",
                "|| 'Apply Filter'",
                "|| 'Posting Date'",
                "|| 'Total Debits'",
                "|| 'EGP'",
                "|| 'JV'",
            ],
            'Reports/VatRegister.tsx' => [
                "|| 'VAT Register'",
                "|| 'All Categories'",
                "|| 'From Date'",
                'Adjust date range or tax category filters',
                "|| 'No posted tax entries found'",
                'getDictionary(locale) as any',
                'dict.taxes.',
                ' - Mini ERP',
            ],
            'Reports/VatSummary.tsx' => [
                "|| 'VAT Summary Report'",
                "|| 'Update Summary'",
                'Total Tax:',
                '>Output VAT<',
                '>Input VAT<',
                "|| 'No output tax entries recorded'",
                'getDictionary(locale) as any',
                'dict.taxes.',
                ' - Mini ERP',
            ],
        ] as $relativePath => $forbiddenFragments) {
            $source = file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep accountant UX labels dictionary-backed.");
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'accounting', 'noLedgerEntriesFilteredDesc'],
            ['app', 'accounting', 'unnumberedVoucher'],
            ['app', 'taxes', 'vatRegister', 'noRecordsDescription'],
            ['app', 'taxes', 'vatRegister', 'netSubtotal'],
            ['app', 'taxes', 'vatSummary', 'totalTax'],
            ['app', 'taxes', 'vatSummary', 'outputVatShort'],
            ['app', 'taxes', 'vatSummary', 'inputVatShort'],
            ['app', 'taxes', 'vatSummary', 'noOutputRecordsDescription'],
            ['app', 'taxes', 'vatSummary', 'noInputRecordsDescription'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_vat_and_tax_pages_use_explicit_currency_instead_of_format_money_defaults(): void
    {
        foreach ([
            'Reports/VatRegister.tsx' => 'formatVatMoney',
            'Reports/VatSummary.tsx' => 'formatVatMoney',
            'Reports/VatGlReconciliation.tsx' => 'formatVatMoney',
            'Taxes/Periods/Show.tsx' => 'formatTaxMoney',
        ] as $relativePath => $helperName) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString($helperName, $source, "{$relativePath} must use an explicit-currency money formatter.");
            $this->assertStringContainsString('accDict.notAvailable', $source, "{$relativePath} must use the canonical unavailable marker when currency is unavailable.");

            foreach ([
                'formatMoney(report.summary',
                'formatMoney(row.',
                'formatMoney(activeReturn.',
                'formatMoney(report.register_',
                'formatMoney(report.gl_',
                'formatMoney(report.output_',
                'formatMoney(report.input_',
                'formatMoney(report.net_',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not call formatMoney without an explicit currency.");
            }
        }

        $vatPageData = (string) file_get_contents(app_path('Application/Reports/VatReportPageData.php'));
        $taxPeriodPageData = (string) file_get_contents(app_path('Application/Taxes/TaxPeriodPageData.php'));
        $taxPeriodController = (string) file_get_contents(app_path('Http/Controllers/Taxes/TaxPeriodController.php'));

        $this->assertStringContainsString("\$report['currency'] = \$this->baseCurrency();", $vatPageData);
        $this->assertStringContainsString('BaseCurrencyResolver $baseCurrencyResolver', $taxPeriodPageData);
        $this->assertStringContainsString("'currency' => \$this->baseCurrencyResolver->resolve(),", $taxPeriodPageData);
        $this->assertStringNotContainsString('baseCurrency', $taxPeriodController);
        $this->assertStringNotContainsString("?: 'EGP'", $vatPageData);
        $this->assertStringNotContainsString("?: 'EGP'", $taxPeriodPageData);
        $this->assertStringNotContainsString("?: 'EGP'", $taxPeriodController);
    }

    public function test_report_currency_defaults_use_resolver_instead_of_hardcoded_literals(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => 'AED'],
            [
                'name' => ['en' => 'UAE Dirham', 'ar' => 'درهم إماراتي'],
                'symbol' => 'AED',
                'exponent' => 2,
            ]
        );

        Company::query()->create([
            'name' => ['en' => 'Resolver Test Company', 'ar' => 'شركة اختبار العملة'],
            'base_currency' => 'AED',
        ]);

        $resolver = $this->app->make(ReportCurrencyResolver::class);

        $this->assertSame('AED', $resolver->resolve());
        $this->assertSame('SAR', $resolver->resolve('sar'));

        foreach ([
            app_path('Application/Reports'),
            app_path('Http/Controllers/Reports'),
        ] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                foreach ([
                    "query('currency', 'EGP')",
                    "query('currency', \"EGP\")",
                    "\$currency ?? 'EGP'",
                    '$currency ?? "EGP"',
                    "\$currency ?? 'USD'",
                    '$currency ?? "USD"',
                    "\$filters['currency'] ?? 'EGP'",
                    "\$filters['currency'] ?? 'USD'",
                    "?: 'EGP'",
                    '?: "EGP"',
                    "?: 'USD'",
                    '?: "USD"',
                ] as $fragment) {
                    $this->assertStringNotContainsString($fragment, $source, $file->getPathname().' must not use hardcoded report currency defaults.');
                }
            }
        }

        foreach ([
            'Application/Reports/ArAgingReportService.php',
            'Application/Reports/ApAgingReportService.php',
            'Application/Reports/ArToGlReconciliationReportService.php',
            'Application/Reports/ApToGlReconciliationReportService.php',
            'Application/Reports/ChequeRegisterReportService.php',
            'Application/Reports/CustomerStatementReportService.php',
            'Application/Reports/SupplierStatementReportService.php',
            'Application/Reports/VatToGlReconciliationService.php',
        ] as $relativePath) {
            $source = (string) file_get_contents(app_path($relativePath));
            $this->assertStringContainsString('ReportCurrencyResolver', $source, "{$relativePath} must use the shared report currency resolver.");
        }
    }

    public function test_operational_services_require_explicit_currency_instead_of_hidden_defaults(): void
    {
        $this->assertSame('USD', CurrencyInput::required('usd'));

        try {
            CurrencyInput::required(null);
            $this->fail('CurrencyInput must reject missing currencies.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency', $exception->errors());
        }

        foreach ([
            app_path('Application'),
            app_path('Http/Controllers'),
        ] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                foreach ([
                    "?? 'EGP'",
                    '?? "EGP"',
                    "?? 'USD'",
                    '?? "USD"',
                    "?: 'EGP'",
                    '?: "EGP"',
                    "?: 'USD'",
                    '?: "USD"',
                    "'currency' => 'EGP'",
                    '"currency" => "EGP"',
                    "'currency' => 'USD'",
                    '"currency" => "USD"',
                    "query('currency', 'EGP')",
                    'query("currency", "EGP")',
                ] as $fragment) {
                    $this->assertStringNotContainsString($fragment, $source, $file->getPathname().' must not use hidden operational currency defaults.');
                }
            }
        }

        foreach ([
            'Application/Sales/CustomerInvoiceService.php',
            'Application/Sales/CustomerCreditNoteService.php',
            'Application/Purchasing/SupplierBillService.php',
            'Application/Purchasing/PurchaseReturnService.php',
            'Application/Purchasing/SupplierAdjustmentNoteService.php',
            'Application/Accounting/JournalDraftService.php',
            'Application/Accounting/IncomingChequeService.php',
            'Application/Accounting/OutgoingChequeService.php',
            'Application/Expenses/ExpenseService.php',
            'Application/Expenses/AccrualScheduleService.php',
            'Application/Expenses/PrepaidScheduleService.php',
            'Application/FixedAssets/FixedAssetRegisterService.php',
            'Application/Inventory/StockCountService.php',
            'Application/Inventory/StockAdjustmentService.php',
            'Application/Payroll/PayrollRunService.php',
            'Application/Payroll/EmployeeService.php',
            'Application/Rentals/RentableItemService.php',
            'Application/Rentals/RentalContractService.php',
        ] as $relativePath) {
            $source = (string) file_get_contents(app_path($relativePath));
            $this->assertStringContainsString('CurrencyInput::', $source, "{$relativePath} must use explicit currency input handling.");
        }

        foreach ([
            'Http/Controllers/Accounting/JournalController.php',
            'Http/Controllers/CustomerInvoiceController.php',
            'Http/Controllers/SupplierBillController.php',
            'Http/Controllers/SalesOrderController.php',
            'Http/Controllers/PurchaseOrderController.php',
            'Http/Controllers/IncomingChequeController.php',
            'Http/Controllers/OutgoingChequeController.php',
        ] as $relativePath) {
            $source = (string) file_get_contents(app_path($relativePath));
            $this->assertStringContainsString('exists:currency,code', $source, "{$relativePath} must validate currency against the registry.");
        }
    }

    public function test_exchange_rates_use_configured_base_currency_and_require_foreign_rate(): void
    {
        foreach ([
            ['code' => 'SAR', 'name' => ['en' => 'Saudi Riyal', 'ar' => 'ريال سعودي'], 'symbol' => 'SAR'],
            ['code' => 'USD', 'name' => ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'], 'symbol' => '$'],
        ] as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'exponent' => 2,
                ]
            );
        }

        Company::query()->create([
            'name' => ['en' => 'FX Base Company', 'ar' => 'شركة اختبار سعر الصرف'],
            'base_currency' => 'SAR',
        ]);

        $service = $this->app->make(ExchangeRateService::class);

        $this->assertSame(1000000, $service->getRateE6('sar', '2026-01-15'));

        try {
            $service->getRateE6('USD', '2026-01-15');
            $this->fail('Missing foreign exchange rate must not silently fall back to 1.000000.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Exchange rate is required', $exception->getMessage());
        }

        $actor = User::factory()->create();
        $service->setRate('usd', '2026-01-15', '50.25', $actor->id);

        $this->assertSame(50250000, $service->getRateE6('USD', '2026-01-16'));

        $source = (string) file_get_contents(app_path('Application/Accounting/ExchangeRateService.php'));
        $this->assertStringContainsString('BaseCurrencyResolver', $source);
        $this->assertStringNotContainsString("=== 'EGP'", $source);
    }

    public function test_console_commands_and_seeders_do_not_use_fixed_currency_fixtures(): void
    {
        foreach ([app_path('Console/Commands'), database_path('seeders')] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                $this->assertStringNotContainsString('EGP', $source, $file->getPathname().' must not hardcode an operational/stress fixture currency.');
                $this->assertStringNotContainsString('USD', $source, $file->getPathname().' must not hardcode an operational/stress fixture currency.');
            }
        }

        foreach ([
            'Console/Commands/AllocationConcurrencyStressCommand.php',
            'Console/Commands/SettlementConcurrencyStressCommand.php',
            'Console/Commands/ChequeConcurrencyStressCommand.php',
            'Console/Commands/StockTransferConcurrencyStressCommand.php',
            'Console/Commands/BankReconciliationConcurrencyStressCommand.php',
            'Console/Commands/AccountingInventoryConcurrencyStressCommand.php',
            'Console/Commands/FixedAssetDepreciationStressCommand.php',
            'Console/Commands/FixedAssetDisposalStressCommand.php',
        ] as $relativePath) {
            $source = (string) file_get_contents(app_path($relativePath));
            $this->assertStringContainsString('resolveStressCurrency', $source, "{$relativePath} must resolve stress currency from configured base currency.");
        }

        $this->assertStringContainsString('BaseCurrencyResolver', (string) file_get_contents(app_path('Console/Commands/AccountingConcurrencyStressCommand.php')));
        $this->assertStringContainsString('ReportCurrencyResolver', (string) file_get_contents(app_path('Console/Commands/Phase3IntegrityCheckCommand.php')));
        $this->assertStringContainsString('BaseCurrencyResolver', (string) file_get_contents(database_path('seeders/AccountingCoreSeeder.php')));
        $this->assertStringContainsString('CurrencyInput::related', (string) file_get_contents(database_path('seeders/AccountingDemoSeeder.php')));
    }

    public function test_accounting_landing_page_uses_canonical_dictionary_labels(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Accounting/Index.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, 'Accounting/Index.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString('const accDict = dict.app.accounting', $source);
        $this->assertStringContainsString('const stateDict = dict.app.state', $source);
        $this->assertStringContainsString('function getStatusLabel(status: string)', $source);
        $this->assertStringContainsString('getStatusLabel(j.status)', $source);

        foreach ([
            "|| 'Accounting Core'",
            "|| 'Create Journal Voucher'",
            "|| 'Active Accounts'",
            "|| 'Posted Journals'",
            "|| 'Draft Vouchers'",
            "|| 'Active Fiscal Year'",
            "'No journal vouchers created yet.'",
            "'DRAFT'",
            "'Manual Journal'",
            '>View<',
            'General Ledger spine, Journal Vouchers',
            'Immutable posted transactions stream',
            'Set account-level initial balances',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Accounting landing page must keep visible text dictionary-backed.');
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'accounting', 'title'],
            ['app', 'accounting', 'subtitle'],
            ['app', 'accounting', 'createVoucher'],
            ['app', 'accounting', 'activeAccounts'],
            ['app', 'accounting', 'postedJournals'],
            ['app', 'accounting', 'draftVouchers'],
            ['app', 'accounting', 'activeFiscalYear'],
            ['app', 'accounting', 'recentJournals'],
            ['app', 'accounting', 'noJournalsDesc'],
            ['app', 'accounting', 'draftBadge'],
            ['app', 'accounting', 'manualJournal'],
            ['app', 'accounting', 'statusDraft'],
            ['app', 'accounting', 'statusSubmitted'],
            ['app', 'accounting', 'statusApproved'],
            ['app', 'accounting', 'statusPosted'],
            ['app', 'accounting', 'statusReversed'],
            ['app', 'accounting', 'viewDetail'],
            ['app', 'state', 'none'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_sensitive_payroll_and_settlement_pages_have_confirmation_and_dictionary_guards(): void
    {
        $payrollEmployees = (string) file_get_contents(resource_path('js/Pages/Payroll/Employees.tsx'));
        $payrollComponents = (string) file_get_contents(resource_path('js/Pages/Payroll/Components.tsx'));

        $this->assertStringContainsString('confirm(pageDict.confirmDeleteAssignment)', $payrollEmployees);
        $this->assertStringContainsString('confirm(pageDict.confirmDeleteComponent)', $payrollComponents);
        $this->assertStringNotContainsString('onClick={() => router.delete(`/payroll/employees/', $payrollEmployees);
        $this->assertStringNotContainsString('onClick={() => router.delete(`/payroll/components/', $payrollComponents);

        foreach ([
            'Sales/ReceivableSettlements.tsx' => [
                'AR Settlement of Credit Notes',
                'Manual AR Credit Settlement',
                'Please enter a positive settlement amount',
                'Processing Settlement...',
                'Confirm Settlement',
                'Reverse Settlement',
                'Reversal Reason',
            ],
            'Purchasing/PayableSettlements.tsx' => [
                'AP Settlement of Adjustment Notes',
                'Manual AP Debit Settlement',
                'Please enter a positive settlement amount',
                'Processing Settlement...',
                'Confirm Settlement',
                'Reverse Settlement',
                'Reversal Reason',
            ],
        ] as $relativePath => $forbiddenFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('getDictionary(locale)', $source);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must use dictionary-backed operational text.");
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'payrollEmployees', 'confirmDeleteAssignment'],
            ['app', 'pages', 'payrollComponents', 'confirmDeleteComponent'],
            ['app', 'pages', 'receivableSettlements', 'confirmSettlement'],
            ['app', 'pages', 'receivableSettlements', 'confirmReversal'],
            ['app', 'pages', 'payableSettlements', 'confirmSettlement'],
            ['app', 'pages', 'payableSettlements', 'confirmReversal'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_dense_operational_pages_confirm_state_changing_actions(): void
    {
        foreach ([
            'Expenses/Index.tsx' => ['pageDict.confirmations[action]', 'confirm(message)'],
            'Expenses/Prepaids.tsx' => [
                'pageDict.confirmations.submit',
                'pageDict.confirmations.approve',
                'pageDict.confirmations.cancel',
                'pageDict.confirmations.postRecognition',
            ],
            'Expenses/Accruals.tsx' => [
                'pageDict.confirmations.submit',
                'pageDict.confirmations.approve',
                'pageDict.confirmations.cancel',
                'pageDict.confirmations.postEntry',
            ],
            'Rentals/Contracts.tsx' => ['pageDict.confirmations[action]', 'confirm(message)'],
            'Rentals/Handovers.tsx' => ['pageDict.confirmConfirm', 'pageDict.confirmCancel'],
            'Rentals/Returns.tsx' => ['pageDict.confirmSubmit', 'pageDict.confirmComplete', 'pageDict.confirmCancel'],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} must confirm sensitive operational state changes.");
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'expenses', 'confirmations', 'submit'],
            ['app', 'pages', 'expenses', 'confirmations', 'approve'],
            ['app', 'pages', 'expenses', 'confirmations', 'post'],
            ['app', 'pages', 'expenses', 'confirmations', 'cancel'],
            ['app', 'pages', 'prepaidSchedules', 'confirmations', 'postRecognition'],
            ['app', 'pages', 'accrualSchedules', 'confirmations', 'postEntry'],
            ['app', 'pages', 'rentalContracts', 'confirmations', 'submit'],
            ['app', 'pages', 'rentalContracts', 'confirmations', 'approve'],
            ['app', 'pages', 'rentalContracts', 'confirmations', 'activate'],
            ['app', 'pages', 'rentalContracts', 'confirmations', 'cancel'],
            ['app', 'pages', 'rentalHandovers', 'confirmConfirm'],
            ['app', 'pages', 'rentalHandovers', 'confirmCancel'],
            ['app', 'pages', 'rentalReturns', 'confirmSubmit'],
            ['app', 'pages', 'rentalReturns', 'confirmComplete'],
            ['app', 'pages', 'rentalReturns', 'confirmCancel'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }

        foreach (['rentableItems', 'rentalContracts', 'rentalHandovers', 'rentalReturns'] as $pageKey) {
            $this->assertStringNotContainsString(
                '????',
                json_encode($ar['app']['pages'][$pageKey], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                "Arabic dictionary block [{$pageKey}] must not contain mojibake placeholders."
            );
        }
    }

    public function test_ar_ap_cash_posting_confirmations_name_the_ledger_impact(): void
    {
        foreach ([
            'CustomerOpeningBalances/Index.tsx' => [
                'required' => 'dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance',
                'forbidden' => 'dict.app.pages.customerOpeningBalances.areYouSureYouWantTo',
            ],
            'CustomerReceipts/Index.tsx' => [
                'required' => 'dict.app.pages.customerReceipts.confirmPostReceipt',
                'forbidden' => 'dict.app.pages.customerReceipts.areYouSureYouWantTo',
            ],
            'SupplierOpeningBalances/Index.tsx' => [
                'required' => 'dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance',
                'forbidden' => 'dict.app.pages.supplierOpeningBalances.areYouSureYouWantTo',
            ],
            'SupplierPayments/Index.tsx' => [
                'required' => 'dict.app.pages.supplierPayments.confirmPostPayment',
                'forbidden' => 'dict.app.pages.supplierPayments.areYouSureYouWantTo',
            ],
        ] as $relativePath => $expectations) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString($expectations['required'], $source, "{$relativePath} must use a workflow-specific posting confirmation.");
            $this->assertStringNotContainsString($expectations['forbidden'], $source, "{$relativePath} must not use the legacy generic posting confirmation.");
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['app', 'pages', 'customerOpeningBalances', 'confirmPostOpeningBalance'],
                ['app', 'pages', 'customerReceipts', 'confirmPostReceipt'],
                ['app', 'pages', 'supplierOpeningBalances', 'confirmPostOpeningBalance'],
                ['app', 'pages', 'supplierPayments', 'confirmPostPayment'],
            ] as $path) {
                $this->assertLocalePathIsNotEmpty($dictionary, $path, $locale);
            }
        }
    }

    public function test_user_permission_admin_page_uses_dictionary_backed_security_labels(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Settings/Users.tsx'));

        foreach ([
            'labelEn',
            'labelAr',
            'CATEGORY_META',
            'Search Results',
            'permissions found',
            'Revoke Role',
            'Are you sure',
            'e.g. Mahmoud',
            'At least 8 characters',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'User/role security administration labels must stay dictionary-backed.');
        }

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, 'Users.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString('dict.app.permissionCategories', $source);
        $this->assertStringContainsString('dict.app.permissionActions', $source);
        $this->assertStringContainsString('dict.app.fields.permissionSearchResults', $source);
        $this->assertStringContainsString('dict.app.messages.confirmDeleteRole.replace', $source);
        $this->assertStringContainsString('dict.app.messages.confirmDeleteUser.replace', $source);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'fields', 'fullNamePlaceholder'],
            ['app', 'fields', 'emailPlaceholder'],
            ['app', 'fields', 'passwordPlaceholder'],
            ['app', 'fields', 'passwordMaskedPlaceholder'],
            ['app', 'fields', 'permissionSearchResults'],
            ['app', 'permissionCategories', 'accounting'],
            ['app', 'permissionCategories', 'inventory'],
            ['app', 'permissionCategories', 'payroll'],
            ['app', 'permissionCategories', 'rentals'],
            ['app', 'permissionCategories', 'approvals'],
            ['app', 'permissionCategories', 'general'],
            ['app', 'permissionActions', 'view_financials'],
            ['app', 'permissionActions', 'view_payroll'],
            ['app', 'permissionActions', 'override_control'],
            ['app', 'permissionActions', 'taxes.file'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_foundation_settings_pages_use_dictionary_backed_labels_and_confirmations(): void
    {
        foreach ([
            'Settings/Company.tsx' => [
                '(English)',
                '(العربية)',
                'e.g. Acme Corporation',
                'مثال: شركة الرواد',
                'EN:',
                'AR:',
                "?? 'EGP'",
                "?? 'JV'",
            ],
            'Settings/Branches.tsx' => [
                '(English)',
                '(العربية)',
                'e.g. CAI-01',
                'e.g. Cairo Main Branch',
                'مثال: فرع القاهرة الرئيسي',
                'Are you sure you want to delete this branch?',
                'dict.app.actions.confirmDelete ||',
                'EN:',
                'AR:',
            ],
            'Settings/Numbering.tsx' => [
                'e.g. sales.invoice',
                'e.g. SalesInvoice',
                'e.g. INV-',
                'Format: PREFIX-YEAR-NUMBER',
                'Never',
                'Yearly',
                'Monthly',
                'digits',
                'Yes (YYYY-)',
                ": 'No'",
            ],
        ] as $relativePath => $forbiddenFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, "{$relativePath} must not contain hardcoded Arabic UI text.");

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep settings UX text dictionary-backed.");
            }
        }

        $branchesSource = (string) file_get_contents(resource_path('js/Pages/Settings/Branches.tsx'));
        $this->assertStringContainsString('dict.app.messages.confirmDeleteBranch.replace', $branchesSource);

        $numberingSource = (string) file_get_contents(resource_path('js/Pages/Settings/Numbering.tsx'));
        $this->assertStringContainsString('dict.app.pages.settingsNumbering.resetPolicies', $numberingSource);
        $this->assertStringContainsString('dict.app.pages.settingsNumbering.includeYearFormatDescription', $numberingSource);
        $this->assertStringContainsString('dict.app.pages.settingsNumbering.paddingDigits.replace', $numberingSource);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'fields', 'companyNameEnPlaceholder'],
            ['app', 'fields', 'companyNameArPlaceholder'],
            ['app', 'fields', 'branchCodePlaceholder'],
            ['app', 'fields', 'branchNameEnPlaceholder'],
            ['app', 'fields', 'branchNameArPlaceholder'],
            ['app', 'messages', 'confirmDeleteBranch'],
            ['app', 'pages', 'settingsNumbering', 'keyPlaceholder'],
            ['app', 'pages', 'settingsNumbering', 'docTypePlaceholder'],
            ['app', 'pages', 'settingsNumbering', 'prefixPlaceholder'],
            ['app', 'pages', 'settingsNumbering', 'includeYearFormatDescription'],
            ['app', 'pages', 'settingsNumbering', 'paddingDigits'],
            ['app', 'pages', 'settingsNumbering', 'includeYearYes'],
            ['app', 'pages', 'settingsNumbering', 'includeYearNo'],
            ['app', 'pages', 'settingsNumbering', 'resetPolicies', 'never'],
            ['app', 'pages', 'settingsNumbering', 'resetPolicies', 'yearly'],
            ['app', 'pages', 'settingsNumbering', 'resetPolicies', 'monthly'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_foundation_settings_actions_have_accessible_names_and_scroll_safe_delete(): void
    {
        foreach ([
            'Settings/Company.tsx' => [
                'title={dict.app.actions.addCompany}',
                'aria-label={dict.app.actions.addCompany}',
                'title={dict.app.pages.settingsCompany.attachments}',
                'aria-label={dict.app.pages.settingsCompany.attachments}',
                'title={dict.app.actions.edit}',
                'aria-label={dict.app.actions.edit}',
                'title={company ? dict.app.actions.save : dict.app.actions.create}',
                'aria-label={company ? dict.app.actions.save : dict.app.actions.create}',
                'title={dict.app.actions.cancel}',
                'aria-label={dict.app.actions.cancel}',
            ],
            'Settings/Branches.tsx' => [
                'title={dict.app.actions.addBranch}',
                'aria-label={dict.app.actions.addBranch}',
                'title={dict.app.pages.settingsBranches.attachments}',
                'aria-label={dict.app.pages.settingsBranches.attachments}',
                'title={dict.app.actions.edit}',
                'aria-label={dict.app.actions.edit}',
                'title={dict.app.actions.delete}',
                'aria-label={dict.app.actions.delete}',
                'title={branch ? dict.app.actions.save : dict.app.actions.create}',
                'aria-label={branch ? dict.app.actions.save : dict.app.actions.create}',
                'title={dict.app.actions.cancel}',
                'aria-label={dict.app.actions.cancel}',
                'router.delete(`/settings/branches/${branch.id}`, { preserveScroll: true });',
            ],
            'Settings/Numbering.tsx' => [
                'title={dict.app.actions.addSequence}',
                'aria-label={dict.app.actions.addSequence}',
                'title={`${dict.app.fields.preview}: ${sequence.preview}`}',
                'aria-label={`${dict.app.fields.preview}: ${sequence.preview}`}',
                'title={dict.app.actions.viewDetails}',
                'aria-label={dict.app.actions.viewDetails}',
                'title={dict.app.actions.edit}',
                'aria-label={dict.app.actions.edit}',
                'title={sequence ? dict.app.actions.save : dict.app.actions.create}',
                'aria-label={sequence ? dict.app.actions.save : dict.app.actions.create}',
                'title={dict.app.actions.cancel}',
                'aria-label={dict.app.actions.cancel}',
                'title={dict.app.actions.close}',
                'aria-label={dict.app.actions.close}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} settings actions must expose stable accessible names.");
            }
        }
    }

    public function test_accounting_taxonomy_actions_have_accessible_names_and_scroll_safe_delete(): void
    {
        foreach ([
            'Accounting/AccountCategories.tsx' => [
                'createTitle' => 'title={accDict.addAccountCategory || pageDict.addAccountCategory}',
                'createAria' => 'aria-label={accDict.addAccountCategory || pageDict.addAccountCategory}',
                'detailsTitle' => 'title={pageDict.viewAccountTypesDetails}',
                'detailsAria' => 'aria-label={pageDict.viewAccountTypesDetails}',
                'deleteLabel' => 'const deleteActionLabel = cat.is_system',
                'deleteRoute' => 'router.delete(`/accounting/account-categories/${cat.id}`, { preserveScroll: true });',
                'oldDeleteRoute' => 'router.delete(`/accounting/account-categories/${cat.id}`);',
            ],
            'Accounting/AccountTypes.tsx' => [
                'createTitle' => 'title={accDict.addAccountType || pageDict.addAccountType}',
                'createAria' => 'aria-label={accDict.addAccountType || pageDict.addAccountType}',
                'detailsTitle' => 'title={pageDict.viewAccountGroupsDetails}',
                'detailsAria' => 'aria-label={pageDict.viewAccountGroupsDetails}',
                'detailsTitle2' => 'title={pageDict.viewAccountsDetails}',
                'detailsAria2' => 'aria-label={pageDict.viewAccountsDetails}',
                'deleteLabel' => 'const deleteActionLabel = at.is_system',
                'deleteRoute' => 'router.delete(`/accounting/account-types/${at.id}`, { preserveScroll: true });',
                'oldDeleteRoute' => 'router.delete(`/accounting/account-types/${at.id}`);',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                'preserveScroll: true',
                'title={actionsDict.cancel}',
                'aria-label={actionsDict.cancel}',
                'title={actionsDict.save}',
                'aria-label={actionsDict.save}',
                'title={actionsDict.edit}',
                'aria-label={actionsDict.edit}',
                'title={deleteActionLabel}',
                'aria-label={deleteActionLabel}',
                'title={actionsDict.close}',
                'aria-label={actionsDict.close}',
                $case['createTitle'],
                $case['createAria'],
                $case['detailsTitle'],
                $case['detailsAria'],
                $case['deleteLabel'],
                $case['deleteRoute'],
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} accounting taxonomy actions must be accessible and scroll-safe.");
            }

            foreach (['detailsTitle2', 'detailsAria2'] as $optionalKey) {
                if (isset($case[$optionalKey])) {
                    $this->assertStringContainsString($case[$optionalKey], $source, "{$relativePath} account type detail actions must be accessible.");
                }
            }

            $this->assertStringNotContainsString($case['oldDeleteRoute'], $source, "{$relativePath} delete actions must preserve table context.");
        }
    }

    public function test_accounting_setup_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        foreach ([
            'Accounting/ChartOfAccounts.tsx' => [
                "groupForm.post('/accounting/coa/groups', {",
                "accountForm.post('/accounting/coa/accounts', {",
                'title={accDict.addGroup}',
                'aria-label={accDict.addGroup}',
                'title={accDict.addAccount}',
                'aria-label={accDict.addAccount}',
                'title={actionsDict.cancel}',
                'aria-label={actionsDict.cancel}',
                'title={actionsDict.save}',
                'aria-label={actionsDict.save}',
            ],
            'Accounting/Currencies.tsx' => [
                "createForm.post('/accounting/currencies', {",
                'editForm.patch(`/accounting/currencies/${editingCurrency.code}`, {',
                'router.delete(`/accounting/currencies/${deletingCurrency.code}`, {',
                'title={accDict.addCurrency}',
                'aria-label={accDict.addCurrency}',
                'title={actionsDict.cancel}',
                'aria-label={actionsDict.cancel}',
                'title={actionsDict.save}',
                'aria-label={actionsDict.save}',
                'title={actionsDict.delete}',
                'aria-label={actionsDict.delete}',
                'title={actionsDict.close}',
                'aria-label={actionsDict.close}',
                'title={(c.accounts_count || 0) > 0 ? accDict.viewLinkedAccountsTitle : accDict.noLinkedAccountsTitle}',
                'aria-label={(c.accounts_count || 0) > 0 ? accDict.viewLinkedAccountsTitle : accDict.noLinkedAccountsTitle}',
                'title={(c.exchange_rates_count || 0) > 0 ? accDict.viewRecordedFxRatesTitle : accDict.noRatesRecordedTitle}',
                'aria-label={(c.exchange_rates_count || 0) > 0 ? accDict.viewRecordedFxRatesTitle : accDict.noRatesRecordedTitle}',
                'title={hasLinkedRecords ? accDict.cannotDeleteCurrencyInUseTitle : accDict.deleteCurrencyTitle}',
                'aria-label={hasLinkedRecords ? accDict.cannotDeleteCurrencyInUseTitle : accDict.deleteCurrencyTitle}',
            ],
            'Accounting/ExchangeRates.tsx' => [
                "form.post('/accounting/fx-rates', {",
                'title={accDict.setFxRate}',
                'aria-label={accDict.setFxRate}',
                'title={actionsDict.close}',
                'aria-label={actionsDict.close}',
                'title={actionsDict.cancel}',
                'aria-label={actionsDict.cancel}',
                'title={accDict.saveFxRate}',
                'aria-label={accDict.saveFxRate}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} accounting setup actions must expose stable accessible names.");
            }

            $this->assertStringContainsString('preserveScroll: true', $source, "{$relativePath} save/delete flows must preserve accountant table context.");
        }
    }

    public function test_financial_mapping_and_period_actions_have_accessible_names_and_scroll_safe_flows(): void
    {
        foreach ([
            'Accounting/FinancialStatementMappings.tsx' => [
                'title={accDict.addStatementLine}',
                'aria-label={accDict.addStatementLine}',
                'title={accDict.allStatementTypes}',
                'aria-label={accDict.allStatementTypes}',
                'title={accDict.balanceSheet}',
                'aria-label={accDict.balanceSheet}',
                'title={accDict.incomeStatement}',
                'aria-label={accDict.incomeStatement}',
                'title={accDict.assignAccount}',
                'aria-label={accDict.assignAccount}',
                'title={`${actionsDict.edit} ${line.code}`}',
                'aria-label={`${actionsDict.edit} ${line.code}`}',
                'title={`${actionsDict.delete} ${line.code}`}',
                'aria-label={`${actionsDict.delete} ${line.code}`}',
                'title={`${accDict.unassign} ${acc.code}`}',
                'aria-label={`${accDict.unassign} ${acc.code}`}',
                'title={actionsDict.close}',
                'aria-label={actionsDict.close}',
                'title={actionsDict.cancel}',
                'aria-label={actionsDict.cancel}',
                'title={actionsDict.save}',
                'aria-label={actionsDict.save}',
                'preserveScroll: true',
            ],
            'Accounting/Periods.tsx' => [
                "yearForm.post('/accounting/periods/fiscal-years', {",
                'actionForm.post(endpoint, {',
                'title={tx(\'createFiscalYear\')}',
                'aria-label={tx(\'createFiscalYear\')}',
                'title={ax(\'cancel\')}',
                'aria-label={ax(\'cancel\')}',
                'title={tx(\'generate12Periods\')}',
                'aria-label={tx(\'generate12Periods\')}',
                'title={ax(\'close\')}',
                'aria-label={ax(\'close\')}',
                'title={modalMode === \'close\' ? tx(\'closePeriod\') : tx(\'reopenPeriod\')}',
                'aria-label={modalMode === \'close\' ? tx(\'closePeriod\') : tx(\'reopenPeriod\')}',
                'title={`${tx(\'reopenPeriod\')} - ${tx(\'month\')} ${p.month}`}',
                'aria-label={`${tx(\'reopenPeriod\')} - ${tx(\'month\')} ${p.month}`}',
                'title={`${tx(\'closePeriod\')} - ${tx(\'month\')} ${p.month}`}',
                'aria-label={`${tx(\'closePeriod\')} - ${tx(\'month\')} ${p.month}`}',
                'preserveScroll: true',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} accounting control actions must stay accessible and preserve page context.");
            }
        }
    }

    public function test_settings_user_role_actions_have_accessible_names_and_scroll_safe_security_actions(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Settings/Users.tsx'));

        foreach ([
            'title={dict.app.actions.close}',
            'aria-label={dict.app.actions.close}',
            'title={dict.app.actions.cancel}',
            'aria-label={dict.app.actions.cancel}',
            'title={user ? dict.app.actions.save : dict.app.actions.create}',
            'aria-label={user ? dict.app.actions.save : dict.app.actions.create}',
            'title={dict.app.actions.assign}',
            'aria-label={dict.app.actions.assign}',
            'title={data.permissions.length === allPermissions.length ? dict.app.actions.clearAll : dict.app.actions.selectAll}',
            'aria-label={data.permissions.length === allPermissions.length ? dict.app.actions.clearAll : dict.app.actions.selectAll}',
            'title={catTitle}',
            'aria-label={catTitle}',
            'title={isCurrentGroupAllSelected ? dict.app.actions.deselectModule : dict.app.actions.selectAllInModule}',
            'aria-label={isCurrentGroupAllSelected ? dict.app.actions.deselectModule : dict.app.actions.selectAllInModule}',
            'title={role ? dict.app.actions.save : dict.app.actions.create}',
            'aria-label={role ? dict.app.actions.save : dict.app.actions.create}',
            'title={expanded ? dict.app.actions.showLess : `+ ${remainingCount} ${dict.app.actions.showMore}`}',
            'aria-label={expanded ? dict.app.actions.showLess : `+ ${remainingCount} ${dict.app.actions.showMore}`}',
            'title={dict.app.actions.revoke}',
            'aria-label={dict.app.actions.revoke}',
            'title={dict.app.actions.deleteRole}',
            'aria-label={dict.app.actions.deleteRole}',
            'title={isSelf ? dict.app.messages.cannotDeleteSelf : dict.app.actions.deleteUser}',
            'aria-label={isSelf ? dict.app.messages.cannotDeleteSelf : dict.app.actions.deleteUser}',
            'title={dict.app.actions.addUser}',
            'aria-label={dict.app.actions.addUser}',
            'title={`${dict.app.actions.assign} ${dict.app.fields.roles}`}',
            'aria-label={`${dict.app.actions.assign} ${dict.app.fields.roles}`}',
            'title={dict.app.actions.addRole}',
            'aria-label={dict.app.actions.addRole}',
            'title={dict.app.fields.user}',
            'aria-label={dict.app.fields.user}',
            'title={dict.app.fields.roles}',
            'aria-label={dict.app.fields.roles}',
            'title={dict.app.actions.editRole}',
            'aria-label={dict.app.actions.editRole}',
            'aria-label={dict.app.actions.editUser}',
            "destroy('/settings/users/roles', { preserveScroll: true });",
            'destroy(`/settings/roles/${roleId}`, { preserveScroll: true });',
            'destroy(`/settings/users/${userId}`, { preserveScroll: true });',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Settings user/role security actions must stay accessible and scroll-safe.');
        }
    }

    public function test_settings_attachment_entity_selectors_use_searchable_controls(): void
    {
        foreach ([
            'Settings/Company.tsx' => [
                'const companyAttachmentOptions',
                'function selectAttachmentCompany(value: string | null)',
                'label={dict.app.pages.settingsCompany.selectCompanyForAttachments}',
                'value={selectedCompanyId}',
                'onChange={selectAttachmentCompany}',
                'options={companyAttachmentOptions}',
                'isClearable={false}',
            ],
            'Settings/Branches.tsx' => [
                'const branchAttachmentOptions',
                'function selectAttachmentBranch(value: string | null)',
                'label={dict.app.pages.settingsBranches.selectBranchForAttachments}',
                'value={selectedBranchId}',
                'onChange={selectAttachmentBranch}',
                'options={branchAttachmentOptions}',
                'isClearable={false}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should not use native select controls.");
            $this->assertStringNotContainsString('<option', $source, "{$relativePath} should not render native option controls.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_operational_document_pages_do_not_use_silent_currency_uom_or_warehouse_fallbacks(): void
    {
        foreach ([
            'Sales/SalesOrders.tsx' => [
                "|| 'USD'",
                "|| 'PCS'",
                'e.g. PO-CUST-123',
            ],
            'Purchasing/PurchaseOrders.tsx' => [
                "|| 'USD'",
                "|| 'PCS'",
                'e.g. RFQ-SUPP-99',
            ],
            'Sales/DeliveryNotes.tsx' => [
                "|| 'PCS'",
                'e.g. TRUCK-DELIV-01',
            ],
            'Purchasing/GoodsReceipts.tsx' => [
                "|| 'PCS'",
                'e.g. VENDOR-DELIV-99',
            ],
            'Purchasing/SupplierBills.tsx' => [
                ": 'USD'",
                "|| 'USD'",
            ],
            'Sales/CustomerInvoices.tsx' => [
                ": 'USD'",
            ],
            'Sales/CustomerCreditNotes.tsx' => [
                ": 'USD'",
            ],
            'Purchasing/SupplierAdjustmentNotes.tsx' => [
                ": 'USD'",
            ],
            'Purchasing/LandedCosts.tsx' => [
                ": 'EGP'",
                "|| 'EGP'",
            ],
            'Inventory/StockBalances.tsx' => [
                "|| 'EGP'",
                "|| 'MAIN'",
            ],
            'Inventory/StockCounts.tsx' => [
                ": 'EGP'",
            ],
            'Inventory/StockAdjustments.tsx' => [
                ": 'EGP'",
            ],
        ] as $relativePath => $forbiddenFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not use silent operational fallback values.");
            }
        }

        foreach ([
            'Inventory/StockCounts.tsx',
            'Inventory/StockAdjustments.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('currencies: CurrencyRow[]', $source);
            $this->assertStringContainsString('currencyOptions', $source);
            $this->assertStringContainsString('placeholder={pageDict.currency}', $source);
        }

        $inventoryOptions = (string) file_get_contents(app_path('Application/Inventory/InventoryPageOptions.php'));
        $this->assertStringContainsString('Currency::query()', $inventoryOptions);

        $this->assertStringContainsString("'currencies' => \$this->inventoryPageOptions->currencies()", (string) file_get_contents(app_path('Application/Inventory/StockCountPageData.php')));
        $this->assertStringContainsString("'currencies' => \$this->inventoryPageOptions->currencies()", (string) file_get_contents(app_path('Application/Inventory/StockAdjustmentPageData.php')));

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'salesSalesOrders', 'referencePlaceholder'],
            ['app', 'pages', 'salesSalesOrders', 'noUom'],
            ['app', 'pages', 'purchasingPurchaseOrders', 'referencePlaceholder'],
            ['app', 'pages', 'purchasingPurchaseOrders', 'noUom'],
            ['app', 'pages', 'salesDeliveryNotes', 'referencePlaceholder'],
            ['app', 'pages', 'salesDeliveryNotes', 'noUom'],
            ['app', 'pages', 'purchasingGoodsReceipts', 'referencePlaceholder'],
            ['app', 'pages', 'purchasingGoodsReceipts', 'noUom'],
            ['app', 'pages', 'purchasingLandedCosts', 'noCurrency'],
            ['app', 'pages', 'stockBalances', 'noCurrency'],
            ['app', 'pages', 'stockCounts', 'currency'],
            ['app', 'pages', 'stockAdjustments', 'currency'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_payroll_expense_rental_and_fixed_asset_pages_do_not_use_silent_currency_fallbacks(): void
    {
        foreach ([
            'Payroll/Runs.tsx',
            'Payroll/Employees.tsx',
            'Payroll/Components.tsx',
            'Expenses/Index.tsx',
            'Expenses/Prepaids.tsx',
            'Expenses/Accruals.tsx',
            'Rentals/Contracts.tsx',
            'Rentals/RentableItems.tsx',
            'Rentals/Invoices.tsx',
            'Rentals/Returns.tsx',
            'FixedAssets/Create.tsx',
            'FixedAssets/Disposals/Show.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "|| 'EGP'",
                ": 'EGP'",
                'currency="EGP"',
                'currency={"EGP"}',
                "value || 'EGP'",
                "currency={rentalReturn.contract?.currency || 'EGP'}",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not use a silent EGP fallback.");
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['app', 'pages', 'expenses', 'noCurrency'],
            ['app', 'pages', 'payrollRuns', 'noCurrency'],
            ['app', 'pages', 'rentalInvoices', 'noCurrency'],
            ['app', 'pages', 'rentalReturns', 'noCurrency'],
            ['app', 'fixedAssetsDisposals', 'noCurrency'],
        ] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_visible_pages_do_not_hardcode_egp_or_usd_currency_literals(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js/Pages')));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace(resource_path('js/Pages').DIRECTORY_SEPARATOR, '', $file->getPathname());

            foreach (["'EGP'", '"EGP"', "'USD'", '"USD"'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not hardcode visible currency literals.");
            }
        }
    }

    public function test_ar_ap_aging_and_reconciliation_reports_do_not_use_hidden_currency_defaults(): void
    {
        foreach ([
            'Reports/ArAging.tsx',
            'Reports/ApAging.tsx',
            'Reports/ArGlReconciliation.tsx',
            'Reports/ApGlReconciliation.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "|| 'EGP'",
                "val || 'EGP'",
                "filters.currency || 'EGP'",
                "report.currency || 'EGP'",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not use hidden currency defaults.");
            }

            $this->assertStringContainsString("setCurrency(val || '')", $source, "{$relativePath} must preserve cleared currency selection explicitly.");
        }
    }

    public function test_operational_report_summaries_do_not_label_unfiltered_totals_as_egp(): void
    {
        foreach ([
            'Reports/SalesOrdersReport.tsx',
            'Reports/PurchaseOrdersReport.tsx',
            'Reports/CustomerInvoicesReport.tsx',
            'Reports/SupplierBillsReport.tsx',
            'Reports/StockMovementsReport.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "filters.currency || 'EGP'",
                "currency || 'EGP'",
                "|| 'EGP'",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not label unfiltered mixed-currency totals as EGP.");
            }

            $this->assertStringContainsString('mixedCurrencyAmount', $source, "{$relativePath} must show a mixed-currency label when no currency filter is selected.");
        }

        $fixedAssetReportUtils = (string) file_get_contents(resource_path('js/Pages/Reports/fixedAssetReportUtils.ts'));
        $this->assertStringNotContainsString("currency = 'EGP'", $fixedAssetReportUtils);
        $this->assertStringContainsString("currency = ''", $fixedAssetReportUtils);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', 'reports', 'mixedCurrencyAmount'], $locale);
            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', 'stockMovementReport', 'mixedCurrencyAmount'], $locale);
        }
    }

    public function test_fixed_asset_report_filters_use_searchable_select_controls(): void
    {
        foreach ([
            'Reports/FixedAssetRegisterReport.tsx',
            'Reports/FixedAssetNetBookValueReport.tsx',
            'Reports/FixedAssetDepreciationReport.tsx',
            'Reports/FixedAssetDepreciationRunReport.tsx',
            'Reports/FixedAssetDisposalReport.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString("import SearchableSelect from '../../Components/SearchableSelect';", $source);
            $this->assertStringContainsString('options={statusOptions}', $source);
            $this->assertStringContainsString("onChange={(value) => setStatus(value || '')}", $source);
            $this->assertStringContainsString('placeholder={reportDict.allStatuses}', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should use searchable report filter controls.");
            $this->assertStringNotContainsString('window.location.href', $source);
        }

        $disposalReport = (string) file_get_contents(resource_path('js/Pages/Reports/FixedAssetDisposalReport.tsx'));
        $this->assertStringContainsString('options={typeOptions}', $disposalReport);
        $this->assertStringContainsString("onChange={(value) => setType(value || '')}", $disposalReport);
        $this->assertStringContainsString('placeholder={reportDict.allTypes}', $disposalReport);
    }

    public function test_tax_master_pages_use_dictionary_backed_visible_text_without_inline_fallbacks(): void
    {
        foreach ([
            'Taxes/Codes/Index.tsx',
            'Taxes/Codes/Create.tsx',
            'Taxes/Codes/Edit.tsx',
            'Taxes/Rates/Index.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach (["|| 'Tax", "|| 'Search", "|| 'Are", "|| '-'", "|| '—'", '(dict.app as any).taxes', 'e.target.value as any', 'locale === \'ar\'', 'Are you sure', "'Tax Codes'", "'Tax Rates'", 'Search tax code', 'e.g. VAT_STD_14'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep tax master visible text dictionary-backed.");
            }

            $this->assertStringContainsString('dict.app.taxes', $source, "{$relativePath} must use the typed tax dictionary path.");
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (['name', 'search', 'searchTaxCode', 'createSubtitle', 'codePlaceholder', 'rateBpsInput', 'basisPointsSuffix', 'allTaxCodes', 'notAvailable'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'taxes', $key], $locale);
            }
        }
    }

    public function test_tax_master_select_controls_use_searchable_selects(): void
    {
        foreach ([
            'Taxes/Codes/Create.tsx' => ['calculationModeOptions', 'recoverabilityModeOptions'],
            'Taxes/Codes/Edit.tsx' => ['calculationModeOptions', 'recoverabilityModeOptions'],
            'Taxes/Rates/Index.tsx' => ['taxCodeOptions', 'taxCodeFilterOptions'],
        ] as $relativePath => $expectedFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should use shared searchable tax controls.");
            $this->assertStringNotContainsString('e.target.value as ', $source, "{$relativePath} should avoid event-value casts for select controls.");

            foreach ($expectedFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_tax_period_pages_use_dictionary_backed_visible_text_without_inline_fallbacks(): void
    {
        foreach ([
            'Taxes/Periods/Index.tsx',
            'Taxes/Periods/Show.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach (["|| 'Tax", "|| 'Generate", "|| 'File", "|| 'Create", "|| 'Status", "|| '—'", 'getDictionary(locale) as any', 'Period Status', 'Filing Reference', 'Locking Guard', 'Output VAT Breakdown', 'Input VAT Breakdown', 'Confirm & Lock Tax Period', 'Create a tax period', 'OPEN FOR POSTINGS', 'LOCKED (Postings Blocked)'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep tax period visible text dictionary-backed.");
            }

            $this->assertStringContainsString('const dict = getDictionary(locale);', $source, "{$relativePath} must use the typed dictionary.");
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (['emptyPeriodsDescription', 'periodLabelHelp', 'periodLabelPlaceholder', 'cancel', 'saving', 'createPeriodAction', 'taxPeriod', 'dateRangeSeparator', 'periodStatus', 'filedDate', 'notFiled', 'notAvailable', 'lockingGuard', 'lockedGuard', 'openPostingGuard', 'emptyDraftTitle', 'emptyDraftDescription', 'outputBreakdown', 'inputBreakdown', 'taxCode', 'rate', 'taxableSubtotal', 'taxAmount', 'grossTotal', 'filingNotes', 'filing', 'confirmAndLockTaxPeriod'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'taxes', 'periods', $key], $locale);
            }
        }
    }

    public function test_audit_log_page_uses_canonical_dictionary_without_fallback_chains(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/AuditLog/Index.tsx'));

        foreach ([
            '(dict.app as any)',
            'dict.app.pages.auditLog',
            'placeholder="req-..."',
            '`User #${log.actor_id}`',
            "|| '-'",
            '>-</span>',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Audit log page must use canonical dictionary labels without legacy fallback chains.');
        }

        $this->assertStringContainsString('const auditDict = dict.app.audit;', $source);
        $this->assertStringContainsString('const actionsDict = dict.app.actions;', $source);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                'title',
                'description',
                'empty',
                'requestIdPlaceholder',
                'system',
                'userFallbackPrefix',
                'notAvailable',
            ] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'audit', $key], $locale);
            }
        }
    }

    public function test_audit_log_actions_have_accessible_names_and_scroll_safe_navigation(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/AuditLog/Index.tsx'));

        foreach ([
            '{ preserveState: true, preserveScroll: true }',
            'router.get(\'/audit-log\', {}, { preserveState: true, preserveScroll: true });',
            'router.get(logs.prev_page_url!, {}, { preserveState: true, preserveScroll: true })',
            'router.get(logs.next_page_url!, {}, { preserveState: true, preserveScroll: true })',
            'title={actionsDict.reset}',
            'aria-label={actionsDict.reset}',
            'title={actionsDict.filter}',
            'aria-label={actionsDict.filter}',
            'title={`${log.entity_id} - ${actionsDict.viewDetails}`}',
            'aria-label={`${log.entity_id} - ${actionsDict.viewDetails}`}',
            'title={`${log.request_id || auditDict.notAvailable} - ${actionsDict.viewDetails}`}',
            'aria-label={`${log.request_id || auditDict.notAvailable} - ${actionsDict.viewDetails}`}',
            'title={auditDict.viewPayload}',
            'aria-label={auditDict.viewPayload}',
            'title={actionsDict.previous}',
            'aria-label={actionsDict.previous}',
            'title={actionsDict.next}',
            'aria-label={actionsDict.next}',
            'title={actionsDict.close}',
            'aria-label={actionsDict.close}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Audit log actions must remain accessible and scroll-safe.');
        }
    }

    public function test_app_layout_navigation_uses_typed_dictionaries_without_visible_fallbacks(): void
    {
        $source = (string) file_get_contents(resource_path('js/Components/AppLayout.tsx'));

        foreach ([
            '(dict.app as any)',
            "|| 'Accounting Core'",
            "|| 'Administration'",
            'dict.app.nav.groups.overview ||',
            'dict.app.nav.groups.modules ||',
            'dict.app.nav.groups.administration ||',
            'accDict.accountCategories ||',
            'taxesDict.periods?.title',
            "props.auth.user?.name || 'Admin'",
            "props.auth.user?.email || 'admin@mini-erp.local'",
            "locale === 'ar' ? 'EN' : 'ع'",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'AppLayout navigation/header labels must use typed dictionary keys without visible fallbacks.');
        }

        $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
        $this->assertStringContainsString('const taxesDict = dict.app.taxes;', $source);
        $this->assertStringContainsString('dict.app.header.unknownUser', $source);
        $this->assertStringContainsString('dict.app.header.unknownEmail', $source);
        $this->assertStringContainsString('dict.common.language.en', $source);
        $this->assertStringContainsString('dict.common.language.ar', $source);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (['overview', 'modules', 'administration'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'nav', 'groups', $key], $locale);
            }

            foreach (['unknownUser', 'unknownEmail'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'header', $key], $locale);
            }
        }
    }

    public function test_accounting_master_pages_use_typed_dictionaries_without_select_any_casts(): void
    {
        foreach ([
            'Accounting/Currencies.tsx',
            'Accounting/ExchangeRates.tsx',
            'Accounting/AccountCategories.tsx',
            'Accounting/AccountTypes.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                '(dict.app as any)',
                '(val as any)',
                'dict.app.actions || {}',
                'dict.app.accounting || {}',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must use typed dictionary paths and explicit select-value parsing.");
            }
        }

        $accountCategories = (string) file_get_contents(resource_path('js/Pages/Accounting/AccountCategories.tsx'));
        $accountTypes = (string) file_get_contents(resource_path('js/Pages/Accounting/AccountTypes.tsx'));

        foreach ([$accountCategories, $accountTypes] as $source) {
            $this->assertStringContainsString('function toNormalBalance', $source);
            $this->assertStringContainsString('function toStatementType', $source);
        }
    }

    public function test_accounting_period_opening_trial_and_coa_pages_use_typed_dictionaries(): void
    {
        foreach ([
            'Accounting/ChartOfAccounts.tsx',
            'Accounting/Periods.tsx',
            'Accounting/OpeningBalances.tsx',
            'Accounting/TrialBalance.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                '(dict.app as any)',
                'dict.app.actions || {}',
                'as Record<string, unknown>',
                "(val as 'debit' | 'credit')",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep accounting dictionaries typed and select parsing explicit.");
            }

            $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
        }

        $chartOfAccounts = (string) file_get_contents(resource_path('js/Pages/Accounting/ChartOfAccounts.tsx'));
        $periods = (string) file_get_contents(resource_path('js/Pages/Accounting/Periods.tsx'));

        $this->assertStringContainsString('function toAccountNature', $chartOfAccounts);
        $this->assertStringContainsString('const actionsDict = dict.app.actions;', $chartOfAccounts);
        $this->assertStringContainsString('const actionsDict = dict.app.actions;', $periods);
        $this->assertStringContainsString('const txDynamic =', $periods);
    }

    public function test_journal_and_ledger_dense_pages_use_typed_dictionaries(): void
    {
        foreach ([
            'Accounting/GeneralLedger.tsx',
            'Accounting/GeneralJournal.tsx',
            'Accounting/JournalForm.tsx',
            'Accounting/JournalDetail.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                '(dict.app as any)',
                'dict.app.actions || {}',
                'return map[s] || status',
                'value: any',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep journal/ledger dictionaries typed and fallbacks canonical.");
            }

            $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
        }

        $journalForm = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalForm.tsx'));
        $generalJournal = (string) file_get_contents(resource_path('js/Pages/Accounting/GeneralJournal.tsx'));
        $journalDetail = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalDetail.tsx'));

        $this->assertStringContainsString('type JournalLineDraft', $journalForm);
        $this->assertStringContainsString('const actionsDict = dict.app.actions;', $journalForm);
        $this->assertStringContainsString('accDict.statusUnknown', $generalJournal);
        $this->assertStringContainsString('accDict.statusUnknown', $journalDetail);
        $this->assertStringContainsString('accDict.notAvailable', $generalJournal);
        $this->assertStringContainsString('accDict.notAvailable', $journalDetail);
    }

    public function test_fixed_asset_pages_use_typed_dictionaries_and_canonical_missing_labels(): void
    {
        foreach ([
            'FixedAssets/Index.tsx',
            'FixedAssets/Create.tsx',
            'FixedAssets/Edit.tsx',
            'FixedAssets/Show.tsx',
            'FixedAssets/Categories.tsx',
            'FixedAssets/Locations.tsx',
            'FixedAssets/DepreciationRuns/Index.tsx',
            'FixedAssets/DepreciationRuns/Preview.tsx',
            'FixedAssets/DepreciationRuns/Show.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                '(dict.app as any)',
                'dict.app.accounting || {}',
                'appDict[',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep fixed-asset accounting UI labels typed.");
            }

            $this->assertStringContainsString('const appDict = dict.app.accounting;', $source);
        }

        foreach ([
            'FixedAssets/Disposals/Index.tsx',
            'FixedAssets/Disposals/Show.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                '(dict.app as any)',
                'appDict[',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep fixed-asset disposal labels typed.");
            }

            $this->assertStringContainsString('const appDict = dict.app.fixedAssetsDisposals;', $source);
            $this->assertStringContainsString('function formatDisposalType', $source);
            $this->assertStringContainsString('appDict.notAvailable', $source);
        }

        $assetShow = (string) file_get_contents(resource_path('js/Pages/FixedAssets/Show.tsx'));
        $this->assertStringContainsString('const disposalDict = dict.app.fixedAssetsDisposals;', $assetShow);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', 'scheduleStatusSkipped'], $locale);
            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'fixedAssetsDisposals', 'notAvailable'], $locale);
        }
    }

    public function test_cross_module_selects_use_explicit_parsers_without_any_casts(): void
    {
        foreach ([
            'Customers/Index.tsx',
            'Suppliers/Index.tsx',
            'CustomerReceipts/Index.tsx',
            'SupplierPayments/Index.tsx',
            'Catalog/Products.tsx',
            'Accounting/FinancialStatementMappings.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringNotContainsString('e.target.value as any', $source, "{$relativePath} must parse select values explicitly.");
        }

        $customers = (string) file_get_contents(resource_path('js/Pages/Customers/Index.tsx'));
        $suppliers = (string) file_get_contents(resource_path('js/Pages/Suppliers/Index.tsx'));
        $customerReceipts = (string) file_get_contents(resource_path('js/Pages/CustomerReceipts/Index.tsx'));
        $supplierPayments = (string) file_get_contents(resource_path('js/Pages/SupplierPayments/Index.tsx'));
        $products = (string) file_get_contents(resource_path('js/Pages/Catalog/Products.tsx'));
        $statementMappings = (string) file_get_contents(resource_path('js/Pages/Accounting/FinancialStatementMappings.tsx'));

        $this->assertStringContainsString('function toCustomerStatus', $customers);
        $this->assertStringContainsString('function toSupplierStatus', $suppliers);
        $this->assertStringContainsString('function toProductType', $products);
        $this->assertStringContainsString('function toProductStatus', $products);
        $this->assertStringContainsString('function toStatementType', $statementMappings);
        $this->assertStringContainsString('function toNormalBalance', $statementMappings);
        $this->assertStringContainsString('useForm<StatementLineForm>', $statementMappings);

        foreach ([$customerReceipts, $supplierPayments] as $source) {
            $this->assertStringContainsString('function toCashBankDestinationType', $source);
            $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
            $this->assertStringContainsString('accDict.notAvailable', $source);

            foreach (["currency: 'EGP'", "val || 'EGP'", 'خزينة:', 'بنك:'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, 'Cash/bank operational pages must not hide currency or Arabic destination fallbacks in React.');
            }
        }
    }

    public function test_sales_and_purchase_order_select_controls_use_searchable_controls(): void
    {
        foreach ([
            'Sales/SalesOrders.tsx' => [
                'const statusFilterOptions',
                'const customerOptions',
                'const currencyOptions',
                'const productOptions',
                'options={statusFilterOptions}',
                'options={customerOptions}',
                'options={currencyOptions}',
                'options={productOptions}',
                'onChange={(value) => setData(\'customer_id\', value || \'\')}',
                'onChange={(value) => handleProductChange(idx, value || \'\')}',
            ],
            'Purchasing/PurchaseOrders.tsx' => [
                'const statusFilterOptions',
                'const supplierOptions',
                'const currencyOptions',
                'const productOptions',
                'options={statusFilterOptions}',
                'options={supplierOptions}',
                'options={currencyOptions}',
                'options={productOptions}',
                'onChange={(value) => setData(\'supplier_id\', value || \'\')}',
                'onChange={(value) => handleProductChange(idx, value || \'\')}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should not use native select controls.");
            $this->assertStringNotContainsString('<option', $source, "{$relativePath} should not render native option controls.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_delivery_note_and_goods_receipt_select_controls_use_searchable_controls(): void
    {
        foreach ([
            'Sales/DeliveryNotes.tsx' => [
                'const warehouseOptions',
                'const warehouseFilterOptions',
                'const statusFilterOptions',
                'const salesOrderOptions',
                'options={warehouseFilterOptions}',
                'options={statusFilterOptions}',
                'options={salesOrderOptions}',
                'options={warehouseOptions}',
                'onChange={(value) => handleSalesOrderSelect(value || \'\')}',
                'onChange={(value) => setData(\'warehouse_id\', value || \'\')}',
            ],
            'Purchasing/GoodsReceipts.tsx' => [
                'const warehouseOptions',
                'const warehouseFilterOptions',
                'const statusFilterOptions',
                'const purchaseOrderOptions',
                'options={warehouseFilterOptions}',
                'options={statusFilterOptions}',
                'options={purchaseOrderOptions}',
                'options={warehouseOptions}',
                'onChange={(value) => handlePurchaseOrderSelect(value || \'\')}',
                'onChange={(value) => setData(\'warehouse_id\', value || \'\')}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should not use native select controls.");
            $this->assertStringNotContainsString('<option', $source, "{$relativePath} should not render native option controls.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_sales_and_purchasing_document_action_cells_are_grouped_and_explain_empty_states(): void
    {
        foreach ([
            'Sales/SalesOrders.tsx' => [
                'permissionChecks' => [
                    "const canEditSalesOrders = can('sales.edit');",
                    "const canSubmitSalesOrders = can('sales.submit');",
                    "const canConfirmSalesOrders = can('sales.approve');",
                    "const canCancelSalesOrders = can('sales.cancel');",
                ],
                'helpers' => [
                    'const isSalesOrderActionable',
                    'const hasAvailableSalesOrderAction',
                    'const getSalesOrderActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('sales.edit') ? (",
                    "can('sales.submit') ? (",
                    "can('sales.approve') ? (",
                    "can('sales.cancel') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.salesSalesOrders.edit}',
                    'title={dict.app.pages.salesSalesOrders.submit}',
                    'title={dict.app.pages.salesSalesOrders.confirm}',
                    'title={dict.app.pages.salesSalesOrders.cancel}',
                ],
            ],
            'Purchasing/PurchaseOrders.tsx' => [
                'permissionChecks' => [
                    "const canEditPurchaseOrders = can('purchasing.edit');",
                    "const canSubmitPurchaseOrders = can('purchasing.submit');",
                    "const canConfirmPurchaseOrders = can('purchasing.approve');",
                    "const canCancelPurchaseOrders = can('purchasing.cancel');",
                ],
                'helpers' => [
                    'const isPurchaseOrderActionable',
                    'const hasAvailablePurchaseOrderAction',
                    'const getPurchaseOrderActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('purchasing.edit') ? (",
                    "can('purchasing.submit') ? (",
                    "can('purchasing.approve') ? (",
                    "can('purchasing.cancel') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.purchasingPurchaseOrders.edit}',
                    'title={dict.app.pages.purchasingPurchaseOrders.submit}',
                    'title={dict.app.pages.purchasingPurchaseOrders.confirm}',
                    'title={dict.app.pages.purchasingPurchaseOrders.cancel}',
                ],
            ],
            'Sales/DeliveryNotes.tsx' => [
                'permissionChecks' => [
                    "const canEditDeliveryNotes = can('sales.edit');",
                    "const canConfirmDeliveryNotes = can('sales.approve');",
                    "const canCancelDeliveryNotes = can('sales.cancel');",
                ],
                'helpers' => [
                    'const hasAvailableDeliveryNoteAction',
                    'const getDeliveryNoteActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('sales.edit') ? (",
                    "can('sales.approve') ? (",
                    "can('sales.cancel') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.salesDeliveryNotes.edit}',
                    'title={dict.app.pages.salesDeliveryNotes.confirm}',
                    'title={dict.app.pages.salesDeliveryNotes.cancel}',
                ],
            ],
            'Purchasing/GoodsReceipts.tsx' => [
                'permissionChecks' => [
                    "const canEditGoodsReceipts = can('purchasing.edit');",
                    "const canConfirmGoodsReceipts = can('purchasing.approve');",
                    "const canCancelGoodsReceipts = can('purchasing.cancel');",
                ],
                'helpers' => [
                    'const hasAvailableGoodsReceiptAction',
                    'const getGoodsReceiptActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('purchasing.edit') ? (",
                    "can('purchasing.approve') ? (",
                    "can('purchasing.cancel') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.purchasingGoodsReceipts.edit}',
                    'title={dict.app.pages.purchasingGoodsReceipts.confirm}',
                    'title={dict.app.pages.purchasingGoodsReceipts.cancel}',
                ],
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('className="flex flex-wrap items-center justify-end gap-2"', $source);
            $this->assertStringContainsString('dict.app.actions.restricted', $source);
            $this->assertStringContainsString('dict.app.actions.noActions', $source);
            $this->assertStringContainsString('StatusBadge tone="muted"', $source);
            $this->assertStringContainsString('aria-label=', $source);
            $this->assertStringNotContainsString('text-end space-x-2 rtl:space-x-reverse', $source, "{$relativePath} should use the stable grouped action wrapper.");

            foreach ($case['permissionChecks'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['helpers'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['actionTitles'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['oldInlinePermissionFragments'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} should compute action permissions before rendering row actions.");
            }
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertLocalePathIsNotEmpty($en, ['app', 'actions', 'noActions'], 'EN');
        $this->assertLocalePathIsNotEmpty($ar, ['app', 'actions', 'noActions'], 'AR');
    }

    public function test_sales_and_purchasing_invoice_return_action_cells_are_grouped_and_explain_empty_states(): void
    {
        foreach ([
            'Sales/CustomerInvoices.tsx' => [
                'permissionChecks' => [
                    "const canEditCustomerInvoices = can('sales.edit');",
                    "const canSubmitCustomerInvoices = can('sales.submit');",
                    "const canApproveCustomerInvoices = can('sales.approve');",
                    "const canPostCustomerInvoices = can('sales.post') && can('view_financials');",
                    "const canCancelCustomerInvoices = can('sales.cancel');",
                ],
                'helpers' => [
                    'const isCustomerInvoiceActionable',
                    'const hasAvailableCustomerInvoiceAction',
                    'const getCustomerInvoiceActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('sales.edit') ? (",
                    "can('sales.submit') ? (",
                    "can('sales.approve') ? (",
                    "can('sales.post') && can('view_financials') ? (",
                    "can('sales.cancel') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.salesCustomerInvoices.edit}',
                    'title={dict.app.pages.salesCustomerInvoices.submit}',
                    'title={dict.app.pages.salesCustomerInvoices.approve}',
                    'title={dict.app.pages.salesCustomerInvoices.postToArGl}',
                    'title={dict.app.pages.salesCustomerInvoices.cancel}',
                ],
                'requiredLinks' => [],
            ],
            'Purchasing/SupplierBills.tsx' => [
                'permissionChecks' => [
                    "const canEditSupplierBills = can('purchasing.edit');",
                    "const canSubmitSupplierBills = can('purchasing.submit');",
                    "const canApproveSupplierBills = can('purchasing.approve');",
                    "const canPostSupplierBills = can('purchasing.post') && can('view_financials');",
                    "const canCancelSupplierBills = can('purchasing.cancel');",
                ],
                'helpers' => [
                    'const isSupplierBillActionable',
                    'const hasAvailableSupplierBillAction',
                    'const getSupplierBillActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('purchasing.edit') ? (",
                    "can('purchasing.submit') ? (",
                    "can('purchasing.approve') ? (",
                    "can('purchasing.post') && can('view_financials') ? (",
                    "can('purchasing.cancel') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.purchasingSupplierBills.edit}',
                    'title={dict.app.pages.purchasingSupplierBills.submit}',
                    'title={dict.app.pages.purchasingSupplierBills.approve}',
                    'title={dict.app.pages.purchasingSupplierBills.post}',
                    'title={dict.app.pages.purchasingSupplierBills.cancel}',
                ],
                'requiredLinks' => [],
            ],
            'Sales/SalesReturns.tsx' => [
                'permissionChecks' => [
                    "const canManageSalesReturns = can('sales.returns');",
                    "const canPostSalesReturns = canManageSalesReturns && can('view_financials');",
                ],
                'helpers' => [
                    'const isSalesReturnActionable',
                    'const hasAvailableSalesReturnAction',
                    'const getSalesReturnActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('sales.returns') ? (",
                    "can('sales.returns') && can('view_financials') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.salesSalesReturns.edit}',
                    'title={dict.app.pages.salesSalesReturns.submit}',
                    'title={dict.app.pages.salesSalesReturns.approve}',
                    'title={dict.app.pages.salesSalesReturns.post}',
                    'title={dict.app.pages.salesSalesReturns.cancel}',
                ],
                'requiredLinks' => [],
            ],
            'Sales/CustomerCreditNotes.tsx' => [
                'permissionChecks' => [
                    "const canManageCustomerCreditNotes = can('sales.credit_notes');",
                    "const canPostCustomerCreditNotes = canManageCustomerCreditNotes && can('view_financials');",
                ],
                'helpers' => [
                    'const canSettleCustomerCreditNote',
                    'const isCustomerCreditNoteActionable',
                    'const hasAvailableCustomerCreditNoteAction',
                    'const getCustomerCreditNoteActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('sales.credit_notes') ? (",
                    "can('sales.credit_notes') && can('view_financials') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.salesCustomerCreditNotes.edit}',
                    'title={dict.app.pages.salesCustomerCreditNotes.submit}',
                    'title={dict.app.pages.salesCustomerCreditNotes.approve}',
                    'title={dict.app.pages.salesCustomerCreditNotes.postToArGl}',
                    'title={dict.app.pages.salesCustomerCreditNotes.settle}',
                    'title={dict.app.pages.salesCustomerCreditNotes.cancel}',
                ],
                'requiredLinks' => [
                    '/sales/receivable-settlements?customer_id=${note.customer_id}&source_entry_id=${note.receivable_entry_id}',
                ],
            ],
            'Purchasing/PurchaseReturns.tsx' => [
                'permissionChecks' => [
                    "const canManagePurchaseReturns = can('purchasing.returns');",
                    "const canPostPurchaseReturns = canManagePurchaseReturns && can('view_financials');",
                ],
                'helpers' => [
                    'const isPurchaseReturnActionable',
                    'const hasAvailablePurchaseReturnAction',
                    'const getPurchaseReturnActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('purchasing.returns') ? (",
                    "can('purchasing.returns') && can('view_financials') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.purchasingPurchaseReturns.edit}',
                    'title={dict.app.pages.purchasingPurchaseReturns.submit}',
                    'title={dict.app.pages.purchasingPurchaseReturns.approve}',
                    'title={dict.app.pages.purchasingPurchaseReturns.post}',
                    'title={dict.app.pages.purchasingPurchaseReturns.cancel}',
                ],
                'requiredLinks' => [],
            ],
            'Purchasing/SupplierAdjustmentNotes.tsx' => [
                'permissionChecks' => [
                    "const canManageSupplierAdjustmentNotes = can('purchasing.adjustment_notes');",
                    "const canPostSupplierAdjustmentNotes = canManageSupplierAdjustmentNotes && can('view_financials');",
                ],
                'helpers' => [
                    'const canSettleSupplierAdjustmentNote',
                    'const isSupplierAdjustmentNoteActionable',
                    'const hasAvailableSupplierAdjustmentNoteAction',
                    'const getSupplierAdjustmentNoteActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('purchasing.adjustment_notes') ? (",
                    "can('purchasing.adjustment_notes') && can('view_financials') ? (",
                ],
                'actionTitles' => [
                    'title={dict.app.pages.purchasingSupplierAdjustmentNotes.edit}',
                    'title={dict.app.pages.purchasingSupplierAdjustmentNotes.submit}',
                    'title={dict.app.pages.purchasingSupplierAdjustmentNotes.approve}',
                    'title={dict.app.pages.purchasingSupplierAdjustmentNotes.postToApGl}',
                    'title={dict.app.pages.purchasingSupplierAdjustmentNotes.settle}',
                    'title={dict.app.pages.purchasingSupplierAdjustmentNotes.cancel}',
                ],
                'requiredLinks' => [
                    '/purchasing/payable-settlements?supplier_id=${note.supplier_id}&source_entry_id=${note.payable_entry_id}',
                ],
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('className="flex flex-wrap items-center justify-end gap-2"', $source);
            $this->assertStringContainsString('dict.app.actions.restricted', $source);
            $this->assertStringContainsString('dict.app.actions.noActions', $source);
            $this->assertStringContainsString('StatusBadge tone="muted"', $source);
            $this->assertStringContainsString('aria-label=', $source);
            $this->assertStringNotContainsString('text-end space-x-2 rtl:space-x-reverse', $source, "{$relativePath} should use the grouped lifecycle action wrapper.");

            foreach ($case['permissionChecks'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['helpers'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['actionTitles'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['oldInlinePermissionFragments'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} should compute lifecycle permissions before rendering row actions.");
            }

            foreach ($case['requiredLinks'] as $fragment) {
                $this->assertStringContainsString('<Link', $source);
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_inventory_rental_payroll_and_expense_action_cells_are_grouped_and_explain_permission_states(): void
    {
        foreach ([
            'Inventory/StockCounts.tsx' => [
                'permissionChecks' => [
                    "const canCountStock = can('inventory.count');",
                    "const canApproveInventory = can('inventory.approve');",
                    "const canPostInventory = can('inventory.post') && can('view_financials');",
                ],
                'helpers' => [
                    'const isStockCountActionable',
                    'const hasAvailableStockCountAction',
                    'const getStockCountActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('inventory.count') ? <Button",
                    "can('inventory.approve') ? <Button",
                    "can('inventory.post') && can('view_financials') ? <Button",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.post}',
                    'title={pageDict.cancelCount}',
                ],
                'requiresNoActionsState' => true,
            ],
            'Inventory/StockAdjustments.tsx' => [
                'permissionChecks' => [
                    "const canAdjustStock = can('inventory.adjust');",
                    "const canApproveInventory = can('inventory.approve');",
                    "const canPostInventory = can('inventory.post') && can('view_financials');",
                ],
                'helpers' => [
                    'const isStockAdjustmentActionable',
                    'const hasAvailableStockAdjustmentAction',
                    'const getStockAdjustmentActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('inventory.adjust') ? <Button",
                    "can('inventory.approve') ? <Button",
                    "can('inventory.post') && can('view_financials') ? <Button",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.post}',
                    'title={pageDict.cancelAdjustment}',
                ],
                'requiresNoActionsState' => true,
            ],
            'Inventory/StockTransfers.tsx' => [
                'permissionChecks' => [
                    "const canTransferStock = can('inventory.transfer');",
                    "const canApproveInventory = can('inventory.approve');",
                    "const canIssueInventory = can('inventory.post');",
                    "const canReceiveInventory = can('inventory.receive');",
                ],
                'helpers' => [
                    'const isStockTransferActionable',
                    'const hasAvailableStockTransferAction',
                    'const getStockTransferActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('inventory.transfer') && transfer.status",
                    "can('inventory.approve') &&",
                    "can('inventory.post') && transfer.status",
                    "can('inventory.receive') &&",
                ],
                'actionTitles' => [
                    'title={pageDict.editTransfer}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.issue}',
                    'title={pageDict.receive}',
                    'title={pageDict.receiveRemaining}',
                    'title={pageDict.cancelTransfer}',
                ],
                'requiresNoActionsState' => true,
            ],
            'Rentals/Contracts.tsx' => [
                'permissionChecks' => [
                    "const canCreateRentalContracts = can('rentals.create');",
                    "const canEditRentalContracts = can('rentals.edit');",
                    "const canSubmitRentalContracts = can('rentals.submit');",
                    "const canApproveRentalContracts = can('rentals.approve');",
                    "const canActivateRentalContracts = can('rentals.deliver');",
                    "const canCancelRentalContracts = can('rentals.cancel');",
                ],
                'helpers' => [
                    'const isRentalContractActionable',
                    'const hasAvailableRentalContractAction',
                    'const getRentalContractActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('rentals.edit') && contract.status",
                    "can('rentals.submit') && contract.status",
                    "can('rentals.approve') && contract.status",
                    "can('rentals.deliver') && contract.status",
                    "can('rentals.cancel') &&",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.activate}',
                    'title={pageDict.cancelContract}',
                ],
                'requiresNoActionsState' => true,
            ],
            'Rentals/Invoices.tsx' => [
                'permissionChecks' => [
                    "const canCreateRentalInvoices = can('rentals.invoice');",
                    "const canSubmitRentalInvoices = can('rentals.submit');",
                    "const canApproveRentalInvoices = can('rentals.approve');",
                    "const canPostRentalInvoices = can('rentals.post') && can('view_financials');",
                    "const canCancelRentalInvoices = can('rentals.cancel');",
                ],
                'helpers' => [
                    'const isRentalInvoiceActionable',
                    'const hasAvailableRentalInvoiceAction',
                    'const getRentalInvoiceActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('rentals.invoice') ? <Button",
                    "can('rentals.submit') ? <Button",
                    "can('rentals.approve') ? <Button",
                    "can('rentals.post') && can('view_financials') ? <Button",
                    "can('rentals.cancel') ? <Button",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.postToArGl}',
                    'title={pageDict.cancel}',
                ],
                'requiresNoActionsState' => true,
            ],
            'Payroll/Runs.tsx' => [
                'permissionChecks' => [
                    "const canViewPayroll = can('view_payroll');",
                    "const canCreatePayrollRuns = can('payroll.create') && canViewPayroll;",
                    "const canRegeneratePayrollRuns = can('payroll.edit') && canViewPayroll;",
                    "const canSubmitPayrollRuns = can('payroll.submit') && canViewPayroll;",
                    "const canApprovePayrollRuns = can('payroll.approve') && canViewPayroll;",
                    "const canPostPayrollRuns = can('payroll.post') && canViewPayroll && can('view_financials');",
                    'const canCancelPayrollRuns = canRegeneratePayrollRuns;',
                ],
                'helpers' => [
                    'const isPayrollRunLifecycleActionable',
                    'const hasAvailablePayrollRunLifecycleAction',
                    'const getPayrollRunActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('payroll.edit') && can('view_payroll')",
                    "can('payroll.submit') && can('view_payroll')",
                    "can('payroll.approve') && can('view_payroll')",
                    "can('payroll.post') && can('view_payroll') && can('view_financials')",
                ],
                'actionTitles' => [
                    'title={pageDict.details}',
                    'title={pageDict.regenerate}',
                    'title={shared.submit}',
                    'title={shared.approve}',
                    'title={shared.post}',
                    'title={shared.cancel}',
                ],
                'requiresNoActionsState' => false,
            ],
            'Expenses/Index.tsx' => [
                'permissionChecks' => [
                    "const canCreateExpenses = can('expenses.create');",
                    "const canEditExpenses = can('expenses.edit');",
                    "const canSubmitExpenses = can('expenses.submit');",
                    "const canApproveExpenses = can('expenses.approve');",
                    "const canPostExpenses = can('expenses.post') && can('view_financials');",
                ],
                'helpers' => [
                    'const isExpenseActionable',
                    'const hasAvailableExpenseAction',
                    'const getExpenseActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('expenses.edit') ? (",
                    "can('expenses.submit') ? (",
                    "can('expenses.approve') ? (",
                    "can('expenses.post') && can('view_financials') ? (",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.post}',
                    'title={pageDict.cancelExpense}',
                ],
                'requiresNoActionsState' => true,
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('className="flex flex-wrap items-center justify-end gap-2"', $source);
            $this->assertStringContainsString('dict.app.actions.restricted', $source);
            $this->assertStringContainsString('StatusBadge tone="muted"', $source);
            $this->assertStringContainsString('aria-label=', $source);

            if ($case['requiresNoActionsState']) {
                $this->assertStringContainsString('dict.app.actions.noActions', $source);
            }

            foreach ($case['permissionChecks'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['helpers'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['actionTitles'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['oldInlinePermissionFragments'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} should compute lifecycle permissions before rendering row actions.");
            }
        }
    }

    public function test_expense_schedule_and_depreciation_run_action_cells_are_grouped_and_explain_permission_states(): void
    {
        foreach ([
            'Expenses/Prepaids.tsx' => [
                'permissionChecks' => [
                    "const canCreateExpenseSchedules = can('expenses.create');",
                    "const canEditExpenseSchedules = can('expenses.edit');",
                    "const canSubmitExpenseSchedules = can('expenses.submit');",
                    "const canApproveExpenseSchedules = can('expenses.approve');",
                    "const canPostExpenseSchedules = can('expenses.post') && can('view_financials');",
                ],
                'helpers' => [
                    'const isScheduleActionable',
                    'const hasAvailableScheduleAction',
                    'const getScheduleActionState',
                    'const isRecognitionPostable',
                    'const getRecognitionActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('expenses.edit') ? <button",
                    "can('expenses.submit') ? <button",
                    "can('expenses.approve') ? <button",
                    "can('expenses.post') && can('view_financials') ? <button",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.post}',
                    'title={pageDict.cancelSchedule}',
                ],
                'requiresNoActionsState' => true,
            ],
            'Expenses/Accruals.tsx' => [
                'permissionChecks' => [
                    "const canCreateExpenseSchedules = can('expenses.create');",
                    "const canEditExpenseSchedules = can('expenses.edit');",
                    "const canSubmitExpenseSchedules = can('expenses.submit');",
                    "const canApproveExpenseSchedules = can('expenses.approve');",
                    "const canPostExpenseSchedules = can('expenses.post') && can('view_financials');",
                ],
                'helpers' => [
                    'const isScheduleActionable',
                    'const hasAvailableScheduleAction',
                    'const getScheduleActionState',
                    'const isAccrualEntryPostable',
                    'const getAccrualEntryActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "can('expenses.edit') ? <button",
                    "can('expenses.submit') ? <button",
                    "can('expenses.approve') ? <button",
                    "can('expenses.post') && can('view_financials') ? <button",
                ],
                'actionTitles' => [
                    'title={pageDict.edit}',
                    'title={pageDict.submit}',
                    'title={pageDict.approve}',
                    'title={pageDict.post}',
                    'title={pageDict.cancelSchedule}',
                ],
                'requiresNoActionsState' => true,
            ],
            'FixedAssets/DepreciationRuns/Index.tsx' => [
                'permissionChecks' => [
                    'const canPostDepreciationRuns = can.post;',
                    'const canReverseDepreciationRuns = can.reverse;',
                ],
                'helpers' => [
                    'const getDepreciationRunActionState',
                    'SensitiveActionModal',
                    'confirmCode="REVERSE_FIXED_ASSET_DEPRECIATION_RUN"',
                    'router.post(`/fixed-assets-depreciation-runs/${reversingRun.id}/reverse`, payload, {',
                ],
                'oldInlinePermissionFragments' => [
                    'can.post &&',
                    'can.reverse &&',
                    'run.status === \'posted\' && can.reverse',
                ],
                'actionTitles' => [
                    'title={appDict.back}',
                    'title={appDict.newDepreciationRun}',
                    'title={appDict.viewDetail}',
                    'title={appDict.reverseDepreciationRun}',
                ],
                'requiresNoActionsState' => false,
            ],
            'FixedAssets/DepreciationRuns/Show.tsx' => [
                'permissionChecks' => [
                    'const canReverseDepreciationRuns = can.reverse;',
                ],
                'helpers' => [
                    'SensitiveActionModal',
                    'confirmCode="REVERSE_FIXED_ASSET_DEPRECIATION_RUN"',
                    'router.post(`/fixed-assets-depreciation-runs/${run.id}/reverse`, payload, {',
                ],
                'oldInlinePermissionFragments' => [
                    'can.reverse &&',
                ],
                'actionTitles' => [
                    'title={appDict.back}',
                    'title={appDict.reverseDepreciationRun}',
                ],
                'requiresNoActionsState' => false,
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('flex flex-wrap items-center', $source);
            $this->assertStringContainsString('dict.app.actions.restricted', $source);
            $this->assertStringContainsString('StatusBadge tone="muted"', $source);
            $this->assertStringContainsString('aria-label=', $source);
            $this->assertStringNotContainsString('text-end space-x-2 rtl:space-x-reverse', $source, "{$relativePath} should use grouped action controls.");
            $this->assertStringNotContainsString('space-x-2', $source, "{$relativePath} should not rely on directional spacing utilities for action groups.");
            $this->assertStringNotContainsString('rtl:space-x-reverse', $source, "{$relativePath} should use gap-based RTL-safe action spacing.");

            if ($case['requiresNoActionsState']) {
                $this->assertStringContainsString('dict.app.actions.noActions', $source);
            }

            foreach ($case['permissionChecks'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['helpers'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['actionTitles'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['oldInlinePermissionFragments'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} should compute permissions before rendering row actions.");
            }
        }
    }

    public function test_cheque_and_bank_reconciliation_action_cells_are_grouped_and_explain_permission_states(): void
    {
        foreach ([
            'IncomingCheques/Index.tsx' => [
                'permissionChecks' => [
                    "const canCreateCheques = can('cheques.create');",
                    "const canReceiveIncomingCheques = can('cheques.receive');",
                    "const canDepositIncomingCheques = can('cheques.deposit');",
                    "const canClearIncomingCheques = can('cheques.clear');",
                    "const canBounceIncomingCheques = can('cheques.bounce');",
                    "const canReturnIncomingCheques = can('cheques.return');",
                ],
                'helpers' => [
                    'const isIncomingChequeActionable',
                    'const hasAvailableIncomingChequeAction',
                    'const getIncomingChequeActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "row.status === 'draft' && can('cheques.receive')",
                    "can('cheques.deposit') ?",
                    "can('cheques.return') ?",
                    "can('cheques.clear') ?",
                    "can('cheques.bounce') ?",
                ],
                'actionTitles' => [
                    'title={pageDict.receive}',
                    'title={pageDict.deposit}',
                    'title={pageDict.return}',
                    'title={pageDict.clear}',
                    'title={pageDict.bounce}',
                ],
                'requiresPermissionState' => true,
                'requiresNoActionsState' => true,
            ],
            'OutgoingCheques/Index.tsx' => [
                'permissionChecks' => [
                    "const canCreateCheques = can('cheques.create');",
                    "const canIssueOutgoingCheques = can('cheques.issue');",
                    "const canClearOutgoingCheques = can('cheques.clear');",
                    "const canReturnOutgoingCheques = can('cheques.return');",
                    "const canCancelOutgoingCheques = can('cheques.cancel');",
                ],
                'helpers' => [
                    'const isOutgoingChequeActionable',
                    'const hasAvailableOutgoingChequeAction',
                    'const getOutgoingChequeActionState',
                ],
                'oldInlinePermissionFragments' => [
                    "row.status === 'draft' && can('cheques.issue')",
                    "can('cheques.clear') ?",
                    "can('cheques.return') ?",
                    "can('cheques.cancel') ?",
                ],
                'actionTitles' => [
                    'title={pageDict.issue}',
                    'title={pageDict.clear}',
                    'title={pageDict.return}',
                    'title={pageDict.cancel}',
                ],
                'requiresPermissionState' => true,
                'requiresNoActionsState' => true,
            ],
            'BankReconciliations/Index.tsx' => [
                'permissionChecks' => [
                    "const canReconcileBanks = can('banks.reconcile');",
                    'canReconcileBanks ? (',
                ],
                'helpers' => [
                    'onClick={() => router.get(`/bank-reconciliations/${row.id}`)}',
                ],
                'oldInlinePermissionFragments' => [
                    "can('banks.reconcile') ? (",
                    '<a',
                    'href={`/bank-reconciliations/${row.id}`}',
                ],
                'actionTitles' => [
                    'title={dict.app.pages.bankReconciliations.newBankReconciliation}',
                    'aria-label={dict.app.pages.bankReconciliations.newBankReconciliation}',
                    'title={row.status === \'draft\' ? dict.app.pages.bankReconciliations.openWorkspace : dict.app.pages.bankReconciliations.viewStatement}',
                ],
                'requiresPermissionState' => false,
                'requiresNoActionsState' => false,
            ],
            'BankReconciliations/Show.tsx' => [
                'permissionChecks' => [
                    "const canReconcileBanks = can('banks.reconcile');",
                    "const canEditReconciliation = reconciliation.status === 'draft' && canReconcileBanks;",
                ],
                'helpers' => [
                    'const headerActionState',
                    'const getStatementLineActionState',
                    'preserveScroll: true',
                ],
                'oldInlinePermissionFragments' => [
                    "reconciliation.status === 'draft' && can('banks.reconcile')",
                    '<span className="text-xs text-[var(--text-muted)] font-mono">{accDict.notAvailable}</span>',
                    'className="text-xs font-bold text-amber-600 hover:underline cursor-pointer"',
                    'className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"',
                    'className="text-xs font-bold text-red-600 hover:underline cursor-pointer"',
                ],
                'actionTitles' => [
                    'title={dict.app.pages.bankReconciliationsShow.addStatementLine}',
                    'title={finalizeTitle}',
                    'title={dict.app.pages.bankReconciliationsShow.unmatch}',
                    'title={dict.app.pages.bankReconciliationsShow.matchGl}',
                    'title={dict.app.pages.bankReconciliationsShow.delete}',
                    'title={dict.app.pages.bankReconciliationsShow.match}',
                ],
                'requiresPermissionState' => true,
                'requiresNoActionsState' => true,
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('className="flex flex-wrap items-center justify-end gap-2"', $source);
            $this->assertStringContainsString('aria-label=', $source);
            $this->assertStringNotContainsString('flex flex-wrap gap-1', $source, "{$relativePath} should use the grouped lifecycle action wrapper.");
            $this->assertStringNotContainsString('text-end space-x-2 rtl:space-x-reverse', $source, "{$relativePath} should use gap-based action spacing.");
            $this->assertStringNotContainsString('rtl:space-x-reverse', $source, "{$relativePath} should not rely on directional spacing utilities for action groups.");

            if ($case['requiresPermissionState']) {
                $this->assertStringContainsString('dict.app.actions.restricted', $source);
                $this->assertStringContainsString('StatusBadge tone="muted"', $source);
            }

            if ($case['requiresNoActionsState']) {
                $this->assertStringContainsString('dict.app.actions.noActions', $source);
            }

            foreach ($case['permissionChecks'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['helpers'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['actionTitles'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($case['oldInlinePermissionFragments'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} should avoid inline permission checks or plain text action links.");
            }
        }
    }

    public function test_cheque_and_bank_reconciliation_modal_actions_have_accessible_names(): void
    {
        foreach ([
            'IncomingCheques/Index.tsx' => [
                'title={dict.app.pages.incomingCheques.addIncomingCheque}',
                'aria-label={dict.app.pages.incomingCheques.addIncomingCheque}',
                'title={dict.app.pages.incomingCheques.cancel}',
                'aria-label={dict.app.pages.incomingCheques.cancel}',
                'title={dict.app.pages.incomingCheques.saveCheque}',
                'aria-label={dict.app.pages.incomingCheques.saveCheque}',
                'title={dict.app.pages.incomingCheques.cancel_2}',
                'aria-label={dict.app.pages.incomingCheques.cancel_2}',
                'title={dict.app.pages.incomingCheques.confirmAction}',
                'aria-label={dict.app.pages.incomingCheques.confirmAction}',
            ],
            'OutgoingCheques/Index.tsx' => [
                'title={dict.app.pages.outgoingCheques.addOutgoingCheque}',
                'aria-label={dict.app.pages.outgoingCheques.addOutgoingCheque}',
                'title={dict.app.pages.outgoingCheques.cancel_2}',
                'aria-label={dict.app.pages.outgoingCheques.cancel_2}',
                'title={dict.app.pages.outgoingCheques.saveCheque}',
                'aria-label={dict.app.pages.outgoingCheques.saveCheque}',
                'title={dict.app.pages.outgoingCheques.cancel_3}',
                'aria-label={dict.app.pages.outgoingCheques.cancel_3}',
                'title={dict.app.pages.outgoingCheques.confirmAction}',
                'aria-label={dict.app.pages.outgoingCheques.confirmAction}',
            ],
            'BankReconciliations/Index.tsx' => [
                'title={dict.app.pages.bankReconciliations.newBankReconciliation}',
                'aria-label={dict.app.pages.bankReconciliations.newBankReconciliation}',
                'title={dict.app.pages.bankReconciliations.cancel}',
                'aria-label={dict.app.pages.bankReconciliations.cancel}',
                'title={dict.app.pages.bankReconciliations.createOpenWorkspace}',
                'aria-label={dict.app.pages.bankReconciliations.createOpenWorkspace}',
            ],
            'BankReconciliations/Show.tsx' => [
                'title={dict.app.pages.bankReconciliationsShow.addStatementLine}',
                'aria-label={dict.app.pages.bankReconciliationsShow.addStatementLine}',
                'title={finalizeTitle}',
                'aria-label={finalizeTitle}',
                'title={dict.app.pages.bankReconciliationsShow.cancel}',
                'aria-label={dict.app.pages.bankReconciliationsShow.cancel}',
                'title={dict.app.pages.bankReconciliationsShow.addLine}',
                'aria-label={dict.app.pages.bankReconciliationsShow.addLine}',
                'title={dict.app.pages.bankReconciliationsShow.match}',
                'aria-label={dict.app.pages.bankReconciliationsShow.match}',
                'title={dict.app.pages.bankReconciliationsShow.close}',
                'aria-label={dict.app.pages.bankReconciliationsShow.close}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} modal and primary actions must expose stable accessible names.");
            }
        }
    }

    public function test_ar_ap_receipt_payment_and_opening_balance_actions_have_accessible_names(): void
    {
        foreach ([
            'CustomerOpeningBalances/Index.tsx' => [
                'const canCreateOpeningBalance = can(\'customers.opening_balances\');',
                'title={dict.app.pages.customerOpeningBalances.newOpeningBalance}',
                'aria-label={dict.app.pages.customerOpeningBalances.newOpeningBalance}',
                'title={dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance}',
                'aria-label={dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance}',
                'router.post(`/customer-opening-balances/${postingBalanceId}/post`, payload, {',
                'confirmCode="POST_CUSTOMER_OPENING_BALANCE"',
                'title={dict.app.pages.customerOpeningBalances.cancel}',
                'aria-label={dict.app.pages.customerOpeningBalances.cancel}',
                'title={dict.app.pages.customerOpeningBalances.saveDraft}',
                'aria-label={dict.app.pages.customerOpeningBalances.saveDraft}',
            ],
            'SupplierOpeningBalances/Index.tsx' => [
                'const canCreateOpeningBalance = can(\'suppliers.opening_balances\');',
                'title={dict.app.pages.supplierOpeningBalances.newOpeningBalance}',
                'aria-label={dict.app.pages.supplierOpeningBalances.newOpeningBalance}',
                'title={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}',
                'aria-label={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}',
                'router.post(`/supplier-opening-balances/${postingBalanceId}/post`, payload, {',
                'confirmCode="POST_SUPPLIER_OPENING_BALANCE"',
                'title={dict.app.pages.supplierOpeningBalances.cancel}',
                'aria-label={dict.app.pages.supplierOpeningBalances.cancel}',
                'title={dict.app.pages.supplierOpeningBalances.saveDraft}',
                'aria-label={dict.app.pages.supplierOpeningBalances.saveDraft}',
            ],
            'CustomerReceipts/Index.tsx' => [
                'const canCreateReceipt = can(\'customers.receipts\');',
                'title={dict.app.pages.customerReceipts.newCustomerReceipt}',
                'aria-label={dict.app.pages.customerReceipts.newCustomerReceipt}',
                'title={dict.app.pages.customerReceipts.confirmPostReceipt}',
                'aria-label={dict.app.pages.customerReceipts.confirmPostReceipt}',
                'router.post(`/customer-receipts/${postingReceiptId}/post`, payload, {',
                'confirmCode="POST_CUSTOMER_RECEIPT"',
                'title={dict.app.pages.customerReceipts.allocate}',
                'aria-label={dict.app.pages.customerReceipts.allocate}',
                'title={dict.app.pages.customerReceipts.cancel}',
                'aria-label={dict.app.pages.customerReceipts.cancel}',
                'title={dict.app.pages.customerReceipts.saveDraft}',
                'aria-label={dict.app.pages.customerReceipts.saveDraft}',
            ],
            'SupplierPayments/Index.tsx' => [
                'const canCreatePayment = can(\'suppliers.payments\');',
                'title={dict.app.pages.supplierPayments.newSupplierPayment}',
                'aria-label={dict.app.pages.supplierPayments.newSupplierPayment}',
                'title={dict.app.pages.supplierPayments.confirmPostPayment}',
                'aria-label={dict.app.pages.supplierPayments.confirmPostPayment}',
                'router.post(`/supplier-payments/${postingPaymentId}/post`, payload, {',
                'confirmCode="POST_SUPPLIER_PAYMENT"',
                'title={dict.app.pages.supplierPayments.allocate}',
                'aria-label={dict.app.pages.supplierPayments.allocate}',
                'title={dict.app.pages.supplierPayments.cancel}',
                'aria-label={dict.app.pages.supplierPayments.cancel}',
                'title={dict.app.pages.supplierPayments.saveDraft}',
                'aria-label={dict.app.pages.supplierPayments.saveDraft}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('preserveScroll: true', $source, "{$relativePath} state-changing actions should preserve table context.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} AR/AP actions must expose stable accessible names.");
            }
        }
    }

    public function test_treasury_transfer_actions_are_grouped_accessible_and_scroll_safe(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/TreasuryTransfers/Index.tsx'));

        foreach ([
            "const canCreateTreasuryTransfers = can('cash.create') || can('banks.create');",
            "const canEditTreasuryTransfers = can('cash.edit') || can('banks.edit');",
            "const canPostTreasuryTransfers = (can('cash.post') || can('banks.post')) && can('view_financials');",
            'const isTreasuryTransferActionable',
            'const hasAvailableTreasuryTransferAction',
            'const getTreasuryTransferActionState',
            'dict.app.actions.restricted',
            'dict.app.actions.noActions',
            'className="flex flex-wrap items-center justify-end gap-2"',
            'title={pageDict.newTransfer}',
            'aria-label={pageDict.newTransfer}',
            'title={pageDict.editTransfer}',
            'aria-label={pageDict.editTransfer}',
            'title={pageDict.confirmPost}',
            'aria-label={pageDict.confirmPost}',
            'title={pageDict.confirmCancel}',
            'aria-label={pageDict.confirmCancel}',
            'title={pageDict.cancelTransfer}',
            'aria-label={pageDict.cancelTransfer}',
            'title={pageDict.saveTransfer}',
            'aria-label={pageDict.saveTransfer}',
            'router.post(`/treasury-transfers/${postingTransferId}/post`, payload, {',
            'confirmCode="POST_TREASURY_TRANSFER"',
            'router.post(`/treasury-transfers/${id}/cancel`, {}, { preserveScroll: true })',
            'preserveScroll: true',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Treasury transfer actions must stay accessible, permission-aware, and scroll-safe.');
        }

        foreach ([
            '<td className={`${tableClasses.td} space-x-2`}>',
            "row.status === 'draft' && (can('cash.edit') || can('banks.edit'))",
            "row.status === 'draft' && (can('cash.post') || can('banks.post')) && can('view_financials')",
            'router.post(`/treasury-transfers/${id}/post`);',
            'router.post(`/treasury-transfers/${id}/cancel`);',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Treasury transfer page should avoid old inline/scroll-reset action patterns.');
        }
    }

    public function test_ar_ap_allocation_actions_are_accessible_restricted_and_scroll_safe(): void
    {
        foreach ([
            'ReceivableAllocations/Index.tsx' => [
                'permission' => "const canManageReceivableAllocations = can('customers.allocations');",
                'store' => "post('/receivable-allocations', {",
                'reverse' => 'router.post(`/receivable-allocations/${reversingId}/reverse`, payload, {',
                'modal' => 'confirmCode="REVERSE_RECEIVABLE_ALLOCATION"',
                'executeTitle' => 'title={dict.app.pages.receivableAllocations.executeAllocation}',
                'executeAria' => 'aria-label={dict.app.pages.receivableAllocations.executeAllocation}',
                'reverseTitle' => 'title={dict.app.pages.receivableAllocations.reverse}',
                'reverseAria' => 'aria-label={dict.app.pages.receivableAllocations.reverse}',
                'oldInlinePermission' => "can('customers.allocations') ? (",
                'oldReversePost' => 'post(`/receivable-allocations/${id}/reverse`);',
            ],
            'PayableAllocations/Index.tsx' => [
                'permission' => "const canManagePayableAllocations = can('suppliers.allocations');",
                'store' => "post('/payable-allocations', {",
                'reverse' => 'router.post(`/payable-allocations/${reversingId}/reverse`, payload, {',
                'modal' => 'confirmCode="REVERSE_PAYABLE_ALLOCATION"',
                'executeTitle' => 'title={dict.app.pages.payableAllocations.executeAllocation}',
                'executeAria' => 'aria-label={dict.app.pages.payableAllocations.executeAllocation}',
                'reverseTitle' => 'title={dict.app.pages.payableAllocations.reverse}',
                'reverseAria' => 'aria-label={dict.app.pages.payableAllocations.reverse}',
                'oldInlinePermission' => "can('suppliers.allocations') ? (",
                'oldReversePost' => 'post(`/payable-allocations/${id}/reverse`);',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                'StatusBadge',
                $case['permission'],
                $case['store'],
                $case['reverse'],
                $case['modal'],
                'preserveScroll: true',
                'SensitiveActionModal',
                'className="flex flex-wrap items-center justify-end gap-2"',
                'dict.app.actions.restricted',
                $case['executeTitle'],
                $case['executeAria'],
                $case['reverseTitle'],
                $case['reverseAria'],
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} allocation actions must be accessible, restricted, and scroll-safe.");
            }

            foreach ([$case['oldInlinePermission'], $case['oldReversePost']] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} should avoid old hidden-action or scroll-reset allocation patterns.");
            }
        }
    }

    public function test_ar_ap_settlement_actions_have_accessible_names(): void
    {
        foreach ([
            'Sales/ReceivableSettlements.tsx' => [
                'backTitle' => 'title={pageDict.backToCreditNotes}',
                'backAria' => 'aria-label={pageDict.backToCreditNotes}',
                'confirmCode' => 'confirmCode="REVERSE_RECEIVABLE_SETTLEMENT"',
            ],
            'Purchasing/PayableSettlements.tsx' => [
                'backTitle' => 'title={pageDict.backToAdjustmentNotes}',
                'backAria' => 'aria-label={pageDict.backToAdjustmentNotes}',
                'confirmCode' => 'confirmCode="REVERSE_PAYABLE_SETTLEMENT"',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                $case['backTitle'],
                $case['backAria'],
                'title={pageDict.confirmSettlement}',
                'aria-label={pageDict.confirmSettlement}',
                'title={pageDict.reverse}',
                'aria-label={pageDict.reverse}',
                'SensitiveActionModal',
                $case['confirmCode'],
                'reasonRequired={true}',
                'preserveScroll: true',
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} settlement actions must expose stable accessible names.");
            }
        }
    }

    public function test_customer_supplier_cash_bank_master_actions_have_accessible_names(): void
    {
        foreach ([
            'Customers/Index.tsx' => [
                'createPermission' => "can('customers.create')",
                'editPermission' => "can('customers.edit')",
                'storeRoute' => "post('/customers', {",
                'updateRoute' => 'patch(`/customers/${editingCustomer.id}`, {',
                'createTitle' => 'title={pageDict.createCustomer}',
                'createAria' => 'aria-label={pageDict.createCustomer}',
                'editTitle' => 'title={pageDict.edit}',
                'editAria' => 'aria-label={pageDict.edit}',
                'cancelTitle' => 'title={pageDict.cancel}',
                'cancelAria' => 'aria-label={pageDict.cancel}',
                'saveTitle' => 'title={pageDict.saveCustomer}',
                'saveAria' => 'aria-label={pageDict.saveCustomer}',
            ],
            'Suppliers/Index.tsx' => [
                'createPermission' => "can('suppliers.create')",
                'editPermission' => "can('suppliers.edit')",
                'storeRoute' => "post('/suppliers', {",
                'updateRoute' => 'patch(`/suppliers/${editingSupplier.id}`, {',
                'createTitle' => 'title={pageDict.createSupplier}',
                'createAria' => 'aria-label={pageDict.createSupplier}',
                'editTitle' => 'title={pageDict.edit}',
                'editAria' => 'aria-label={pageDict.edit}',
                'cancelTitle' => 'title={pageDict.cancel}',
                'cancelAria' => 'aria-label={pageDict.cancel}',
                'saveTitle' => 'title={pageDict.saveSupplier}',
                'saveAria' => 'aria-label={pageDict.saveSupplier}',
            ],
            'CashAccounts/Index.tsx' => [
                'createPermission' => "can('cash.create')",
                'editPermission' => "can('cash.edit')",
                'storeRoute' => "post('/cash-accounts', {",
                'updateRoute' => 'patch(`/cash-accounts/${editingAccount.id}`, {',
                'createTitle' => 'title={pageDict.createCashAccount}',
                'createAria' => 'aria-label={pageDict.createCashAccount}',
                'editTitle' => 'title={pageDict.edit}',
                'editAria' => 'aria-label={pageDict.edit}',
                'cancelTitle' => 'title={pageDict.cancel}',
                'cancelAria' => 'aria-label={pageDict.cancel}',
                'saveTitle' => 'title={pageDict.saveAccount}',
                'saveAria' => 'aria-label={pageDict.saveAccount}',
            ],
            'BankAccounts/Index.tsx' => [
                'createPermission' => "can('banks.create')",
                'editPermission' => "can('banks.edit')",
                'storeRoute' => "post('/bank-accounts', {",
                'updateRoute' => 'patch(`/bank-accounts/${editingAccount.id}`, {',
                'createTitle' => 'title={pageDict.createBankAccount}',
                'createAria' => 'aria-label={pageDict.createBankAccount}',
                'editTitle' => 'title={pageDict.edit}',
                'editAria' => 'aria-label={pageDict.edit}',
                'cancelTitle' => 'title={pageDict.cancel}',
                'cancelAria' => 'aria-label={pageDict.cancel}',
                'saveTitle' => 'title={pageDict.saveAccount}',
                'saveAria' => 'aria-label={pageDict.saveAccount}',
            ],
        ] as $relativePath => $case) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                $case['createPermission'],
                $case['editPermission'],
                $case['storeRoute'],
                $case['updateRoute'],
                'preserveScroll: true',
                'className="flex flex-wrap items-center justify-end gap-2"',
                'dict.app.actions.restricted',
                $case['createTitle'],
                $case['createAria'],
                $case['editTitle'],
                $case['editAria'],
                $case['cancelTitle'],
                $case['cancelAria'],
                $case['saveTitle'],
                $case['saveAria'],
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} master-data actions must be permission-aware, accessible, and scroll-safe.");
            }

            $this->assertStringContainsString(') : (', $source, "{$relativePath} row action cells should show a restricted state instead of appearing empty.");
        }
    }

    public function test_customer_invoice_and_supplier_bill_select_controls_use_searchable_controls(): void
    {
        foreach ([
            'Sales/CustomerInvoices.tsx' => [
                'const statusFilterOptions',
                'const customerOptions',
                'const salesOrderOptions',
                'const deliveryNoteOptions',
                'const productOptions',
                'options={statusFilterOptions}',
                'options={customerOptions}',
                'options={salesOrderOptions}',
                'options={deliveryNoteOptions}',
                'options={productOptions}',
                'onChange={(value) => handleSalesOrderSelect(value || \'\')}',
                'onChange={(value) => handleDeliveryNoteSelect(value || \'\')}',
                'onChange={(value) => updateLineProduct(idx, value || \'\')}',
            ],
            'Purchasing/SupplierBills.tsx' => [
                'const statusFilterOptions',
                'const supplierOptions',
                'const purchaseOrderOptions',
                'const goodsReceiptOptions',
                'const productOptions',
                'options={statusFilterOptions}',
                'options={supplierOptions}',
                'options={purchaseOrderOptions}',
                'options={goodsReceiptOptions}',
                'options={productOptions}',
                'onChange={(value) => handlePurchaseOrderSelect(value || \'\')}',
                'onChange={(value) => handleGoodsReceiptSelect(value || \'\')}',
                'onChange={(value) => updateLineItem(idx, \'product_id\', value || \'\')}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should not use native select controls.");
            $this->assertStringNotContainsString('<option', $source, "{$relativePath} should not render native option controls.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_sales_and_purchasing_line_editors_use_typed_update_helpers(): void
    {
        foreach ([
            'Sales/CustomerCreditNotes.tsx',
            'Sales/SalesReturns.tsx',
            'Purchasing/SupplierAdjustmentNotes.tsx',
            'Purchasing/PurchaseReturns.tsx',
            'Purchasing/SupplierBills.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                'value: any',
                '(note as any)',
                'e.target.value as any',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep document line editing typed.");
            }

            $this->assertStringContainsString('<K extends keyof', $source, "{$relativePath} must use a typed line update helper.");
        }

        $salesReturns = (string) file_get_contents(resource_path('js/Pages/Sales/SalesReturns.tsx'));

        $this->assertStringContainsString('function toDisposition', $salesReturns);
        $this->assertStringContainsString("onChange={(value) => updateLineItem(idx, 'disposition', toDisposition(value || ''))}", $salesReturns);
    }

    public function test_returns_adjustments_and_landed_cost_select_controls_use_searchable_controls(): void
    {
        foreach ([
            'Sales/SalesReturns.tsx' => [
                'const warehouseOptions',
                'const warehouseFilterOptions',
                'const statusFilterOptions',
                'const customerOptions',
                'const deliveryNoteOptions',
                'const postedInvoiceOptions',
                'const deliveryNoteLineOptions',
                'const dispositionOptions',
                'options={warehouseFilterOptions}',
                'options={statusFilterOptions}',
                'options={customerOptions}',
                'options={warehouseOptions}',
                'options={deliveryNoteOptions}',
                'options={postedInvoiceOptions}',
                'options={deliveryNoteLineOptions}',
                'options={dispositionOptions}',
                'onChange={(value) => handleCustomerSelect(value || \'\')}',
                'onChange={(value) => updateLineItem(idx, \'delivery_note_line_id\', value || \'\')}',
                'onChange={(value) => updateLineItem(idx, \'disposition\', toDisposition(value || \'\'))}',
            ],
            'Sales/CustomerCreditNotes.tsx' => [
                'const filteredInvoiceOptions',
                'const filteredSalesReturnOptions',
                'const statusFilterOptions',
                'const customerOptions',
                'const taxModeOptions',
                'const invoiceLineOptions',
                'const handleInvoiceSelect',
                'options={filteredInvoiceOptions}',
                'options={filteredSalesReturnOptions}',
                'options={statusFilterOptions}',
                'options={customerOptions}',
                'options={taxModeOptions}',
                'options={invoiceLineOptions}',
                'onChange={(value) => handleInvoiceSelect(value || \'\')}',
            ],
            'Purchasing/PurchaseReturns.tsx' => [
                'const warehouseOptions',
                'const warehouseFilterOptions',
                'const statusFilterOptions',
                'const supplierOptions',
                'const goodsReceiptOptions',
                'options={warehouseFilterOptions}',
                'options={statusFilterOptions}',
                'options={supplierOptions}',
                'options={goodsReceiptOptions}',
                'options={warehouseOptions}',
                'onChange={(value) => handleSupplierSelect(value || \'\')}',
                'onChange={(value) => handleGoodsReceiptSelect(value || \'\')}',
            ],
            'Purchasing/SupplierAdjustmentNotes.tsx' => [
                'const filteredBillOptions',
                'const statusFilterOptions',
                'const supplierOptions',
                'const directionOptions',
                'const taxModeOptions',
                'const handleSupplierBillSelect',
                'options={filteredBillOptions}',
                'options={statusFilterOptions}',
                'options={supplierOptions}',
                'options={directionOptions}',
                'options={taxModeOptions}',
                'onChange={(value) => handleSupplierBillSelect(value || \'\')}',
            ],
            'Purchasing/LandedCosts.tsx' => [
                'type AllocationMethod',
                'function toAllocationMethod',
                'const goodsReceiptOptions',
                'const supplierOptions',
                'const allocationMethodOptions',
                'const statusFilterOptions',
                'options={goodsReceiptOptions}',
                'options={supplierOptions}',
                'options={allocationMethodOptions}',
                'options={statusFilterOptions}',
                'onChange={(value) => handleReceiptChange(value || \'\')}',
                'onChange={(value) => setData(\'allocation_method\', toAllocationMethod(value))}',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should not use native select controls.");
            $this->assertStringNotContainsString('<option', $source, "{$relativePath} should not render native option controls.");
            $this->assertStringNotContainsString('e.target.value as', $source, "{$relativePath} should not cast native select event values.");

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_inertia_page_pagination_links_are_explicitly_typed(): void
    {
        $pages = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('js/Pages'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($pages as $page) {
            if (! $page->isFile() || $page->getExtension() !== 'tsx') {
                continue;
            }

            $source = (string) file_get_contents($page->getPathname());

            foreach ([
                'links: any[]',
                'links?: any[]',
                'links: unknown[]',
                'PaginatedData<T> = { data: T[]; total: number; links: any[] }',
            ] as $fragment) {
                $this->assertStringNotContainsString(
                    $fragment,
                    $source,
                    "{$page->getPathname()} must use PaginationLink[] for paginated Inertia payloads."
                );
            }
        }
    }

    public function test_remaining_page_any_casts_are_removed_from_landed_costs_and_customer_invoices(): void
    {
        foreach ([
            'Purchasing/LandedCosts.tsx',
            'Sales/CustomerInvoices.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach (['as any', 'value: any'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not use loose any casts.");
            }
        }

        $landedCosts = (string) file_get_contents(resource_path('js/Pages/Purchasing/LandedCosts.tsx'));
        $customerInvoices = (string) file_get_contents(resource_path('js/Pages/Sales/CustomerInvoices.tsx'));

        $this->assertStringContainsString('errors.goods_receipt_id', $landedCosts);
        $this->assertStringContainsString('errors.supplier_id', $landedCosts);
        $this->assertStringContainsString('sales_order_line_id: l.sales_order_line_id', $customerInvoices);
        $this->assertStringContainsString('delivery_note_line_id: l.delivery_note_line_id', $customerInvoices);
    }

    public function test_landed_cost_lifecycle_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Purchasing/LandedCosts.tsx'));

        foreach ([
            'router.put(`/purchasing/landed-costs/${editing.id}`, payload, { preserveScroll: true, onSuccess: closeForm });',
            'router.post(\'/purchasing/landed-costs\', payload, { preserveScroll: true, onSuccess: closeForm });',
            "confirmCode: 'POST_LANDED_COST'",
            'router.post(`/purchasing/landed-costs/${row.id}/${action}`, {}, { preserveScroll: true });',
            'router.post(pendingSensitiveAction.url, payload, {',
            'router.get(\'/purchasing/landed-costs\', { search: searchFilter, status: statusFilter }, { preserveState: true, preserveScroll: true, replace: true });',
            'title={showForm ? t.closeForm : t.create}',
            'aria-label={showForm ? t.closeForm : t.create}',
            'title={t.cancel}',
            'aria-label={t.cancel}',
            'title={processing ? t.processing : editing ? t.saveChanges : t.saveDraft}',
            'aria-label={processing ? t.processing : editing ? t.saveChanges : t.saveDraft}',
            'title={t.filter}',
            'aria-label={t.filter}',
            'title={`${t.edit} ${rowLabel(row)}`}',
            'aria-label={`${t.edit} ${rowLabel(row)}`}',
            'title={`${t.submit} ${rowLabel(row)}`}',
            'aria-label={`${t.submit} ${rowLabel(row)}`}',
            'title={`${t.approve} ${rowLabel(row)}`}',
            'aria-label={`${t.approve} ${rowLabel(row)}`}',
            'title={`${t.post} ${rowLabel(row)}`}',
            'aria-label={`${t.post} ${rowLabel(row)}`}',
            'title={`${t.cancel} ${rowLabel(row)}`}',
            'aria-label={`${t.cancel} ${rowLabel(row)}`}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Landed cost lifecycle actions must remain accessible and scroll-safe.');
        }
    }

    public function test_customer_invoice_modal_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Sales/CustomerInvoices.tsx'));

        foreach ([
            'router.get(\'/sales/invoices\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/sales/invoices\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            "confirmCode: 'POST_CUSTOMER_INVOICE'",
            'router.post(`/sales/invoices/${invId}/${action}`, {}, { preserveScroll: true });',
            'router.post(pendingSensitiveAction.url, payload, {',
            'preserveScroll: true',
            'title={dict.app.pages.salesCustomerInvoices.createCustomerInvoice}',
            'aria-label={dict.app.pages.salesCustomerInvoices.createCustomerInvoice}',
            'title={dict.app.pages.salesCustomerInvoices.manualService}',
            'aria-label={dict.app.pages.salesCustomerInvoices.manualService}',
            'title={dict.app.pages.salesCustomerInvoices.fromSalesOrder}',
            'aria-label={dict.app.pages.salesCustomerInvoices.fromSalesOrder}',
            'title={dict.app.pages.salesCustomerInvoices.fromDeliveryNote}',
            'aria-label={dict.app.pages.salesCustomerInvoices.fromDeliveryNote}',
            'title={dict.app.pages.salesCustomerInvoices.addLine}',
            'aria-label={dict.app.pages.salesCustomerInvoices.addLine}',
            'title={`${dict.app.pages.salesCustomerInvoices.removeLine} ${idx + 1}`}',
            'aria-label={`${dict.app.pages.salesCustomerInvoices.removeLine} ${idx + 1}`}',
            'title={dict.app.pages.salesCustomerInvoices.cancel_2}',
            'aria-label={dict.app.pages.salesCustomerInvoices.cancel_2}',
            'title={processing ? dict.app.pages.salesCustomerInvoices.saving : dict.app.pages.salesCustomerInvoices.saveDraft}',
            'aria-label={processing ? dict.app.pages.salesCustomerInvoices.saving : dict.app.pages.salesCustomerInvoices.saveDraft}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Customer invoice modal actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', 'salesCustomerInvoices', 'removeLine'], $locale);
        }
    }

    public function test_supplier_bill_modal_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Purchasing/SupplierBills.tsx'));

        foreach ([
            'router.put(`/purchasing/bills/${editingBill.id}`, payload, {',
            'router.post(\'/purchasing/bills\', payload, {',
            "confirmCode: 'POST_SUPPLIER_BILL'",
            'router.post(`/purchasing/bills/${billId}/${action}`, {}, { preserveScroll: true });',
            'router.post(pendingSensitiveAction.url, payload, {',
            'router.get(',
            '{ preserveState: true, preserveScroll: true, replace: true }',
            'const supplierBillSubmitLabel = editingBill ? pageDict.saveChanges : pageDict.createBill;',
            'title={dict.app.pages.purchasingSupplierBills.createSupplierBill}',
            'aria-label={dict.app.pages.purchasingSupplierBills.createSupplierBill}',
            'title={dict.app.pages.purchasingSupplierBills.filter}',
            'aria-label={dict.app.pages.purchasingSupplierBills.filter}',
            'title={dict.app.pages.purchasingSupplierBills.addLine}',
            'aria-label={dict.app.pages.purchasingSupplierBills.addLine}',
            'title={`${dict.app.pages.purchasingSupplierBills.removeLine} ${idx + 1}`}',
            'aria-label={`${dict.app.pages.purchasingSupplierBills.removeLine} ${idx + 1}`}',
            'title={dict.app.pages.purchasingSupplierBills.cancel_2}',
            'aria-label={dict.app.pages.purchasingSupplierBills.cancel_2}',
            'title={supplierBillSubmitLabel}',
            'aria-label={supplierBillSubmitLabel}',
            'preserveScroll: true',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Supplier bill modal actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', 'purchasingSupplierBills', 'removeLine'], $locale);
        }
    }

    public function test_sales_return_modal_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Sales/SalesReturns.tsx'));

        foreach ([
            'router.put(`/sales/returns/${editingReturn.id}`, payload, {',
            'router.post(\'/sales/returns\', payload, {',
            "confirmCode: 'POST_SALES_RETURN'",
            'router.post(`/sales/returns/${retId}/${action}`, {}, { preserveScroll: true });',
            'router.post(pendingSensitiveAction.url, payload, {',
            'router.get(\'/sales/returns\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/sales/returns\', { ...filters, warehouse_id: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'router.get(\'/sales/returns\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'const salesReturnLoadLinesLabel = fetchingLines ? pageDict.loading : pageDict.loadLines;',
            'const salesReturnSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'title={dict.app.pages.salesSalesReturns.createSalesReturn}',
            'aria-label={dict.app.pages.salesSalesReturns.createSalesReturn}',
            'title={dict.app.pages.salesSalesReturns.fromDeliveryNote}',
            'aria-label={dict.app.pages.salesSalesReturns.fromDeliveryNote}',
            'title={dict.app.pages.salesSalesReturns.createFromInvoice}',
            'aria-label={dict.app.pages.salesSalesReturns.createFromInvoice}',
            'title={salesReturnLoadLinesLabel}',
            'aria-label={salesReturnLoadLinesLabel}',
            'title={dict.app.pages.salesSalesReturns.cancel_2}',
            'aria-label={dict.app.pages.salesSalesReturns.cancel_2}',
            'title={salesReturnSubmitLabel}',
            'aria-label={salesReturnSubmitLabel}',
            'preserveScroll: true',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Sales return modal actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (['fromDeliveryNote', 'createFromInvoice', 'loadLines', 'saving', 'saveDraft'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', 'salesSalesReturns', $key], $locale);
            }
        }
    }

    public function test_login_actions_have_accessible_names_and_dev_credentials_are_dev_only(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Auth/Login.tsx'));

        foreach ([
            "post('/login', {",
            'preserveScroll: true',
            'const passwordToggleLabel = showPassword ? t.hidePassword : t.showPassword;',
            'const loginSubmitLabel = processing ? t.submitting : t.submitButton;',
            'const devQuickFillEnabled = import.meta.env.DEV;',
            'title={t.switchToEnglish}',
            'aria-label={t.switchToEnglish}',
            'title={t.switchToArabic}',
            'aria-label={t.switchToArabic}',
            "title={t.switchTheme.replace(':theme', mode.label)}",
            "aria-label={t.switchTheme.replace(':theme', mode.label)}",
            'title={passwordToggleLabel}',
            'aria-label={passwordToggleLabel}',
            'title={loginSubmitLabel}',
            'aria-label={loginSubmitLabel}',
            '{devQuickFillEnabled ? (',
            'title={t.fillDevAdminCredentials}',
            'aria-label={t.fillDevAdminCredentials}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Login actions must remain accessible and development credentials must stay dev-only.');
        }

        $this->assertStringNotContainsString('Switch to ${mode.label} theme', $source);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (['switchToEnglish', 'switchToArabic', 'switchTheme', 'showPassword', 'hidePassword', 'fillDevAdminCredentials'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['auth', 'login', $key], $locale);
            }
        }
    }

    public function test_invoice_source_document_shapes_do_not_use_loose_any(): void
    {
        $customerInvoices = (string) file_get_contents(resource_path('js/Pages/Sales/CustomerInvoices.tsx'));
        $supplierBills = (string) file_get_contents(resource_path('js/Pages/Purchasing/SupplierBills.tsx'));

        foreach ([
            'confirmedSalesOrders: any[]',
            'confirmedDeliveryNotes: any[]',
            'getProductName = (prod: any)',
            '(l: any)',
            'links: any[]',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $customerInvoices, 'Customer invoice source-document handling must stay explicitly typed.');
        }

        foreach ([
            'confirmedPurchaseOrders: any[]',
            'confirmedGoodsReceipts: any[]',
            '(l: any)',
            'links: any[]',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $supplierBills, 'Supplier bill source-document handling must stay explicitly typed.');
        }

        $this->assertStringContainsString('type ConfirmedSalesOrder', $customerInvoices);
        $this->assertStringContainsString('type ConfirmedDeliveryNote', $customerInvoices);
        $this->assertStringContainsString('getDeliveryNoteSalesOrder', $customerInvoices);
        $this->assertStringContainsString('type ConfirmedPurchaseOrder', $supplierBills);
        $this->assertStringContainsString('type ConfirmedGoodsReceipt', $supplierBills);
        $this->assertStringContainsString('getGoodsReceiptPurchaseOrder', $supplierBills);
    }

    public function test_ar_ap_cash_bank_pagination_links_are_typed(): void
    {
        foreach ([
            'CustomerOpeningBalances/Index.tsx',
            'CustomerReceipts/Index.tsx',
            'SupplierOpeningBalances/Index.tsx',
            'SupplierPayments/Index.tsx',
            'CashAccounts/Index.tsx',
            'BankAccounts/Index.tsx',
            'IncomingCheques/Index.tsx',
            'OutgoingCheques/Index.tsx',
            'ReceivableAllocations/Index.tsx',
            'PayableAllocations/Index.tsx',
            'BankReconciliations/Index.tsx',
            'Sales/CustomerInvoices.tsx',
            'Purchasing/SupplierBills.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('PaginationLink', $source, "{$relativePath} must import and use the shared pagination link type.");
            $this->assertStringNotContainsString('links: any[]', $source, "{$relativePath} must not use loose pagination links.");
        }
    }

    public function test_sales_purchasing_and_catalog_tables_use_canonical_missing_labels(): void
    {
        foreach ([
            'Sales/SalesOrders.tsx',
            'Sales/DeliveryNotes.tsx',
            'Sales/CustomerInvoices.tsx',
            'Sales/CustomerCreditNotes.tsx',
            'Sales/SalesReturns.tsx',
            'Purchasing/PurchaseOrders.tsx',
            'Purchasing/GoodsReceipts.tsx',
            'Purchasing/SupplierBills.tsx',
            'Purchasing/SupplierAdjustmentNotes.tsx',
            'Purchasing/PurchaseReturns.tsx',
            'Catalog/ProductCategories.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "|| '-'",
                ": '-'",
                "value={item.description || '-'}",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must use canonical unavailable labels instead of silent dashes.");
            }

            $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
            $this->assertStringContainsString('accDict.notAvailable', $source);
        }
    }

    public function test_sales_invoice_revision_pages_use_canonical_missing_labels(): void
    {
        foreach ([
            'Sales/InvoiceRevisions.tsx',
            'Sales/InvoiceRevisionShow.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "|| '-'",
                ": '-'",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must use canonical unavailable labels instead of silent dashes.");
            }

            $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
            $this->assertStringContainsString('accDict.notAvailable', $source);
        }
    }

    public function test_ar_ap_cash_bank_pages_use_canonical_missing_labels_and_explicit_currency(): void
    {
        foreach ([
            'Customers/Index.tsx',
            'Suppliers/Index.tsx',
            'CashAccounts/Index.tsx',
            'BankAccounts/Index.tsx',
            'IncomingCheques/Index.tsx',
            'OutgoingCheques/Index.tsx',
            'CustomerOpeningBalances/Index.tsx',
            'SupplierOpeningBalances/Index.tsx',
            'ReceivableAllocations/Index.tsx',
            'PayableAllocations/Index.tsx',
            'BankReconciliations/Index.tsx',
            'BankReconciliations/Show.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "'EGP'",
                "|| '—'",
                ": '—'",
                "|| '-'",
                ": '-'",
                'متبقي',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not use hidden EGP or silent unavailable-label fallbacks.");
            }

            $this->assertStringContainsString('const accDict = dict.app.accounting;', $source);
            $this->assertStringContainsString('accDict.notAvailable', $source);
        }
    }

    public function test_remaining_operational_pages_use_canonical_missing_labels_and_explicit_currency(): void
    {
        foreach ([
            'TreasuryTransfers/Index.tsx',
            'Inventory/StockTransfers.tsx',
            'Inventory/StockCounts.tsx',
            'Inventory/StockAdjustments.tsx',
            'Accounting/ChartOfAccounts.tsx',
            'Settings/Company.tsx',
            'Settings/Numbering.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                "'EGP'",
                "|| '—'",
                ": '—'",
                "|| '-'",
                ": '-'",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must use explicit currency and canonical unavailable labels.");
            }

            $this->assertStringContainsString('accDict.notAvailable', $source);
        }

        $stockAdjustments = (string) file_get_contents(resource_path('js/Pages/Inventory/StockAdjustments.tsx'));

        $this->assertStringContainsString('formatMoney(adjustment.total_value_delta_minor, adjustment.currency)', $stockAdjustments);
        $this->assertStringNotContainsString('>{adjustment.total_value_delta_minor}</td>', $stockAdjustments);
    }

    public function test_journal_detail_and_fixed_asset_disposal_lines_format_money_without_raw_minor_or_silent_dashes(): void
    {
        $journalDetail = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalDetail.tsx'));
        $disposalShow = (string) file_get_contents(resource_path('js/Pages/FixedAssets/Disposals/Show.tsx'));

        $this->assertStringContainsString('formatMoney(line.debit_minor, journal.currency)', $journalDetail);
        $this->assertStringContainsString('formatMoney(line.credit_minor, journal.currency)', $journalDetail);
        $this->assertStringContainsString('formatMoney(totalDebit, journal.currency)', $journalDetail);
        $this->assertStringContainsString('formatMoney(totalCredit, journal.currency)', $journalDetail);
        $this->assertStringNotContainsString('line.debit_minor > 0 ? line.debit_minor', $journalDetail);
        $this->assertStringNotContainsString('line.credit_minor > 0 ? line.credit_minor', $journalDetail);
        $this->assertStringNotContainsString('{totalDebit}', $journalDetail);
        $this->assertStringNotContainsString('{totalCredit}', $journalDetail);

        foreach ([$journalDetail, $disposalShow] as $source) {
            $this->assertStringNotContainsString(": '-'", $source);
            $this->assertStringNotContainsString("|| '-'", $source);
            $this->assertStringNotContainsString(" ? '-'", $source);
            $this->assertStringContainsString('notAvailable', $source);
        }
    }

    public function test_report_tables_use_dictionary_backed_zero_and_unavailable_markers(): void
    {
        foreach ([
            'Reports/BankBook.tsx',
            'Reports/CashBook.tsx',
            'Reports/CustomerStatement.tsx',
            'Reports/SupplierStatement.tsx',
            'Reports/BankReconciliationDetail.tsx',
            'Reports/CustomerInvoicesReport.tsx',
            'Reports/SupplierBillsReport.tsx',
            'Reports/StockMovementsReport.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                " ? '—'",
                ": '—'",
                '>—<',
                '<span className="text-xs text-slate-400">—</span>',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not hardcode report table zero/unavailable markers.");
            }
        }

        foreach ([
            'Reports/BankBook.tsx',
            'Reports/CashBook.tsx',
            'Reports/CustomerStatement.tsx',
            'Reports/SupplierStatement.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('accDict.zeroAmount', $source, "{$relativePath} must use the accounting zero-amount marker.");
        }

        foreach ([
            'Reports/BankReconciliationDetail.tsx',
            'Reports/CustomerInvoicesReport.tsx',
            'Reports/SupplierBillsReport.tsx',
            'Reports/StockMovementsReport.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('accDict.notAvailable', $source, "{$relativePath} must use the canonical unavailable marker for missing links.");
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', 'zeroAmount'], $locale);
        }
    }

    public function test_bank_reconciliation_detail_uses_dictionary_for_missing_gl_journal_reference(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Reports/BankReconciliationDetail.tsx'));

        $this->assertStringNotContainsString("'GL-Entry'", $source);
        $this->assertStringContainsString('reportsBankReconciliationDetail.missingGlJournalReference', $source);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', 'reportsBankReconciliationDetail', 'missingGlJournalReference'], $locale);
        }
    }

    public function test_bank_reconciliation_finalize_confirmation_uses_canonical_dictionary_key(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/BankReconciliations/Show.tsx'));

        $this->assertStringContainsString('bankReconciliationsShow.confirmFinalizeReconciliation', $source);
        $this->assertStringNotContainsString('bankReconciliationsShow.areYouSureYouWantTo_2', $source);

        $en = json_decode((string) file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode((string) file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(
            'Are you sure you want to finalize this bank reconciliation?',
            $en['app']['pages']['bankReconciliationsShow']['confirmFinalizeReconciliation']
        );
        $this->assertSame(
            'هل تريد اعتماد تسوية البنك وإغلاقها نهائيا؟',
            $ar['app']['pages']['bankReconciliationsShow']['confirmFinalizeReconciliation']
        );
    }

    public function test_catalog_product_modal_select_labels_are_cleanly_localized_in_arabic(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Catalog/Products.tsx'));

        foreach ([
            'pageDict.stock_2',
            'pageDict.service_2',
            'pageDict.nonStock_2',
            'pageDict.active_2',
            'pageDict.inactive_2',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source);
        }

        $ar = json_decode((string) file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'stock_2',
            'service_2',
            'nonStock_2',
            'active_2',
            'inactive_2',
        ] as $key) {
            $label = $ar['app']['pages']['catalogProducts'][$key] ?? '';

            $this->assertNotEmpty($label, "Missing Arabic catalog product label [{$key}].");
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $label, "Arabic catalog product label [{$key}] must not include English parenthetical copy.");
        }
    }

    public function test_catalog_product_filters_and_modal_use_searchable_select_controls(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Catalog/Products.tsx'));

        foreach ([
            'SearchableSelect',
            'const pageDict = dict.app.pages.catalogProducts',
            'const typeOptions',
            'const typeFilterOptions',
            'const statusOptions',
            'const statusFilterOptions',
            'const uomOptions',
            'const categoryOptions',
            'const categoryFilterOptions',
            'SearchableSelect<ProductType>',
            'SearchableSelect<ProductStatus>',
            'options={typeFilterOptions}',
            'options={statusFilterOptions}',
            'options={categoryFilterOptions}',
            'options={uomOptions}',
            'function toProductType',
            'function toProductStatus',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source);
        }

        $this->assertStringNotContainsString('<select', $source);
        $this->assertStringNotContainsString('e.target.value as', $source);
        $this->assertStringNotContainsString('window.location.href', $source);

        $en = json_decode((string) file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode((string) file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertLocalePathIsNotEmpty($en, ['app', 'pages', 'catalogProducts', 'allCategories'], 'EN');
        $this->assertLocalePathIsNotEmpty($ar, ['app', 'pages', 'catalogProducts', 'allCategories'], 'AR');
    }

    public function test_catalog_category_and_uom_form_labels_and_placeholders_are_dictionary_backed(): void
    {
        $productCategories = (string) file_get_contents(resource_path('js/Pages/Catalog/ProductCategories.tsx'));
        $unitsOfMeasure = (string) file_get_contents(resource_path('js/Pages/Catalog/UnitsOfMeasure.tsx'));

        $this->assertStringNotContainsString('placeholder="e.g. RAW, FG, SERV"', $productCategories);
        $this->assertStringContainsString('catalogProductCategories.codePlaceholder', $productCategories);

        foreach ([
            'placeholder="e.g. PCS, KG, M"',
            'placeholder="e.g. pc, kg, m"',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $unitsOfMeasure);
        }

        $this->assertStringContainsString('catalogUnitsOfMeasure.codePlaceholder', $unitsOfMeasure);
        $this->assertStringContainsString('catalogUnitsOfMeasure.symbolPlaceholder', $unitsOfMeasure);

        $ar = json_decode((string) file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            ['catalogProductCategories', 'code_2'],
            ['catalogUnitsOfMeasure', 'code_2'],
            ['catalogUnitsOfMeasure', 'symbol_2'],
        ] as [$section, $key]) {
            $label = $ar['app']['pages'][$section][$key] ?? '';

            $this->assertNotEmpty($label, "Missing Arabic catalog label [{$section}.{$key}].");
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $label, "Arabic catalog label [{$section}.{$key}] must not include English parenthetical copy.");
        }

        foreach ([
            ['catalogProductCategories', 'codePlaceholder'],
            ['catalogUnitsOfMeasure', 'codePlaceholder'],
            ['catalogUnitsOfMeasure', 'symbolPlaceholder'],
        ] as [$section, $key]) {
            $this->assertNotEmpty($ar['app']['pages'][$section][$key] ?? null, "Missing Arabic catalog placeholder [{$section}.{$key}].");
        }
    }

    public function test_catalog_master_data_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $productCategories = (string) file_get_contents(resource_path('js/Pages/Catalog/ProductCategories.tsx'));
        $unitsOfMeasure = (string) file_get_contents(resource_path('js/Pages/Catalog/UnitsOfMeasure.tsx'));
        $products = (string) file_get_contents(resource_path('js/Pages/Catalog/Products.tsx'));

        foreach ([
            'const categorySubmitLabel = processing',
            'put(`/catalog/categories/${editingCategory.id}`, {',
            'post(\'/catalog/categories\', {',
            'destroy(`/catalog/categories/${category.id}`, { preserveScroll: true });',
            'router.get(\'/catalog/categories\', { search: val }, { preserveState: true, preserveScroll: true });',
            'title={dict.app.pages.catalogProductCategories.addCategory}',
            'aria-label={dict.app.pages.catalogProductCategories.addCategory}',
            'title={dict.app.pages.catalogProductCategories.edit}',
            'aria-label={dict.app.pages.catalogProductCategories.edit}',
            'title={dict.app.pages.catalogProductCategories.delete}',
            'aria-label={dict.app.pages.catalogProductCategories.delete}',
            'title={dict.app.pages.catalogProductCategories.cancel}',
            'aria-label={dict.app.pages.catalogProductCategories.cancel}',
            'title={categorySubmitLabel}',
            'aria-label={categorySubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $productCategories, 'Product category actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const uomSubmitLabel = processing',
            'put(`/catalog/uoms/${editingUom.id}`, {',
            'post(\'/catalog/uoms\', {',
            'destroy(`/catalog/uoms/${uom.id}`, { preserveScroll: true });',
            'router.get(\'/catalog/uoms\', { search: val }, { preserveState: true, preserveScroll: true });',
            'title={dict.app.pages.catalogUnitsOfMeasure.addUnitOfMeasure}',
            'aria-label={dict.app.pages.catalogUnitsOfMeasure.addUnitOfMeasure}',
            'title={dict.app.pages.catalogUnitsOfMeasure.edit}',
            'aria-label={dict.app.pages.catalogUnitsOfMeasure.edit}',
            'title={dict.app.pages.catalogUnitsOfMeasure.delete}',
            'aria-label={dict.app.pages.catalogUnitsOfMeasure.delete}',
            'title={dict.app.pages.catalogUnitsOfMeasure.cancel}',
            'aria-label={dict.app.pages.catalogUnitsOfMeasure.cancel}',
            'title={uomSubmitLabel}',
            'aria-label={uomSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $unitsOfMeasure, 'Unit-of-measure actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const productSubmitLabel = processing ? pageDict.saving : pageDict.save;',
            'put(`/catalog/products/${editingProduct.id}`, {',
            'post(\'/catalog/products\', {',
            'destroy(`/catalog/products/${product.id}`, { preserveScroll: true });',
            'router.get(\'/catalog/products\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/catalog/products\', { ...filters, type: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'router.get(\'/catalog/products\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'router.get(\'/catalog/products\', { ...filters, product_category_id: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.addProduct}',
            'aria-label={pageDict.addProduct}',
            'title={pageDict.edit}',
            'aria-label={pageDict.edit}',
            'title={pageDict.delete}',
            'aria-label={pageDict.delete}',
            'placeholder={pageDict.codePlaceholder}',
            'title={pageDict.cancel}',
            'aria-label={pageDict.cancel}',
            'title={productSubmitLabel}',
            'aria-label={productSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $products, 'Product actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['catalogProductCategories', 'addCategory'],
                ['catalogProductCategories', 'edit'],
                ['catalogProductCategories', 'delete'],
                ['catalogProductCategories', 'cancel'],
                ['catalogProductCategories', 'saving'],
                ['catalogProductCategories', 'save'],
                ['catalogUnitsOfMeasure', 'addUnitOfMeasure'],
                ['catalogUnitsOfMeasure', 'edit'],
                ['catalogUnitsOfMeasure', 'delete'],
                ['catalogUnitsOfMeasure', 'cancel'],
                ['catalogUnitsOfMeasure', 'saving'],
                ['catalogUnitsOfMeasure', 'save'],
                ['catalogProducts', 'addProduct'],
                ['catalogProducts', 'edit'],
                ['catalogProducts', 'delete'],
                ['catalogProducts', 'codePlaceholder'],
                ['catalogProducts', 'cancel'],
                ['catalogProducts', 'saving'],
                ['catalogProducts', 'save'],
            ] as [$section, $key]) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', $section, $key], $locale);
            }
        }
    }

    public function test_sales_and_purchase_order_modal_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $salesOrders = (string) file_get_contents(resource_path('js/Pages/Sales/SalesOrders.tsx'));
        $purchaseOrders = (string) file_get_contents(resource_path('js/Pages/Purchasing/PurchaseOrders.tsx'));

        foreach ([
            'const salesOrderSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/sales/orders/${editingOrder.id}`, payload, {',
            'router.post(\'/sales/orders\', payload, {',
            'router.post(`/sales/orders/${orderId}/${action}`, {}, { preserveScroll: true });',
            'router.get(\'/sales/orders\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/sales/orders\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createSalesOrder}',
            'aria-label={pageDict.createSalesOrder}',
            'title={pageDict.addLine}',
            'aria-label={pageDict.addLine}',
            'title={pageDict.removeLine}',
            'aria-label={pageDict.removeLine}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={salesOrderSubmitLabel}',
            'aria-label={salesOrderSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $salesOrders, 'Sales Order modal and filter actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const purchaseOrderSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/purchasing/orders/${editingOrder.id}`, payload, {',
            'router.post(\'/purchasing/orders\', payload, {',
            'router.post(`/purchasing/orders/${orderId}/${action}`, {}, { preserveScroll: true });',
            'router.get(\'/purchasing/orders\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/purchasing/orders\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createPurchaseOrder}',
            'aria-label={pageDict.createPurchaseOrder}',
            'title={pageDict.addLine}',
            'aria-label={pageDict.addLine}',
            'title={pageDict.removeLine}',
            'aria-label={pageDict.removeLine}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={purchaseOrderSubmitLabel}',
            'aria-label={purchaseOrderSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $purchaseOrders, 'Purchase Order modal and filter actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['salesSalesOrders', 'createSalesOrder'],
                ['salesSalesOrders', 'addLine'],
                ['salesSalesOrders', 'removeLine'],
                ['salesSalesOrders', 'cancel_2'],
                ['salesSalesOrders', 'saving'],
                ['salesSalesOrders', 'saveDraft'],
                ['purchasingPurchaseOrders', 'createPurchaseOrder'],
                ['purchasingPurchaseOrders', 'addLine'],
                ['purchasingPurchaseOrders', 'removeLine'],
                ['purchasingPurchaseOrders', 'cancel_2'],
                ['purchasingPurchaseOrders', 'saving'],
                ['purchasingPurchaseOrders', 'saveDraft'],
            ] as [$section, $key]) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', $section, $key], $locale);
            }
        }
    }

    public function test_customer_credit_and_supplier_adjustment_note_modal_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $customerCreditNotes = (string) file_get_contents(resource_path('js/Pages/Sales/CustomerCreditNotes.tsx'));
        $supplierAdjustmentNotes = (string) file_get_contents(resource_path('js/Pages/Purchasing/SupplierAdjustmentNotes.tsx'));

        foreach ([
            'const customerCreditNoteSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/sales/credit-notes/${editingNote.id}`, payload, {',
            'router.post(\'/sales/credit-notes\', payload, {',
            'router.post(`/sales/credit-notes/${note.id}/${action}`',
            'router.get(\'/sales/credit-notes\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/sales/credit-notes\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createCustomerCreditNote}',
            'aria-label={pageDict.createCustomerCreditNote}',
            'title={pageDict.addLine}',
            'aria-label={pageDict.addLine}',
            'title={pageDict.removeLine}',
            'aria-label={pageDict.removeLine}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={customerCreditNoteSubmitLabel}',
            'aria-label={customerCreditNoteSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $customerCreditNotes, 'Customer Credit Note modal and filter actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const supplierAdjustmentNoteSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/purchasing/adjustment-notes/${editingNote.id}`, payload, {',
            'router.post(\'/purchasing/adjustment-notes\', payload, {',
            "confirmCode: 'POST_SUPPLIER_ADJUSTMENT_NOTE'",
            'router.post(`/purchasing/adjustment-notes/${noteId}/${action}`, {}, { preserveScroll: true });',
            'router.post(pendingSensitiveAction.url, payload, {',
            'router.get(\'/purchasing/adjustment-notes\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/purchasing/adjustment-notes\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createAdjustmentNote}',
            'aria-label={pageDict.createAdjustmentNote}',
            'title={pageDict.addLine}',
            'aria-label={pageDict.addLine}',
            'title={pageDict.removeLine}',
            'aria-label={pageDict.removeLine}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={supplierAdjustmentNoteSubmitLabel}',
            'aria-label={supplierAdjustmentNoteSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $supplierAdjustmentNotes, 'Supplier Adjustment Note modal and filter actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['salesCustomerCreditNotes', 'createCustomerCreditNote'],
                ['salesCustomerCreditNotes', 'addLine'],
                ['salesCustomerCreditNotes', 'removeLine'],
                ['salesCustomerCreditNotes', 'cancel_2'],
                ['salesCustomerCreditNotes', 'saving'],
                ['salesCustomerCreditNotes', 'saveDraft'],
                ['purchasingSupplierAdjustmentNotes', 'createAdjustmentNote'],
                ['purchasingSupplierAdjustmentNotes', 'addLine'],
                ['purchasingSupplierAdjustmentNotes', 'removeLine'],
                ['purchasingSupplierAdjustmentNotes', 'cancel_2'],
                ['purchasingSupplierAdjustmentNotes', 'saving'],
                ['purchasingSupplierAdjustmentNotes', 'saveDraft'],
            ] as [$section, $key]) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', $section, $key], $locale);
            }
        }
    }

    public function test_fixed_asset_master_data_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $categories = (string) file_get_contents(resource_path('js/Pages/FixedAssets/Categories.tsx'));
        $locations = (string) file_get_contents(resource_path('js/Pages/FixedAssets/Locations.tsx'));

        foreach ([
            'const categorySubmitLabel = processing ? appDict.saving : appDict.save;',
            'put(`/fixed-asset-categories/${editingCategory.id}`, {',
            'post(\'/fixed-asset-categories\', {',
            'router.delete(`/fixed-asset-categories/${id}`, { preserveScroll: true });',
            'title={appDict.createAssetCategory}',
            'aria-label={appDict.createAssetCategory}',
            'title={appDict.editAssetCategory}',
            'aria-label={appDict.editAssetCategory}',
            'title={appDict.delete}',
            'aria-label={appDict.delete}',
            'title={appDict.back}',
            'aria-label={appDict.back}',
            'title={categorySubmitLabel}',
            'aria-label={categorySubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $categories, 'Fixed Asset Category actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const locationSubmitLabel = form.processing ? appDict.saving : appDict.save;',
            'form.put(`/fixed-asset-locations/${editingLocation.id}`, {',
            'form.post(\'/fixed-asset-locations\', {',
            'router.delete(`/fixed-asset-locations/${location.id}`, { preserveScroll: true });',
            'title={appDict.createAssetLocation}',
            'aria-label={appDict.createAssetLocation}',
            'title={appDict.edit}',
            'aria-label={appDict.edit}',
            'title={appDict.delete}',
            'aria-label={appDict.delete}',
            'title={appDict.cancel}',
            'aria-label={appDict.cancel}',
            'title={locationSubmitLabel}',
            'aria-label={locationSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $locations, 'Fixed Asset Location actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                'createAssetCategory',
                'editAssetCategory',
                'createAssetLocation',
                'editAssetLocation',
                'delete',
                'back',
                'cancel',
                'saving',
                'save',
            ] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', $key], $locale);
            }
        }
    }

    public function test_notifications_and_tax_rate_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $notifications = (string) file_get_contents(resource_path('js/Pages/Notifications.tsx'));
        $taxRates = (string) file_get_contents(resource_path('js/Pages/Taxes/Rates/Index.tsx'));

        foreach ([
            'post(`/notifications/${id}/read`, { preserveScroll: true });',
            'post(\'/notifications/read-all\', { preserveScroll: true });',
            'title={label}',
            'aria-label={label}',
            'title={dict.app.notifications.all}',
            'aria-label={dict.app.notifications.all}',
            'title={dict.app.notifications.unread}',
            'aria-label={dict.app.notifications.unread}',
            'title={dict.app.notifications.read}',
            'aria-label={dict.app.notifications.read}',
            'title={dict.app.notifications.unread}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $notifications, 'Notification actions and filter tabs must remain accessible and scroll-safe.');
        }

        foreach ([
            "router.get('/taxes/rates', { tax_code_id: codeId }, { preserveState: true, preserveScroll: true, replace: true });",
            "post('/taxes/rates', {",
            'router.delete(`/taxes/rates/${id}`, { preserveScroll: true });',
            'title={taxDict.backToCodes}',
            'aria-label={taxDict.backToCodes}',
            'title={taxDict.newTaxRate}',
            'aria-label={taxDict.newTaxRate}',
            'type="button"',
            'title={taxDict.delete}',
            'aria-label={taxDict.delete}',
            'title={taxDict.cancel}',
            'aria-label={taxDict.cancel}',
            'title={taxDict.save}',
            'aria-label={taxDict.save}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $taxRates, 'Tax Rate actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['app', 'notifications', 'all'],
                ['app', 'notifications', 'unread'],
                ['app', 'notifications', 'read'],
                ['app', 'notifications', 'markRead'],
                ['app', 'notifications', 'markAllRead'],
                ['app', 'taxes', 'newTaxRate'],
                ['app', 'taxes', 'backToCodes'],
                ['app', 'taxes', 'delete'],
                ['app', 'taxes', 'cancel'],
                ['app', 'taxes', 'save'],
            ] as $path) {
                $this->assertLocalePathIsNotEmpty($dictionary, $path, $locale);
            }
        }
    }

    public function test_delivery_goods_receipt_and_purchase_return_modal_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $deliveryNotes = (string) file_get_contents(resource_path('js/Pages/Sales/DeliveryNotes.tsx'));
        $goodsReceipts = (string) file_get_contents(resource_path('js/Pages/Purchasing/GoodsReceipts.tsx'));
        $purchaseReturns = (string) file_get_contents(resource_path('js/Pages/Purchasing/PurchaseReturns.tsx'));

        foreach ([
            'const deliveryNoteSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/sales/delivery-notes/${editingNote.id}`, payload, {',
            'router.post(\'/sales/delivery-notes\', payload, {',
            'router.post(`/sales/delivery-notes/${noteId}/${action}`, {}, { preserveScroll: true });',
            'router.get(\'/sales/delivery-notes\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/sales/delivery-notes\', { ...filters, warehouse_id: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'router.get(\'/sales/delivery-notes\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createDeliveryNote}',
            'aria-label={pageDict.createDeliveryNote}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={deliveryNoteSubmitLabel}',
            'aria-label={deliveryNoteSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $deliveryNotes, 'Delivery Note modal, lifecycle, and filter actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const goodsReceiptSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/purchasing/goods-receipts/${editingReceipt.id}`, payload, {',
            'router.post(\'/purchasing/goods-receipts\', payload, {',
            'router.post(`/purchasing/goods-receipts/${receiptId}/${action}`, {}, { preserveScroll: true });',
            'router.get(\'/purchasing/goods-receipts\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/purchasing/goods-receipts\', { ...filters, warehouse_id: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'router.get(\'/purchasing/goods-receipts\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createGoodsReceipt}',
            'aria-label={pageDict.createGoodsReceipt}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={goodsReceiptSubmitLabel}',
            'aria-label={goodsReceiptSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $goodsReceipts, 'Goods Receipt modal, lifecycle, and filter actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'const purchaseReturnSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;',
            'router.put(`/purchasing/returns/${editingReturn.id}`, payload, {',
            'router.post(\'/purchasing/returns\', payload, {',
            "confirmCode: 'POST_PURCHASE_RETURN'",
            'router.post(`/purchasing/returns/${retId}/${action}`, {}, { preserveScroll: true });',
            'router.post(pendingSensitiveAction.url, payload, {',
            'router.get(\'/purchasing/returns\', { ...filters, search: val }, { preserveState: true, preserveScroll: true });',
            'router.get(\'/purchasing/returns\', { ...filters, warehouse_id: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'router.get(\'/purchasing/returns\', { ...filters, status: value || \'\' }, { preserveState: true, preserveScroll: true })',
            'title={pageDict.createPurchaseReturn}',
            'aria-label={pageDict.createPurchaseReturn}',
            'title={pageDict.cancel_2}',
            'aria-label={pageDict.cancel_2}',
            'title={purchaseReturnSubmitLabel}',
            'aria-label={purchaseReturnSubmitLabel}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $purchaseReturns, 'Purchase Return modal, lifecycle, and filter actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['salesDeliveryNotes', 'createDeliveryNote'],
                ['salesDeliveryNotes', 'cancel_2'],
                ['salesDeliveryNotes', 'saving'],
                ['salesDeliveryNotes', 'saveDraft'],
                ['purchasingGoodsReceipts', 'createGoodsReceipt'],
                ['purchasingGoodsReceipts', 'cancel_2'],
                ['purchasingGoodsReceipts', 'saving'],
                ['purchasingGoodsReceipts', 'saveDraft'],
                ['purchasingPurchaseReturns', 'createPurchaseReturn'],
                ['purchasingPurchaseReturns', 'cancel_2'],
                ['purchasingPurchaseReturns', 'saving'],
                ['purchasingPurchaseReturns', 'saveDraft'],
            ] as [$section, $key]) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'pages', $section, $key], $locale);
            }
        }
    }

    public function test_core_accounting_workflow_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $journalForm = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalForm.tsx'));
        $generalJournal = (string) file_get_contents(resource_path('js/Pages/Accounting/GeneralJournal.tsx'));
        $trialBalance = (string) file_get_contents(resource_path('js/Pages/Accounting/TrialBalance.tsx'));
        $openingBalances = (string) file_get_contents(resource_path('js/Pages/Accounting/OpeningBalances.tsx'));

        foreach ([
            "post('/accounting/journal', { preserveScroll: true });",
            'title={accDict.journal}',
            'aria-label={accDict.journal}',
            'title={accDict.addLine}',
            'aria-label={accDict.addLine}',
            'title={`${accDict.removeLine} ${idx + 1}`}',
            'aria-label={`${accDict.removeLine} ${idx + 1}`}',
            'title={accDict.saveDraftJournal}',
            'aria-label={accDict.saveDraftJournal}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $journalForm, 'Journal Form actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'title={accDict.createVoucher}',
            'aria-label={accDict.createVoucher}',
            'title={dict.app.actions.close}',
            'aria-label={dict.app.actions.close}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $generalJournal, 'General Journal actions must remain accessible.');
        }

        foreach ([
            "router.get('/accounting/trial-balance', {",
            '}, { preserveScroll: true });',
            'title={accDict.generateTrialBalance}',
            'aria-label={accDict.generateTrialBalance}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $trialBalance, 'Trial Balance generation must remain accessible and scroll-safe.');
        }

        foreach ([
            "saveForm.post('/accounting/opening-balances', { preserveScroll: true });",
            "postForm.post('/accounting/opening-balances/post', {",
            'confirmCode="POST_OPENING_BALANCES"',
            "router.get('/accounting/opening-balances', { fiscal_year_id: val }, { preserveState: false, preserveScroll: true });",
            'title={postingReadinessMessage}',
            'aria-label={postingReadinessMessage}',
            'title={accDict.saveDraft}',
            'aria-label={accDict.saveDraft}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $openingBalances, 'Opening Balance actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                'journal',
                'createVoucher',
                'addLine',
                'removeLine',
                'saveDraftJournal',
                'generateTrialBalance',
                'saveDraft',
            ] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', $key], $locale);
            }

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'actions', 'close'], $locale);
        }
    }

    public function test_report_tax_code_and_stock_balance_actions_have_accessible_names_and_scroll_safe_filters(): void
    {
        $balanceSheet = (string) file_get_contents(resource_path('js/Pages/Reports/BalanceSheet.tsx'));
        $incomeStatement = (string) file_get_contents(resource_path('js/Pages/Reports/IncomeStatement.tsx'));
        $cashFlow = (string) file_get_contents(resource_path('js/Pages/Reports/CashFlow.tsx'));
        $taxCodes = (string) file_get_contents(resource_path('js/Pages/Taxes/Codes/Index.tsx'));
        $stockBalances = (string) file_get_contents(resource_path('js/Pages/Inventory/StockBalances.tsx'));

        foreach ([
            $balanceSheet,
            $incomeStatement,
            $cashFlow,
        ] as $source) {
            foreach ([
                'preserveScroll: true',
                'title={actionsDict.printReport}',
                'aria-label={actionsDict.printReport}',
                'title={actionsDict.exportCsv}',
                'aria-label={actionsDict.exportCsv}',
                'title={accDict.applyFilter}',
                'aria-label={accDict.applyFilter}',
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $source, 'Financial report actions must remain accessible and scroll-safe.');
            }
        }

        foreach ([
            'FixedAssetRegisterReport.tsx' => 'fixed-asset-register',
            'FixedAssetDepreciationReport.tsx' => 'fixed-asset-depreciation',
            'FixedAssetDepreciationRunReport.tsx' => 'fixed-asset-depreciation-runs',
            'FixedAssetDisposalReport.tsx' => 'fixed-asset-disposals',
            'FixedAssetNetBookValueReport.tsx' => 'fixed-asset-net-book-values',
        ] as $file => $routePart) {
            $source = (string) file_get_contents(resource_path("js/Pages/Reports/{$file}"));

            foreach ([
                "router.get('/reports/{$routePart}'",
                'preserveScroll: true',
                'title={dict.app.actions.exportCsv}',
                'aria-label={dict.app.actions.exportCsv}',
                'title={dict.app.actions.printReport}',
                'aria-label={dict.app.actions.printReport}',
                'title={reportDict.backToReports}',
                'aria-label={reportDict.backToReports}',
                'title={reportDict.applyFilters}',
                'aria-label={reportDict.applyFilters}',
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "Fixed asset report {$file} actions must remain accessible and scroll-safe.");
            }
        }

        foreach ([
            "router.get('/taxes/codes', { search }, { preserveState: true, preserveScroll: true, replace: true });",
            'router.delete(`/taxes/codes/${id}`, { preserveScroll: true });',
            'title={taxDict.taxRates}',
            'aria-label={taxDict.taxRates}',
            'title={taxDict.newTaxCode}',
            'aria-label={taxDict.newTaxCode}',
            'type="button"',
            'title={taxDict.search}',
            'aria-label={taxDict.search}',
            'title={taxDict.delete}',
            'aria-label={taxDict.delete}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $taxCodes, 'Tax Code actions must remain accessible and scroll-safe.');
        }

        foreach ([
            "router.get('/inventory/stock-balances', { warehouse_id: warehouseId }, { preserveState: true, preserveScroll: true });",
            "router.get('/inventory/stock-balances', {}, { preserveState: true, preserveScroll: true });",
            'title={pageDict.applyFilter}',
            'aria-label={pageDict.applyFilter}',
            'title={pageDict.clearFilter}',
            'aria-label={pageDict.clearFilter}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $stockBalances, 'Stock Balance filter actions must remain accessible and scroll-safe.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                ['app', 'actions', 'printReport'],
                ['app', 'actions', 'exportCsv'],
                ['app', 'accounting', 'applyFilter'],
                ['app', 'pages', 'reports', 'applyFilters'],
                ['app', 'pages', 'reports', 'backToReports'],
                ['app', 'taxes', 'taxRates'],
                ['app', 'taxes', 'newTaxCode'],
                ['app', 'taxes', 'search'],
                ['app', 'taxes', 'delete'],
                ['app', 'pages', 'stockBalances', 'applyFilter'],
                ['app', 'pages', 'stockBalances', 'clearFilter'],
            ] as $path) {
                $this->assertLocalePathIsNotEmpty($dictionary, $path, $locale);
            }
        }
    }

    public function test_all_inertia_page_buttons_have_accessible_names(): void
    {
        $failures = [];

        foreach ($this->inertiaPageSourceFiles() as $relativePath => $source) {
            foreach ($this->missingAccessibleButtonLines($source) as $line) {
                $failures[] = "{$relativePath}:{$line}";
            }
        }

        $this->assertSame(
            [],
            $failures,
            'Every Inertia page <button> must expose a title or aria-label.'.PHP_EOL.implode(PHP_EOL, $failures)
        );
    }

    public function test_all_inertia_pages_avoid_native_selects_unsafe_redirects_and_loose_pagination_links(): void
    {
        $failures = [];
        $forbiddenFragments = [
            '<select',
            '<option',
            'type="date"',
            'window.location.href',
            'links: any[]',
            'links?: any[]',
            'links: unknown[]',
            'links?: unknown[]',
        ];

        foreach ($this->inertiaPageSourceFiles() as $relativePath => $source) {
            foreach ($forbiddenFragments as $fragment) {
                if (str_contains($source, $fragment)) {
                    $failures[] = "{$relativePath}: {$fragment}";
                }
            }
        }

        $this->assertSame(
            [],
            $failures,
            'Inertia pages must use shared controls, Inertia navigation, and typed pagination payloads.'.PHP_EOL.implode(PHP_EOL, $failures)
        );
    }

    public function test_dashboard_controller_delegates_page_data_to_application_service(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/DashboardController.php'));

        $this->assertTrue(class_exists(DashboardPageData::class));
        $this->assertStringContainsString('DashboardPageData $pageData', $source);
        $this->assertStringContainsString('$pageData->forUser($request->user()->id)', $source);

        foreach ([
            'use App\\Models\\Account;',
            'use App\\Models\\Currency;',
            'use App\\Models\\Customer;',
            'use App\\Models\\JournalEntry;',
            'use App\\Models\\LedgerEntry;',
            'use App\\Models\\Supplier;',
            'use Illuminate\\Support\\Facades\\DB;',
            'DB::table',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_customer_and_supplier_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'CustomerController.php' => [
                'pageDataClass' => CustomerPageData::class,
                'constructor' => 'CustomerPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\']))',
                'forbidden' => [
                    'use App\\Models\\Customer;',
                    'Customer::query',
                ],
            ],
            'SupplierController.php' => [
                'pageDataClass' => SupplierPageData::class,
                'constructor' => 'SupplierPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\']))',
                'forbidden' => [
                    'use App\\Models\\Supplier;',
                    'Supplier::query',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);
            $this->assertStringNotContainsString('use App\\Models\\Currency;', $source);
            $this->assertStringNotContainsString('Currency::query', $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_cash_and_bank_account_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'CashAccountController.php' => [
                'pageDataClass' => CashAccountPageData::class,
                'constructor' => 'CashAccountPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\CashAccount;',
                    'CashAccount::query',
                ],
            ],
            'BankAccountController.php' => [
                'pageDataClass' => BankAccountPageData::class,
                'constructor' => 'BankAccountPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\BankAccount;',
                    'BankAccount::query',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ([
                'use App\\Models\\Account;',
                'use App\\Models\\Branch;',
                'use App\\Models\\Currency;',
                'Account::query',
                'Branch::query',
                'Currency::query',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_ar_ap_opening_balance_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'CustomerOpeningBalanceController.php' => [
                'pageDataClass' => CustomerOpeningBalancePageData::class,
                'constructor' => 'CustomerOpeningBalancePageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\CustomerOpeningBalance;',
                    'use App\\Models\\Customer;',
                    'CustomerOpeningBalance::query',
                    'Customer::query',
                ],
            ],
            'SupplierOpeningBalanceController.php' => [
                'pageDataClass' => SupplierOpeningBalancePageData::class,
                'constructor' => 'SupplierOpeningBalancePageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\SupplierOpeningBalance;',
                    'use App\\Models\\Supplier;',
                    'SupplierOpeningBalance::query',
                    'Supplier::query',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ([
                'use App\\Models\\Currency;',
                'use App\\Models\\FinancialPeriod;',
                'use App\\Models\\FiscalYear;',
                'Currency::query',
                'FinancialPeriod::query',
                'FiscalYear::query',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_ar_ap_receipt_payment_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'CustomerReceiptController.php' => [
                'pageDataClass' => CustomerReceiptPageData::class,
                'constructor' => 'CustomerReceiptPageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\CustomerReceipt;',
                    'use App\\Models\\Customer;',
                    'CustomerReceipt::query',
                    'Customer::query',
                ],
            ],
            'SupplierPaymentController.php' => [
                'pageDataClass' => SupplierPaymentPageData::class,
                'constructor' => 'SupplierPaymentPageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\SupplierPayment;',
                    'use App\\Models\\Supplier;',
                    'SupplierPayment::query',
                    'Supplier::query',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ([
                'use App\\Models\\BankAccount;',
                'use App\\Models\\CashAccount;',
                'use App\\Models\\Currency;',
                'use App\\Models\\FinancialPeriod;',
                'use App\\Models\\FiscalYear;',
                'BankAccount::query',
                'CashAccount::query',
                'Currency::query',
                'FinancialPeriod::query',
                'FiscalYear::query',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_cheque_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'IncomingChequeController.php' => [
                'pageDataClass' => IncomingChequePageData::class,
                'constructor' => 'IncomingChequePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'status\', \'customer_id\']))',
                'forbidden' => [
                    'use App\\Models\\IncomingCheque;',
                    'use App\\Models\\Customer;',
                    'IncomingCheque::query',
                    'Customer::query',
                ],
            ],
            'OutgoingChequeController.php' => [
                'pageDataClass' => OutgoingChequePageData::class,
                'constructor' => 'OutgoingChequePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'status\', \'supplier_id\']))',
                'forbidden' => [
                    'use App\\Models\\OutgoingCheque;',
                    'use App\\Models\\Supplier;',
                    'OutgoingCheque::query',
                    'Supplier::query',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ([
                'use App\\Models\\BankAccount;',
                'use App\\Models\\Currency;',
                'use App\\Models\\FiscalYear;',
                'BankAccount::query',
                'Currency::query',
                'FiscalYear::query',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_allocation_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'ReceivableAllocationController.php' => [
                'pageDataClass' => ReceivableAllocationPageData::class,
                'constructor' => 'ReceivableAllocationPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'customer_id\', \'receipt_id\']))',
                'forbidden' => [
                    'use App\\Models\\CustomerReceipt;',
                    'use App\\Models\\ReceivableAllocation;',
                    'use App\\Models\\ReceivableEntry;',
                    'use App\\Models\\Customer;',
                    'CustomerReceipt::query',
                    'ReceivableAllocation::query',
                    'ReceivableEntry::query',
                    'Customer::query',
                ],
            ],
            'PayableAllocationController.php' => [
                'pageDataClass' => PayableAllocationPageData::class,
                'constructor' => 'PayableAllocationPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'supplier_id\', \'payment_id\']))',
                'forbidden' => [
                    'use App\\Models\\SupplierPayment;',
                    'use App\\Models\\PayableAllocation;',
                    'use App\\Models\\PayableEntry;',
                    'use App\\Models\\Supplier;',
                    'SupplierPayment::query',
                    'PayableAllocation::query',
                    'PayableEntry::query',
                    'Supplier::query',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_entry_settlement_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'ReceivableEntrySettlementController.php' => [
                'pageDataClass' => ReceivableEntrySettlementPageData::class,
                'constructor' => 'ReceivableEntrySettlementPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'customer_id\', \'source_entry_id\']))',
                'forbidden' => [
                    'use App\\Models\\Customer;',
                    'use App\\Models\\ReceivableAllocation;',
                    'use App\\Models\\ReceivableEntry;',
                    'use App\\Models\\ReceivableEntrySettlement;',
                    'Customer::query',
                    'ReceivableAllocation::query',
                    'ReceivableEntry::query',
                    'ReceivableEntrySettlement::query',
                    'whereRaw',
                    'array_merge',
                ],
            ],
            'PayableEntrySettlementController.php' => [
                'pageDataClass' => PayableEntrySettlementPageData::class,
                'constructor' => 'PayableEntrySettlementPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'supplier_id\', \'source_entry_id\']))',
                'forbidden' => [
                    'use App\\Models\\PayableAllocation;',
                    'use App\\Models\\PayableEntry;',
                    'use App\\Models\\PayableEntrySettlement;',
                    'use App\\Models\\Supplier;',
                    'PayableAllocation::query',
                    'PayableEntry::query',
                    'PayableEntrySettlement::query',
                    'Supplier::query',
                    'whereRaw',
                    'array_merge',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_sales_and_purchase_order_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'SalesOrderController.php' => [
                'pageDataClass' => SalesOrderPageData::class,
                'constructor' => 'SalesOrderPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'customer_id\']))',
                'forbidden' => [
                    'use App\\Models\\Currency;',
                    'use App\\Models\\Customer;',
                    'use App\\Models\\Product;',
                    'use App\\Models\\SalesOrder;',
                    'Currency::query',
                    'Customer::query',
                    'Product::query',
                    'SalesOrder::query',
                    'whereHas',
                ],
            ],
            'PurchaseOrderController.php' => [
                'pageDataClass' => PurchaseOrderPageData::class,
                'constructor' => 'PurchaseOrderPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'supplier_id\']))',
                'forbidden' => [
                    'use App\\Models\\Currency;',
                    'use App\\Models\\Product;',
                    'use App\\Models\\PurchaseOrder;',
                    'use App\\Models\\Supplier;',
                    'Currency::query',
                    'Product::query',
                    'PurchaseOrder::query',
                    'Supplier::query',
                    'whereHas',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_delivery_note_and_goods_receipt_controllers_delegate_index_page_data_to_services(): void
    {
        $controllers = [
            'DeliveryNoteController.php' => [
                'pageDataClass' => DeliveryNotePageData::class,
                'constructor' => 'DeliveryNotePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Models\\DeliveryNote;',
                    'use App\\Models\\SalesOrder;',
                    'use App\\Models\\Warehouse;',
                    'DeliveryNote::query',
                    'SalesOrder::query',
                    'Warehouse::query',
                    'whereHas',
                ],
            ],
            'GoodsReceiptController.php' => [
                'pageDataClass' => GoodsReceiptPageData::class,
                'constructor' => 'GoodsReceiptPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Models\\GoodsReceipt;',
                    'use App\\Models\\PurchaseOrder;',
                    'use App\\Models\\Warehouse;',
                    'GoodsReceipt::query',
                    'PurchaseOrder::query',
                    'Warehouse::query',
                    'whereHas',
                ],
            ],
        ];

        foreach ($controllers as $controller => $expectation) {
            $source = (string) file_get_contents(app_path("Http/Controllers/{$controller}"));

            $this->assertTrue(class_exists($expectation['pageDataClass']));
            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_customer_invoice_revision_controller_delegates_page_data_to_service(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/CustomerInvoiceRevisionController.php'));

        $this->assertTrue(class_exists(CustomerInvoiceRevisionPageData::class));
        $this->assertStringContainsString('CustomerInvoiceRevisionPageData $pageData', $source);
        $this->assertStringContainsString('$this->pageData->indexData($request->only([\'search\']))', $source);
        $this->assertStringContainsString('$this->pageData->showData($id)', $source);

        foreach ([
            'use App\\Models\\CustomerInvoiceRevision;',
            'CustomerInvoiceRevision::query',
            'whereHas',
            'json_decode',
            'snapshot_json',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_accounting_account_mapping_controller_delegates_page_data_to_service(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/AccountingAccountMappingController.php'));

        $this->assertTrue(class_exists(AccountingAccountMappingPageData::class));
        $this->assertStringContainsString('AccountingAccountMappingPageData $pageData', $source);
        $this->assertStringContainsString('$this->pageData->indexData()', $source);

        foreach ([
            'use App\\Models\\Account;',
            'use App\\Models\\AccountingAccountMapping;',
            'use App\\Models\\Branch;',
            'Account::query',
            'AccountingAccountMapping::query',
            'Branch::query',
            'orderBy',
            'values()',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_accounting_overview_controller_delegates_page_data_to_service(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Accounting/AccountingOverviewController.php'));

        $this->assertTrue(class_exists(AccountingOverviewPageData::class));
        $this->assertStringContainsString('AccountingOverviewPageData $pageData', $source);
        $this->assertStringContainsString('$this->pageData->indexData()', $source);

        foreach ([
            'use App\\Models\\Account;',
            'use App\\Models\\FiscalYear;',
            'use App\\Models\\JournalEntry;',
            'Account::query',
            'FiscalYear::query',
            'JournalEntry::query',
            'take(5)',
            "'counts' =>",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_account_category_and_type_controllers_delegate_page_data_to_services(): void
    {
        $this->assertInstanceOf(AccountCategoryPageData::class, app(AccountCategoryPageData::class));
        $this->assertInstanceOf(AccountTypePageData::class, app(AccountTypePageData::class));

        $controllers = [
            app_path('Http/Controllers/Accounting/AccountCategoryController.php') => [
                'constructor' => 'AccountCategoryPageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    "with(['accountTypes'])",
                    "withCount('accountTypes')",
                    "orderBy('sort_order')",
                    "orderBy('code')",
                    'AccountCategory::query',
                ],
            ],
            app_path('Http/Controllers/Accounting/AccountTypeController.php') => [
                'constructor' => 'AccountTypePageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    "with(['accountCategory', 'groups', 'accounts'])",
                    "withCount(['groups', 'accounts'])",
                    "where('is_active', true)",
                    "orderBy('sort_order')",
                    "orderBy('code')",
                    'AccountType::query',
                    'AccountCategory::query',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate account master-data page-data composition.");
            }
        }
    }

    public function test_journal_and_opening_balance_controllers_delegate_page_data_to_services(): void
    {
        $this->assertInstanceOf(JournalPageData::class, app(JournalPageData::class));
        $this->assertInstanceOf(OpeningBalancePageData::class, app(OpeningBalancePageData::class));

        $controllers = [
            app_path('Http/Controllers/Accounting/JournalController.php') => [
                'constructor' => 'JournalPageData $pageData',
                'delegation' => [
                    '$this->pageData->indexData($request->all())',
                    '$this->pageData->createData()',
                    '$this->pageData->showData($journalEntry)',
                ],
                'forbidden' => [
                    'use App\\Application\\Accounting\\GeneralLedgerService;',
                    'use App\\Models\\Account;',
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\FinancialPeriod;',
                    'Account::query',
                    'Branch::query',
                    'Currency::query',
                    'FinancialPeriod::query',
                    '$journalEntry->load',
                ],
            ],
            app_path('Http/Controllers/Accounting/OpeningBalanceController.php') => [
                'constructor' => 'OpeningBalancePageData $pageData',
                'delegation' => [
                    '$this->pageData->indexData($request->query(\'fiscal_year_id\'))',
                ],
                'forbidden' => [
                    'use App\\Models\\Account;',
                    'use App\\Models\\FiscalYear;',
                    'use App\\Models\\OpeningBalance;',
                    'Account::query',
                    'FiscalYear::query',
                    'OpeningBalance::query',
                    "keyBy('account_id')",
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);

            foreach ($expectation['delegation'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate journal/opening-balance page-data composition.");
            }
        }
    }

    public function test_remaining_accounting_master_data_controllers_delegate_page_data_to_services(): void
    {
        foreach ([CurrencyPageData::class, ExchangeRatePageData::class, ChartOfAccountsPageData::class, FinancialPeriodPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/Accounting/CurrencyController.php') => [
                'constructor' => 'CurrencyPageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'Currency::query',
                    'withCount',
                    "orderBy('code')",
                ],
            ],
            app_path('Http/Controllers/Accounting/ExchangeRateController.php') => [
                'constructor' => 'ExchangeRatePageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\Company;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\ExchangeRate;',
                    'Company::query',
                    'Currency::query',
                    'ExchangeRate::query',
                    'baseCurrencyRef',
                ],
            ],
            app_path('Http/Controllers/Accounting/ChartOfAccountsController.php') => [
                'constructor' => 'ChartOfAccountsPageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\Currency;',
                    'Account::query',
                    'AccountGroup::query',
                    'AccountType::query',
                    'Currency::query',
                    "with(['accountType', 'children', 'accounts'])",
                    "with(['accountType', 'group', 'currencyRef'])",
                    "whereNull('parent_id')",
                ],
            ],
            app_path('Http/Controllers/Accounting/FinancialPeriodController.php') => [
                'constructor' => 'FinancialPeriodPageData $pageData',
                'delegation' => '$this->pageData->indexData()',
                'forbidden' => [
                    'use App\\Models\\FiscalYear;',
                    'FiscalYear::query',
                    "with('periods')",
                    "orderBy('year', 'desc')",
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate accounting master-data page-data composition.");
            }
        }
    }

    public function test_catalog_controllers_delegate_index_page_data_to_services(): void
    {
        foreach ([ProductCategoryPageData::class, ProductPageData::class, UnitOfMeasurePageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/Catalog/ProductCategoryController.php') => [
                'constructor' => 'ProductCategoryPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\']))',
                'forbidden' => [
                    'use App\\Models\\ProductCategory;',
                    'ProductCategory::query',
                    'where(function',
                    "orderBy('code', 'asc')",
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/Catalog/ProductController.php') => [
                'constructor' => 'ProductPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'type\', \'status\', \'product_category_id\']))',
                'forbidden' => [
                    'use App\\Models\\Product;',
                    'use App\\Models\\ProductCategory;',
                    'use App\\Models\\UnitOfMeasure;',
                    'Product::query',
                    'ProductCategory::query',
                    'UnitOfMeasure::query',
                    'ALLOWED_TYPES',
                    'ALLOWED_STATUSES',
                    'where(function',
                    'paginate(15)',
                ],
            ],
            app_path('Http/Controllers/Catalog/UnitOfMeasureController.php') => [
                'constructor' => 'UnitOfMeasurePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\']))',
                'forbidden' => [
                    'use App\\Models\\UnitOfMeasure;',
                    'UnitOfMeasure::query',
                    'where(function',
                    "orderBy('code', 'asc')",
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate catalog index page-data composition.");
            }
        }
    }

    public function test_expense_prepaid_and_accrual_controllers_delegate_index_page_data_to_services(): void
    {
        foreach ([ExpenseCategoryPageData::class, ExpensePageData::class, PrepaidSchedulePageData::class, AccrualSchedulePageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/ExpenseCategoryController.php') => [
                'constructor' => 'ExpenseCategoryPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\']))',
                'forbidden' => [
                    'use App\\Models\\Account;',
                    'use App\\Models\\ExpenseCategory;',
                    'use App\\Models\\TaxCode;',
                    'ExpenseCategory::query',
                    'TaxCode::query',
                    'Account::query',
                    'withCount',
                    'expenseAccountOptions',
                    'paginate(15)',
                ],
            ],
            app_path('Http/Controllers/ExpenseController.php') => [
                'constructor' => 'ExpensePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Account;',
                    'use App\\Models\\BankAccount;',
                    'use App\\Models\\Branch;',
                    'use App\\Models\\CashAccount;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\Expense;',
                    'use App\\Models\\ExpenseCategory;',
                    'use App\\Models\\Supplier;',
                    'use App\\Models\\TaxCode;',
                    'Expense::query',
                    'ExpenseCategory::query',
                    'Supplier::query',
                    'CashAccount::query',
                    'BankAccount::query',
                    'Branch::query',
                    'Currency::query',
                    'TaxCode::query',
                    'expenseAccountOptions',
                    'paginate(15)',
                ],
            ],
            app_path('Http/Controllers/PrepaidScheduleController.php') => [
                'constructor' => 'PrepaidSchedulePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Account;',
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\ExpenseCategory;',
                    'use App\\Models\\PrepaidSchedule;',
                    'PrepaidSchedule::query',
                    'ExpenseCategory::query',
                    'Branch::query',
                    'Currency::query',
                    'Account::query',
                    'prepaidAssetAccounts',
                    'expenseAccounts',
                    'paginate(15)',
                ],
            ],
            app_path('Http/Controllers/AccrualScheduleController.php') => [
                'constructor' => 'AccrualSchedulePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Account;',
                    'use App\\Models\\AccrualSchedule;',
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\ExpenseCategory;',
                    'AccrualSchedule::query',
                    'ExpenseCategory::query',
                    'Branch::query',
                    'Currency::query',
                    'Account::query',
                    'expenseAccounts',
                    'liabilityAccounts',
                    'paginate(15)',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate expenses index page-data composition.");
            }
        }
    }

    public function test_fixed_asset_location_and_disposal_controllers_delegate_page_data_to_services(): void
    {
        foreach ([FixedAssetPageData::class, FixedAssetLocationPageData::class, FixedAssetDisposalPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/FixedAssets/FixedAssetController.php') => [
                'constructor' => 'FixedAssetPageData $pageData',
                'delegation' => [
                    '$this->pageData->indexData($filters, $request->user())',
                    '$this->pageData->showData($id, $request->user())',
                    '$this->pageData->assetForEditing($id)',
                    '$this->pageData->editData($asset)',
                ],
                'forbidden' => [
                    'use App\\Models\\FixedAsset;',
                    'FixedAsset::query',
                    "with('category')",
                    'findOrFail($id)',
                ],
            ],
            app_path('Http/Controllers/FixedAssets/FixedAssetLocationController.php') => [
                'constructor' => 'FixedAssetLocationPageData $pageData',
                'delegation' => [
                    '$this->pageData->indexData($filters, $request->user())',
                ],
                'forbidden' => [
                    'use App\\Models\\Branch;',
                    'Branch::query',
                    'listLocations($filters)',
                ],
            ],
            app_path('Http/Controllers/FixedAssets/FixedAssetDisposalController.php') => [
                'constructor' => 'FixedAssetDisposalPageData $pageData',
                'delegation' => [
                    '$this->pageData->indexData($request->only([\'search\', \'status\', \'disposal_type\']))',
                    '$this->pageData->showData($id)',
                ],
                'forbidden' => [
                    'use App\\Models\\FixedAssetDisposal;',
                    'FixedAssetDisposal::query',
                    'orWhereHas',
                    'latest(\'created_at\')',
                    'paginate(15)',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);

            foreach ($expectation['delegation'] as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate fixed-asset page-data composition.");
            }
        }
    }

    public function test_payroll_controllers_delegate_index_page_data_to_services(): void
    {
        foreach ([PayrollEmployeePageData::class, PayrollComponentPageData::class, PayrollRunPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/PayrollEmployeeController.php') => [
                'constructor' => 'PayrollEmployeePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\Employee;',
                    'use App\\Models\\PayrollComponent;',
                    'Employee::query',
                    'Branch::query',
                    'Currency::query',
                    'PayrollComponent::query',
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/PayrollComponentController.php') => [
                'constructor' => 'PayrollComponentPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'type\']))',
                'forbidden' => [
                    'use App\\Models\\Account;',
                    'use App\\Models\\PayrollComponent;',
                    'PayrollComponent::query',
                    'Account::query',
                    'withCount',
                    'paginate(20)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/PayrollRunController.php') => [
                'constructor' => 'PayrollRunPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\PayrollPeriod;',
                    'use App\\Models\\PayrollRun;',
                    'PayrollRun::query',
                    'PayrollPeriod::query',
                    'Branch::query',
                    'Currency::query',
                    'paginate(10)',
                    'withQueryString',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate payroll index page-data composition.");
            }
        }
    }

    public function test_rental_operational_controllers_delegate_index_page_data_to_services(): void
    {
        foreach ([RentableItemPageData::class, RentalContractPageData::class, RentalHandoverPageData::class, RentalReturnPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/RentableItemController.php') => [
                'constructor' => 'RentableItemPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'item_source\', \'branch_id\', \'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\FixedAsset;',
                    'use App\\Models\\Product;',
                    'use App\\Models\\RentableItem;',
                    'use App\\Models\\Warehouse;',
                    'RentableItem::query',
                    'Branch::query',
                    'Warehouse::query',
                    'Product::query',
                    'FixedAsset::query',
                    'Currency::query',
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/RentalContractController.php') => [
                'constructor' => 'RentalContractPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'customer_id\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Currency;',
                    'use App\\Models\\Customer;',
                    'use App\\Models\\RentableItem;',
                    'use App\\Models\\RentalContract;',
                    'RentalContract::query',
                    'Customer::query',
                    'Branch::query',
                    'RentableItem::query',
                    'Currency::query',
                    'orWhereHas',
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/RentalHandoverController.php') => [
                'constructor' => 'RentalHandoverPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\']))',
                'forbidden' => [
                    'use App\\Models\\RentalContract;',
                    'use App\\Models\\RentalHandover;',
                    'RentalHandover::query',
                    'RentalContract::query',
                    'orWhereHas',
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/RentalReturnController.php') => [
                'constructor' => 'RentalReturnPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\']))',
                'forbidden' => [
                    'use App\\Models\\RentalContract;',
                    'use App\\Models\\RentalReturn;',
                    'RentalReturn::query',
                    'RentalContract::query',
                    'orWhereHas',
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate rental index page-data composition.");
            }
        }
    }

    public function test_inventory_and_warehouse_controllers_delegate_index_page_data_to_services(): void
    {
        foreach ([WarehousePageData::class, StockBalancePageData::class, StockTransferPageData::class, StockCountPageData::class, StockAdjustmentPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/WarehouseController.php') => [
                'constructor' => 'WarehousePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'branch_id\']))',
                'forbidden' => [
                    'use App\\Models\\Branch;',
                    'use App\\Models\\Warehouse;',
                    'Warehouse::query',
                    'Branch::query',
                    'paginate(20)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/StockBalanceController.php') => [
                'constructor' => 'StockBalancePageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Models\\StockBalance;',
                    'use App\\Models\\Warehouse;',
                    'StockBalance::query',
                    'Warehouse::query',
                    'paginate(30)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/StockTransferController.php') => [
                'constructor' => 'StockTransferPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Application\\Inventory\\InventoryPageOptions;',
                    'use App\\Models\\StockTransfer;',
                    'StockTransfer::query',
                    'paginate(15)',
                    'withQueryString',
                ],
            ],
            app_path('Http/Controllers/StockCountController.php') => [
                'constructor' => 'StockCountPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Application\\Inventory\\InventoryPageOptions;',
                    'use App\\Models\\StockCount;',
                    'StockCount::query',
                    'paginate(15)',
                    'withQueryString',
                    "'currencies' =>",
                ],
            ],
            app_path('Http/Controllers/StockAdjustmentController.php') => [
                'constructor' => 'StockAdjustmentPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\', \'warehouse_id\']))',
                'forbidden' => [
                    'use App\\Application\\Inventory\\InventoryPageOptions;',
                    'use App\\Models\\StockAdjustment;',
                    'StockAdjustment::query',
                    'paginate(15)',
                    'withQueryString',
                    "'currencies' =>",
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate inventory and warehouse index page-data composition.");
            }
        }
    }

    public function test_landed_cost_and_treasury_transfer_controllers_delegate_index_page_data_to_services(): void
    {
        foreach ([LandedCostAllocationPageData::class, TreasuryTransferPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/LandedCostAllocationController.php') => [
                'constructor' => 'LandedCostAllocationPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\']))',
                'forbidden' => [
                    'use App\\Models\\GoodsReceipt;',
                    'use App\\Models\\LandedCostAllocation;',
                    'use App\\Models\\Supplier;',
                    'LandedCostAllocation::query',
                    'GoodsReceipt::query',
                    'Supplier::query',
                    'orWhereHas',
                    'paginate(15)',
                    'withQueryString',
                    "'activeSuppliers' =>",
                    "'confirmedGoodsReceipts' =>",
                ],
            ],
            app_path('Http/Controllers/TreasuryTransferController.php') => [
                'constructor' => 'TreasuryTransferPageData $pageData',
                'delegation' => '$this->pageData->indexData($request->only([\'search\', \'status\']))',
                'forbidden' => [
                    'use App\\Models\\BankAccount;',
                    'use App\\Models\\CashAccount;',
                    'use App\\Models\\FinancialPeriod;',
                    'use App\\Models\\FiscalYear;',
                    'use App\\Models\\TreasuryTransfer;',
                    'TreasuryTransfer::query',
                    'CashAccount::query',
                    'BankAccount::query',
                    'FiscalYear::query',
                    'FinancialPeriod::query',
                    'paginate(15)',
                    'withQueryString',
                    "'cashAccounts' =>",
                    "'bankAccounts' =>",
                    "'fiscalYears' =>",
                    "'financialPeriods' =>",
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);
            $this->assertStringContainsString($expectation['delegation'], $source);

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate operational index page-data composition.");
            }
        }
    }

    public function test_tax_controllers_delegate_page_data_to_services(): void
    {
        foreach ([TaxCodePageData::class, TaxRatePageData::class, TaxPeriodPageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        $controllers = [
            app_path('Http/Controllers/Taxes/TaxCodeController.php') => [
                'constructor' => 'TaxCodePageData $pageData',
                'delegations' => [
                    '$this->pageData->indexData($request->only([\'search\']))',
                    '$this->pageData->editData($id)',
                ],
                'forbidden' => [
                    'use App\\Models\\TaxCode;',
                    'TaxCode::query',
                    'withCount',
                    'withQueryString',
                    'paginate(20)',
                ],
            ],
            app_path('Http/Controllers/Taxes/TaxRateController.php') => [
                'constructor' => 'TaxRatePageData $pageData',
                'delegations' => [
                    '$this->pageData->indexData($request->only([\'tax_code_id\']))',
                ],
                'forbidden' => [
                    'use App\\Models\\TaxCode;',
                    'use App\\Models\\TaxRate;',
                    'TaxCode::query',
                    'TaxRate::query',
                    'withQueryString',
                    'paginate(20)',
                ],
            ],
            app_path('Http/Controllers/Taxes/TaxPeriodController.php') => [
                'constructor' => 'TaxPeriodPageData $pageData',
                'delegations' => [
                    '$this->pageData->indexData()',
                    '$this->pageData->showData($id)',
                ],
                'forbidden' => [
                    'use App\\Models\\Company;',
                    'use App\\Models\\Currency;',
                    'Company::query',
                    'Currency::query',
                    'baseCurrency',
                    'listPeriods()',
                    'getPeriod($id)',
                ],
            ],
        ];

        foreach ($controllers as $path => $expectation) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString($expectation['constructor'], $source);

            foreach ($expectation['delegations'] as $delegation) {
                $this->assertStringContainsString($delegation, $source);
            }

            foreach ($expectation['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate tax page-data composition.");
            }
        }
    }

    public function test_dashboard_uses_dictionary_for_missing_user_name(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Dashboard.tsx'));

        $this->assertStringNotContainsString("|| 'User'", $source);
        $this->assertStringContainsString('dict.app.header.unknownUser', $source);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'header', 'unknownUser'], $locale);
        }
    }

    public function test_fixed_asset_financial_values_are_formatted_without_raw_minor_or_hardcoded_masks(): void
    {
        foreach ([
            'FixedAssets/Index.tsx',
            'FixedAssets/Categories.tsx',
            'FixedAssets/Show.tsx',
            'FixedAssets/Disposals/Index.tsx',
            'FixedAssets/Disposals/Show.tsx',
            'FixedAssets/DepreciationRuns/Index.tsx',
            'FixedAssets/DepreciationRuns/Preview.tsx',
            'FixedAssets/DepreciationRuns/Show.tsx',
        ] as $relativePath) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ([
                '***',
                'formatMoney(item.proceeds_minor, item.asset?.currency)',
                'formatMoney(item.net_book_value_minor, item.asset?.currency)',
                'formatMoney(item.gain_minor, item.asset?.currency)',
                'formatMoney(item.loss_minor, item.asset?.currency)',
                '<span className="text-slate-500">0.00</span>',
                '{run.total_depreciation_minor}',
                '{totalDepreciationMinor}',
                '{row.depreciation_minor}',
                '{row.accumulated_depreciation_minor}',
                '{row.net_book_value_minor}',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not expose raw minor values or hardcoded financial masks.");
            }
        }

        $this->assertStringContainsString('formatMoney(asset.cost_minor, asset.currency)', (string) file_get_contents(resource_path('js/Pages/FixedAssets/Index.tsx')));
        $this->assertStringContainsString('appDict.restrictedValue', (string) file_get_contents(resource_path('js/Pages/FixedAssets/Index.tsx')));
        $this->assertStringContainsString('formatMoney(asset.cost_minor, asset.currency)', (string) file_get_contents(resource_path('js/Pages/FixedAssets/Show.tsx')));
        $this->assertStringContainsString('formatAssetMoney(item.proceeds_minor, item.asset)', (string) file_get_contents(resource_path('js/Pages/FixedAssets/Disposals/Index.tsx')));
        $this->assertStringContainsString('formatAmount(run.total_depreciation_minor)', (string) file_get_contents(resource_path('js/Pages/FixedAssets/DepreciationRuns/Index.tsx')));
        $this->assertStringContainsString('formatAmount(totalDepreciationMinor)', (string) file_get_contents(resource_path('js/Pages/FixedAssets/DepreciationRuns/Preview.tsx')));

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', 'restrictedValue'], $locale);
        }
    }

    public function test_fixed_asset_workflow_modals_use_searchable_select_controls(): void
    {
        foreach ([
            'FixedAssets/Create.tsx' => [
                'const categoryOptions',
                'const currencyOptions',
                'options={categoryOptions}',
                'options={currencyOptions}',
            ],
            'FixedAssets/Show.tsx' => [
                'const disposalTypeOptions',
                'options={disposalTypeOptions}',
                'disposalDict.disposalType',
            ],
            'FixedAssets/DepreciationRuns/Index.tsx' => [
                'const periodOptions',
                'options={periodOptions}',
                'appDict.selectOption',
            ],
        ] as $relativePath => $requiredFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString('SearchableSelect', $source);
            $this->assertStringNotContainsString('<select', $source, "{$relativePath} should use shared searchable select controls.");
            $this->assertStringNotContainsString('e.target.value as', $source, "{$relativePath} should avoid event-value casts.");
            $this->assertStringNotContainsString('window.location.href', $source);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $source);
            }
        }
    }

    public function test_fixed_asset_detail_financial_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/FixedAssets/Show.tsx'));

        foreach ([
            'title={appDict.back}',
            'aria-label={appDict.back}',
            'title={schedules.length > 0 ? appDict.regenerateSchedule : appDict.generateSchedule}',
            'aria-label={schedules.length > 0 ? appDict.regenerateSchedule : appDict.generateSchedule}',
            'title={appDict.editFixedAsset}',
            'aria-label={appDict.editFixedAsset}',
            'title={appDict.capitalizeAsset}',
            'aria-label={appDict.capitalizeAsset}',
            'title={disposalDict.disposeAsset}',
            'aria-label={disposalDict.disposeAsset}',
            'title={appDict.moveAsset}',
            'aria-label={appDict.moveAsset}',
            'title={appDict.reverseCapitalization}',
            'aria-label={appDict.reverseCapitalization}',
            'title={`${appDict.delete} ${asset.asset_number}`}',
            'aria-label={`${appDict.delete} ${asset.asset_number}`}',
            'title={appDict.cancel}',
            'aria-label={appDict.cancel}',
            'title={appDict.recordMovement}',
            'aria-label={appDict.recordMovement}',
            'title={disposalDict.cancel}',
            'aria-label={disposalDict.cancel}',
            'title={disposalDict.postDisposal}',
            'aria-label={disposalDict.postDisposal}',
            'preserveScroll: true',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Fixed asset detail actions must remain accessible and scroll-safe.');
        }

        foreach ([
            'router.delete(`/fixed-assets/${asset.id}`, {',
            'post(`/fixed-assets/${asset.id}/capitalize`, {',
            'router.post(`/fixed-assets/${asset.id}/reverse-capitalization`',
            'router.post(`/fixed-assets/${asset.id}/generate-schedule`, {}, {',
            'moveForm.post(`/fixed-assets/${asset.id}/movements`, {',
            'disposeForm.post(`/fixed-assets/${asset.id}/disposals`, {',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'Fixed asset detail financial actions must keep Inertia submissions explicit.');
        }
    }

    public function test_master_data_delete_confirmations_are_entity_specific(): void
    {
        foreach ([
            'Accounting/AccountCategories.tsx' => ['actionsDict.confirmDelete ||', 'accountingAccountCategories.areYouSureYouWantTo'],
            'Accounting/AccountTypes.tsx' => ['actionsDict.confirmDelete ||', 'accountingAccountTypes.areYouSureYouWantTo'],
            'Expenses/Categories.tsx' => ['dict.app.actions.confirmDelete'],
            'Catalog/ProductCategories.tsx' => ['catalogProductCategories.areYouSureYouWantTo'],
            'Catalog/Products.tsx' => ['catalogProducts.areYouSureYouWantTo'],
            'Catalog/UnitsOfMeasure.tsx' => ['catalogUnitsOfMeasure.areYouSureYouWantTo'],
        ] as $relativePath => $forbiddenFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must use an entity-specific delete confirmation.");
            }

            $this->assertStringContainsString('.replace(\'{name}\'', $source, "{$relativePath} must inject the visible record name into delete confirmation.");
        }

        $requiredPaths = [
            ['app', 'pages', 'accountingAccountCategories', 'confirmDeleteAccountCategory'],
            ['app', 'pages', 'accountingAccountTypes', 'confirmDeleteAccountType'],
            ['app', 'pages', 'expenseCategories', 'confirmDeleteCategory'],
            ['app', 'pages', 'catalogProductCategories', 'confirmDeleteCategory'],
            ['app', 'pages', 'catalogProducts', 'confirmDeleteProduct'],
            ['app', 'pages', 'catalogUnitsOfMeasure', 'confirmDeleteUom'],
        ];

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ($requiredPaths as $path) {
                $this->assertLocalePathIsNotEmpty($dictionary, $path, $locale);
            }
        }
    }

    public function test_accounting_master_data_pages_use_dictionary_backed_form_and_detail_text(): void
    {
        foreach ([
            'Accounting/AccountCategories.tsx' => [
                "|| 'Cancel'",
                "|| 'Save'",
                "|| 'Code'",
                "|| 'Name",
                'placeholder="e.g. ASSET"',
                'placeholder="Asset"',
                'placeholder="أصول"',
                "|| 'Normal Balance'",
                "|| 'Statement",
                "|| 'Active'",
                "|| 'Inactive'",
                "|| 'Edit'",
                "|| 'Delete'",
                "|| 'Close'",
                'SYSTEM',
                'CUSTOM',
                'CONTRA',
                "locale === 'ar'",
                'Account Types linked to this Category',
                'تفاصيل أنواع الحسابات',
            ],
            'Accounting/AccountTypes.tsx' => [
                "|| 'Account Types'",
                'Manage relational accounting classifications',
                "|| 'Add Account Type'",
                "|| 'Edit Account Type'",
                'placeholder="e.g. ASSET_CURRENT"',
                'placeholder="e.g. Current Assets"',
                'placeholder="مثال',
                "|| 'Category'",
                "|| 'Normal Balance'",
                "|| 'Statement Type'",
                "|| 'Contra Account Type'",
                "|| 'Active'",
                "|| 'Edit'",
                "|| 'Delete'",
                "|| 'Close'",
                'SYSTEM',
                'CUSTOM',
                "locale === 'ar'",
                'Account Groups linked to this Type',
                'Accounts linked to this Type',
                'تفاصيل المجموعات',
                'تفاصيل الحسابات',
                "|| 'Statement Section'",
                "|| 'Nature'",
                "|| 'Currency'",
                "|| 'Control Account'",
            ],
        ] as $relativePath => $forbiddenFragments) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must keep accounting master-data form/detail text dictionary-backed.");
            }
        }

        $requiredPaths = [
            ['app', 'pages', 'accountingAccountCategories', 'codePlaceholder'],
            ['app', 'pages', 'accountingAccountCategories', 'nameEnPlaceholder'],
            ['app', 'pages', 'accountingAccountCategories', 'nameArPlaceholder'],
            ['app', 'pages', 'accountingAccountCategories', 'systemBadge'],
            ['app', 'pages', 'accountingAccountCategories', 'customBadge'],
            ['app', 'pages', 'accountingAccountCategories', 'contraBadge'],
            ['app', 'pages', 'accountingAccountCategories', 'accountTypesLinkedDescription'],
            ['app', 'pages', 'accountingAccountTypes', 'accountTypes'],
            ['app', 'pages', 'accountingAccountTypes', 'accountTypesDesc'],
            ['app', 'pages', 'accountingAccountTypes', 'addAccountType'],
            ['app', 'pages', 'accountingAccountTypes', 'editAccountType'],
            ['app', 'pages', 'accountingAccountTypes', 'codePlaceholder'],
            ['app', 'pages', 'accountingAccountTypes', 'nameEnPlaceholder'],
            ['app', 'pages', 'accountingAccountTypes', 'nameArPlaceholder'],
            ['app', 'pages', 'accountingAccountTypes', 'contraAccountType'],
            ['app', 'pages', 'accountingAccountTypes', 'systemBadge'],
            ['app', 'pages', 'accountingAccountTypes', 'customBadge'],
            ['app', 'pages', 'accountingAccountTypes', 'accountGroupsLinkedDescription'],
            ['app', 'pages', 'accountingAccountTypes', 'accountsLinkedDescription'],
            ['app', 'pages', 'accountingAccountTypes', 'statementSection'],
            ['app', 'pages', 'accountingAccountTypes', 'nature'],
            ['app', 'pages', 'accountingAccountTypes', 'currency'],
            ['app', 'pages', 'accountingAccountTypes', 'controlAccount'],
            ['app', 'pages', 'accountingAccountTypes', 'emptyValue'],
        ];

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ($requiredPaths as $path) {
                $this->assertLocalePathIsNotEmpty($dictionary, $path, $locale);
            }
        }
    }

    public function test_fx_rates_page_uses_configured_base_currency_without_silent_fallbacks(): void
    {
        $this->withoutVite();

        Permission::findOrCreate('accounting.view', 'web');

        Currency::query()->updateOrCreate(
            ['code' => 'SAR'],
            [
                'name' => ['en' => 'Saudi Riyal', 'ar' => 'ريال سعودي'],
                'symbol' => 'SAR',
                'exponent' => 2,
            ],
        );
        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'],
                'symbol' => '$',
                'exponent' => 2,
            ],
        );
        Company::query()->create([
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
            'base_currency' => 'SAR',
            'settings_json' => [],
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('accounting.view');

        $this->actingAs($user)
            ->get('/accounting/fx-rates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/ExchangeRates')
                ->where('baseCurrency', 'SAR')
                ->where('baseCurrencyRef.code', 'SAR')
                ->has('currencies')
            );

        $source = (string) file_get_contents(resource_path('js/Pages/Accounting/ExchangeRates.tsx'));

        foreach ([
            "!== 'EGP'",
            "?? 'USD'",
            "|| 'Exchange Rates'",
            "|| 'Set Exchange Rate'",
            "|| 'Base Currency'",
            "|| 'Target Currency'",
            "|| 'Effective Date'",
            "|| 'Save FX Rate'",
            '50.2500',
            'Search FX rates by currency code',
            '1 {r.currency}',
            ' EGP)',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'ExchangeRates.tsx must use configured base currency data and dictionary-backed labels.');
        }

        $this->assertStringContainsString('baseCurrencyRef', $source);
        $this->assertStringContainsString('foreignCurrencies', $source);
        $this->assertStringContainsString('fxConversionLine', $source);

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (['rateAgainstBaseWithCurrency', 'targetCurrency', 'noBaseCurrency', 'noForeignCurrencyOptions', 'fxRatePlaceholder', 'fxConversionLine', 'noFxRates'] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', $key], $locale);
            }
        }
    }

    public function test_currency_master_page_uses_dictionary_backed_visible_text(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Accounting/Currencies.tsx'));

        foreach ([
            "|| 'Currencies'",
            "|| 'Add Currency'",
            "|| 'Total Currencies'",
            "|| 'Linked Accounts'",
            "|| 'FX Rates Configured'",
            "|| 'ISO Code'",
            "|| 'English Name'",
            "|| 'Arabic Name'",
            "|| 'Symbol'",
            "|| 'Minor Exponent'",
            "|| 'Cancel'",
            "|| 'Save'",
            "|| 'Delete'",
            "|| 'Close'",
            'placeholder="USD"',
            'placeholder="US Dollar"',
            'placeholder="الدولار الأمريكي"',
            'placeholder="$"',
            'Delete Currency',
            'Are you sure you want to delete currency',
            'Linked Accounts for',
            'Recorded FX Rates for',
            'No accounts linked to this currency.',
            'No exchange rates recorded for this currency.',
            'Click to view linked accounts',
            'Click to view recorded FX rates',
            'Cannot delete currency with linked accounts or FX rates',
            'View General Ledger for this account',
            'ISO 4217',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Currencies.tsx must keep currency master-data text dictionary-backed.');
        }

        foreach (['en', 'ar'] as $locale) {
            $dictionary = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach ([
                'currencyCodePlaceholder',
                'currencyNameEnPlaceholder',
                'currencyNameArPlaceholder',
                'currencySymbolPlaceholder',
                'deleteCurrencyTitle',
                'confirmDeleteCurrencyNamed',
                'iso4217Badge',
                'viewLinkedAccountsTitle',
                'noLinkedAccountsTitle',
                'viewRecordedFxRatesTitle',
                'noRatesRecordedTitle',
                'cannotDeleteCurrencyInUseTitle',
                'viewLedgerTitle',
            ] as $key) {
                $this->assertLocalePathIsNotEmpty($dictionary, ['app', 'accounting', $key], $locale);
            }
        }
    }

    public function test_chart_of_accounts_requires_explicit_currency_and_uses_dictionary_backed_text(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(AccountTypeSeeder::class);

        Permission::findOrCreate('accounting.create', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.create');
        $accountType = AccountType::query()->where('code', 'ASSET_CURRENT')->firstOrFail();

        $this->actingAs($user)->post('/accounting/coa/accounts', [
            'code' => '159901',
            'name_en' => 'Explicit Currency Account',
            'name_ar' => 'حساب يتطلب عملة',
            'account_type_id' => $accountType->id,
        ])->assertSessionHasErrors(['currency']);

        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/ChartOfAccounts.tsx'));
        $controllerSource = (string) file_get_contents(app_path('Http/Controllers/Accounting/ChartOfAccountsController.php'));

        foreach ([
            "currency: 'EGP'",
            "val || 'EGP'",
            "acc.currency || 'EGP'",
            "|| 'Chart of Accounts'",
            "|| 'Add Account'",
            "|| 'Account Group'",
            "|| 'Nature'",
            "|| 'Currency'",
            'e.g. 1000',
            'e.g. Current Assets',
            'e.g. 1101',
            'e.g. Petty Cash',
            'مثال:',
            'Control Account (Subledger Only)',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $pageSource);
        }

        foreach ([
            "'currency' => ['nullable'",
            "?? 'EGP'",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $controllerSource);
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'groupCodePlaceholder',
            'groupNameEnPlaceholder',
            'groupNameArPlaceholder',
            'accountCodePlaceholder',
            'accountNameEnPlaceholder',
            'accountNameArPlaceholder',
            'selectAccountCurrency',
            'noCurrencyOptions',
            'missingCurrency',
        ] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', $key], 'AR');
        }
    }

    public function test_trial_balance_uses_backend_display_currency_and_dictionary_backed_text(): void
    {
        $this->withoutVite();
        $this->seed(CurrencySeeder::class);

        Company::create([
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
            'base_currency' => 'SAR',
            'settings_json' => [],
        ]);

        Permission::findOrCreate('accounting.view', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.view');

        $this->actingAs($user)
            ->get('/accounting/trial-balance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/TrialBalance')
                ->where('displayCurrency', 'SAR')
                ->etc()
            );

        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/TrialBalance.tsx'));

        foreach ([
            "|| 'EGP'",
            "|| 'Trial Balance'",
            "|| 'All Periods (Cumulative)'",
            "|| 'Financial Period'",
            "|| 'Generate Trial Balance'",
            "|| 'Total Debits'",
            "|| 'Total Credits'",
            "|| 'Net Movement'",
            "|| 'Ending Debit (Minor)'",
            "|| 'TOTAL TRIAL BALANCE:'",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $pageSource);
        }
    }

    public function test_general_journal_uses_dictionary_backed_visible_text(): void
    {
        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/GeneralJournal.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $pageSource, 'GeneralJournal.tsx must not contain hardcoded Arabic UI text.');

        foreach ([
            "{ en: 'DRAFT'",
            "locale === 'ar'",
            'status.toUpperCase()',
            "|| 'ALL'",
            "|| 'DRAFT'",
            "|| 'SUBMITTED'",
            "|| 'APPROVED'",
            "|| 'POSTED'",
            "|| 'REVERSED'",
            "|| 'General Journal'",
            "|| 'Create Journal Voucher'",
            "|| 'Entry Date'",
            "|| 'Description'",
            "|| 'Reference'",
            "|| 'Created By'",
            "|| 'Manual Journal'",
            "|| 'View Full Voucher'",
            "|| 'View Detail'",
            "|| 'Filter Status'",
            "|| 'Voucher #'",
            'dict.app.pages.accountingGeneralJournal',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $pageSource, 'GeneralJournal.tsx must keep visible accounting text dictionary-backed.');
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'journal',
            'journalDesc',
            'createVoucher',
            'draftBadge',
            'entryDate',
            'description',
            'reference',
            'createdBy',
            'manualJournal',
            'viewDetail',
            'viewFullVoucher',
            'filterStatus',
            'voucherNumber',
            'noJournals',
            'noJournalsDesc',
            'statusAll',
            'statusDraft',
            'statusSubmitted',
            'statusApproved',
            'statusPosted',
            'statusReversed',
            'statusUnknown',
            'systemActor',
            'notAvailable',
        ] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', $key], 'AR');
        }
    }

    public function test_journal_detail_uses_dictionary_backed_visible_text(): void
    {
        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalDetail.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $pageSource, 'JournalDetail.tsx must not contain hardcoded Arabic UI text.');

        foreach ([
            'accDict.statusDraft ||',
            'status.toUpperCase()',
            "|| 'Journal Voucher: '",
            "|| 'DRAFT'",
            "|| 'Created on'",
            "|| 'Submit for Approval'",
            "|| 'Approve'",
            "|| 'Post to Ledger'",
            "|| 'Reverse Entry'",
            "|| 'UNASSIGNED DRAFT'",
            "|| 'Sequence Key'",
            "|| 'Document Status'",
            "|| 'Entry Date'",
            "|| 'Total Lines'",
            "|| 'Reverse Journal Entry'",
            "|| 'Voucher Number'",
            "|| 'Currency'",
            "|| 'Reference'",
            "|| 'Created By'",
            "|| 'Description'",
            "|| 'Audit Trail'",
            "|| 'Posted Date'",
            "|| 'Reverses Entry'",
            "|| 'Reversal Entry'",
            "|| 'Account Code'",
            "|| 'Account Name'",
            "|| 'Memo'",
            "|| 'Debit (Minor)'",
            "|| 'Credit (Minor)'",
            "|| 'TOTAL:'",
            'dict.app.pages.accountingJournalDetail',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $pageSource, 'JournalDetail.tsx must keep visible voucher workflow text dictionary-backed.');
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'journalVoucherPrefix',
            'createdOn',
            'submitForApproval',
            'approve',
            'postToLedger',
            'confirmPostJournal',
            'reverseEntry',
            'reverseJournalEntry',
            'reverseEntryDescription',
            'unassignedDraft',
            'systemActor',
            'sequenceKey',
            'documentStatus',
            'entryDate',
            'totalLines',
            'voucherNumber',
            'currency',
            'reference',
            'createdBy',
            'descriptionMemo',
            'auditTrail',
            'postedDate',
            'reversesEntry',
            'reversalEntry',
            'accountCode',
            'accountName',
            'lineMemo',
            'debitMinor',
            'creditMinor',
            'totalLabel',
            'statusDraft',
            'statusSubmitted',
            'statusApproved',
            'statusPosted',
            'statusReversed',
            'statusUnknown',
            'notAvailable',
        ] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', $key], 'AR');
        }

        $this->assertStringContainsString('const handlePostJournal', $pageSource);
        $this->assertStringContainsString('setShowPostConfirmation(true);', $pageSource);
        $this->assertStringContainsString('confirmCode="POST_JOURNAL_ENTRY"', $pageSource);
        $this->assertStringContainsString('onClick={handlePostJournal}', $pageSource);
        $this->assertStringContainsString('title={accDict.confirmPostJournal}', $pageSource);
    }

    public function test_journal_detail_financial_actions_have_accessible_names_and_scroll_safe_submissions(): void
    {
        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalDetail.tsx'));

        foreach ([
            'postForm.post(`/accounting/journal/${journal.id}/post`, {',
            'confirmCode="POST_JOURNAL_ENTRY"',
            'submitForm.post(`/accounting/journal/${journal.id}/submit`, { preserveScroll: true })',
            'approveForm.post(`/accounting/journal/${journal.id}/approve`, { preserveScroll: true })',
            'reverseForm.post(`/accounting/journal/${journal.id}/reverse`, { preserveScroll: true });',
            'title={accDict.submitForApproval}',
            'aria-label={accDict.submitForApproval}',
            'title={accDict.approve}',
            'aria-label={accDict.approve}',
            'title={accDict.confirmPostJournal}',
            'aria-label={accDict.confirmPostJournal}',
            'title={accDict.reverseEntry}',
            'aria-label={accDict.reverseEntry}',
            'title={dict.app.actions.close}',
            'aria-label={dict.app.actions.close}',
            'title={dict.app.actions.numberDetails}',
            'aria-label={dict.app.actions.numberDetails}',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $pageSource, 'Journal detail financial actions must remain accessible and scroll-safe.');
        }
    }

    public function test_journal_form_requires_explicit_currency_and_dictionary_backed_text(): void
    {
        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/JournalForm.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $pageSource, 'JournalForm.tsx must not contain hardcoded Arabic UI text.');

        foreach ([
            "currency: currencies[0]?.code ?? 'EGP'",
            "|| currencies[0]?.code || 'EGP'",
            "|| 'EGP'",
            "?? 'EGP'",
            "|| 'CONTROL'",
            '|| dict.app.pages.accountingJournalForm',
            'dict.app.pages.accountingJournalForm',
            'placeholder="e.g. REF-1001"',
            'briefSummaryOfTransactionPurpose',
            "|| 'Create Journal Voucher'",
            "|| 'General Journal'",
            "|| 'No open financial period",
            "|| 'Total Debit'",
            "|| 'Total Credit'",
            "|| 'Difference'",
            "|| 'Entry Date'",
            "|| 'Financial Period'",
            "|| 'Reference'",
            "|| 'Currency'",
            "|| 'Journal Lines'",
            "|| 'Add Line'",
            "|| 'Account'",
            "|| 'Save Draft Journal'",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $pageSource, 'JournalForm.tsx must keep voucher creation text and currency choice dictionary-backed.');
        }

        $this->assertStringContainsString("currency: currencies[0]?.code ?? ''", $pageSource);
        $this->assertStringContainsString("setData('currency', val || '')", $pageSource);
        $this->assertStringContainsString('currencies.length === 0 || !data.currency', $pageSource);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'createVoucher',
            'createVoucherDesc',
            'journal',
            'noOpenPeriodsWarning',
            'noJournalCurrencyOptions',
            'totalDebit',
            'totalCredit',
            'difference',
            'balanced',
            'unbalanced',
            'entryDate',
            'financialPeriod',
            'reference',
            'referencePlaceholder',
            'currency',
            'descriptionMemo',
            'journalMemoPlaceholder',
            'journalLines',
            'addLine',
            'account',
            'debitLabel',
            'creditLabel',
            'lineMemo',
            'lineMemoPlaceholder',
            'saveDraftJournal',
            'controlBadge',
        ] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', $key], 'AR');
        }
    }

    public function test_opening_balances_uses_accounting_dictionary_without_legacy_page_fallbacks(): void
    {
        $pageSource = (string) file_get_contents(resource_path('js/Pages/Accounting/OpeningBalances.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $pageSource, 'OpeningBalances.tsx must not contain hardcoded Arabic UI text.');

        foreach ([
            'dict.app.pages.accountingOpeningBalances',
            "|| 'Opening Balances'",
            "|| 'Post Opening Journal to Ledger'",
            "|| 'Fiscal Year'",
            "|| 'Total Debits'",
            "|| 'Total Credits'",
            "|| 'Difference'",
            "|| 'BALANCED'",
            "|| 'UNBALANCED'",
            "|| 'Account Code'",
            "|| 'Account Name'",
            "|| 'Type / Nature'",
            "|| 'Opening Debit (Minor)'",
            "|| 'Opening Credit (Minor)'",
            "|| 'Save Draft Balances'",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $pageSource, 'OpeningBalances.tsx must keep opening-balance workflow text dictionary-backed.');
        }

        $this->assertStringContainsString('accDict.openingBalancesDesc', $pageSource);
        $this->assertStringContainsString('accDict.postOpeningJournal', $pageSource);
        $this->assertStringContainsString('const postingReadinessMessage', $pageSource);
        $this->assertStringContainsString('setShowPostConfirmation(true);', $pageSource);
        $this->assertStringContainsString('confirmCode="POST_OPENING_BALANCES"', $pageSource);
        $this->assertStringContainsString('title={postingReadinessMessage}', $pageSource);
        $this->assertStringContainsString('aria-label={postingReadinessMessage}', $pageSource);
        $this->assertStringContainsString('accDict.noOpeningBalancesConfiguredDesc', $pageSource);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'openingBalances',
            'openingBalancesDesc',
            'postOpeningJournal',
            'confirmPostOpeningJournal',
            'openingPostReady',
            'openingPostBlockedUnbalanced',
            'openingPostBlockedPosted',
            'noFiscalYearsWarning',
            'balancesAlreadyPosted',
            'fiscalYear',
            'totalDebit',
            'totalCredit',
            'difference',
            'balanced',
            'unbalanced',
            'noOpeningBalancesConfigured',
            'noOpeningBalancesConfiguredDesc',
            'accountCode',
            'accountName',
            'typeAndNature',
            'openingDebitMinor',
            'openingCreditMinor',
            'saveDraft',
        ] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', $key], 'AR');
        }
    }

    public function test_fiscal_periods_navigation_and_actions_match_route_permissions(): void
    {
        $route = Route::getRoutes()->getByName('accounting.periods');
        $this->assertNotNull($route, 'Missing accounting periods route.');

        $middleware = implode('|', $route->gatherMiddleware());
        $this->assertStringContainsString('permission.any:accounting.periods,settings.configure', $middleware);

        $layoutSource = (string) file_get_contents(resource_path('js/Components/AppLayout.tsx'));
        $periodsSource = (string) file_get_contents(resource_path('js/Pages/Accounting/Periods.tsx'));

        $this->assertStringContainsString("'accounting.periods': ['accounting.view', 'accounting.periods', 'settings.configure']", $layoutSource);
        $this->assertStringContainsString('const hasNavPermission = (permission: NavPermission): boolean', $layoutSource);
        $this->assertStringContainsString("can('accounting.periods')", $layoutSource);

        foreach ([
            "accDict.coa || 'Chart of Accounts'",
            "accDict.accountTypes || 'Account Types'",
            "accDict.journal || 'General Journal'",
            "accDict.ledger || 'General Ledger'",
            "accDict.trialBalance || 'Trial Balance'",
            "accDict.periods || 'Fiscal Periods'",
            "accDict.openingBalances || 'Opening Balances'",
            "accDict.fxRates || 'Exchange Rates'",
            "accDict.currencies || 'Currencies'",
            "(dict.app as any).taxes?.title || 'Tax Codes & Rates'",
            "(dict.app as any).taxes?.periods?.title || 'Tax Periods & Filing'",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $layoutSource, 'Accounting navigation labels must use dictionary-backed labels.');
        }

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $periodsSource, 'Periods.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString("const canCreateFiscalYear = can('settings.configure')", $periodsSource);
        $this->assertStringContainsString('actions={canCreateFiscalYear ? (', $periodsSource);
        $this->assertStringContainsString('title={tx(\'noFiscalYearsTitle\')}', $periodsSource);
        $this->assertStringContainsString('description={tx(\'noFiscalYearsDesc\')}', $periodsSource);

        foreach ([
            "|| 'Fiscal Periods'",
            "|| 'Create Fiscal Year'",
            "|| 'Close Period'",
            "|| 'Reopen Period'",
            "|| 'No fiscal years",
            'dict.app.pages.accountingPeriods',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $periodsSource, 'Periods.tsx must keep period workflow text permission-aware and dictionary-backed.');
        }

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'fiscalStructure',
            'fiscalStructureDesc',
            'coa',
            'accountTypes',
            'journal',
            'ledger',
            'trialBalance',
            'periods',
            'openingBalances',
            'fxRates',
            'currencies',
            'createFiscalYear',
            'createFiscalYearTitle',
            'noFiscalYearsTitle',
            'noFiscalYearsDesc',
            'closePeriod',
            'reopenPeriod',
            'checkingReadiness',
            'periodReadyToClose',
            'closeBlockersTitle',
            'closeBlockersDesc',
            'closeNotePlaceholder',
        ] as $key) {
            $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', $key], 'EN');
            $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', $key], 'AR');
        }

        foreach ([['app', 'taxes', 'title'], ['app', 'taxes', 'periods', 'title']] as $path) {
            $this->assertLocalePathIsNotEmpty($en, $path, 'EN');
            $this->assertLocalePathIsNotEmpty($ar, $path, 'AR');
        }
    }

    public function test_financial_statement_mapping_delete_confirmation_names_the_statement_line(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Accounting/FinancialStatementMappings.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, 'FinancialStatementMappings.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString('function statementLineDeleteMessage(line: StatementLineRow)', $source);
        $this->assertStringContainsString('accDict.confirmDeleteStatementLine', $source);
        $this->assertStringContainsString(".replace('{code}', line.code)", $source);
        $this->assertStringContainsString(".replace('{name}', getLocalizedName(line.name, locale))", $source);
        $this->assertStringNotContainsString('confirm(actionsDict.confirmDelete)', $source);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', 'confirmDeleteStatementLine'], 'EN');
        $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', 'confirmDeleteStatementLine'], 'AR');
    }

    public function test_account_mapping_delete_confirmation_names_key_branch_and_account(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Accounting/AccountMappings.tsx'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $source, 'AccountMappings.tsx must not contain hardcoded Arabic UI text.');
        $this->assertStringContainsString('function mappingDeleteMessage(mapping: MappingRow)', $source);
        $this->assertStringContainsString('const keyLabel = mappingKeyLabels[mapping.key] || mapping.key', $source);
        $this->assertStringContainsString('const branchLabel = mapping.branch', $source);
        $this->assertStringContainsString('const accountLabel = `${mapping.account.code} - ${getLocalizedName(mapping.account.name, locale)}`', $source);
        $this->assertStringContainsString('confirm(mappingDeleteMessage(mapping))', $source);
        $this->assertStringNotContainsString('confirm(accDict.accountMappingDeleteConfirm)', $source);

        $en = json_decode(file_get_contents(resource_path('js/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(resource_path('js/locales/ar.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertLocalePathIsNotEmpty($en, ['app', 'accounting', 'accountMappingDeleteConfirm'], 'EN');
        $this->assertLocalePathIsNotEmpty($ar, ['app', 'accounting', 'accountMappingDeleteConfirm'], 'AR');

        foreach (['{key}', '{branch}', '{account}'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $en['app']['accounting']['accountMappingDeleteConfirm']);
            $this->assertStringContainsString($placeholder, $ar['app']['accounting']['accountMappingDeleteConfirm']);
        }
    }

    public function test_shared_csv_report_response_is_used_for_simple_row_exports(): void
    {
        $response = app(CsvReportResponse::class)->fromRows(
            'sample.csv',
            ['Code', 'Amount Minor'],
            [['code' => 'TST', 'amount_minor' => 12345]],
            fn (array $row): array => [$row['code'], $row['amount_minor']]
        );

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="sample.csv"', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Code,"Amount Minor"', $content);
        $this->assertStringContainsString('TST,12345', $content);

        foreach ([
            app_path('Http/Controllers/Reports/FixedAssetReportController.php'),
            app_path('Http/Controllers/Reports/VatReportController.php'),
        ] as $path) {
            $this->assertStringNotContainsString('private function csvResponse', (string) file_get_contents($path));
        }
    }

    public function test_report_exporters_use_the_shared_csv_stream_boundary(): void
    {
        foreach (glob(app_path('Application/Reports/*.php')) ?: [] as $path) {
            if (str_ends_with($path, 'CsvReportResponse.php')) {
                continue;
            }

            $source = (string) file_get_contents($path);

            foreach (['response()->stream(', "fopen('php://output'", 'fclose($handle)', 'private function stream('] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must rely on CsvReportResponse for stream lifecycle.");
            }
        }
    }

    public function test_super_admin_protection_is_centralized_and_blocks_last_admin_weakening(): void
    {
        $superRole = Role::query()->firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
        $accountantRole = Role::query()->firstOrCreate(['name' => 'ACCOUNTANT', 'guard_name' => 'web']);
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole($superRole);

        $protection = app(SuperAdminProtection::class);

        $this->assertTrue($protection->isSuperAdmin($superAdmin));
        $this->assertSame(1, $protection->activeSuperAdminCount());
        $this->assertTrue($protection->wouldDeactivateLastActiveSuperAdmin($superAdmin, true, false));
        $this->assertTrue($protection->wouldWeakenLastActiveSuperAdmin($superAdmin, true, $accountantRole->id));
        $this->assertFalse($protection->wouldWeakenLastActiveSuperAdmin($superAdmin, true, $superRole->id));
        $this->assertTrue($protection->wouldRemoveLastActiveSuperAdmin($superAdmin, $superRole));

        foreach ([
            app_path('Http/Controllers/Settings/UserSettingsController.php'),
            app_path('Http/Controllers/Settings/UserRoleAssignmentController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString('whereRaw(\'LOWER(name) LIKE ?', $source);
            $this->assertStringNotContainsString('function activeSuperAdminCount', $source);
        }
    }

    public function test_gl_posting_routes_require_financial_visibility_permission(): void
    {
        foreach ($this->glPostingRouteNames() as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route [{$routeName}].");

            $middleware = implode('|', $route->gatherMiddleware());

            $this->assertStringContainsString(
                'view_financials',
                $middleware,
                "Route [{$routeName}] must require view_financials for GL/subledger posting."
            );
        }
    }

    public function test_state_changing_routes_are_auth_gated_and_authorized_or_explicitly_allowlisted(): void
    {
        $publicStateChangingRoutes = [
            'login.store',
            'locale.update',
        ];

        $authOnlyScopedRoutes = [
            'foundation',
            'logout',
            'notifications.read_all',
            'notifications.read',
            'attachments.store',
            'attachments.destroy',
        ];

        foreach (Route::getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['HEAD']);

            if (array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']) === []) {
                continue;
            }

            $routeName = (string) $route->getName();
            $middleware = $route->gatherMiddleware();
            $middlewareString = implode('|', $middleware);

            if (in_array($routeName, $publicStateChangingRoutes, true)) {
                $this->assertStringNotContainsString('can:', $middlewareString);
                $this->assertStringNotContainsString('permission.', $middlewareString);

                continue;
            }

            $this->assertContains('auth', $middleware, "State-changing route [{$routeName}] must be auth gated.");

            if (in_array($routeName, $authOnlyScopedRoutes, true)) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/(^|\|)(can:|permission\.)/',
                $middlewareString,
                "State-changing route [{$routeName}] must have explicit authorization middleware or be deliberately allowlisted."
            );
        }
    }

    public function test_financial_post_actions_match_backend_permissions_in_visible_pages(): void
    {
        foreach ([
            'CustomerOpeningBalances/Index.tsx' => "can('customers.opening_balances') && can('view_financials')",
            'SupplierOpeningBalances/Index.tsx' => "can('suppliers.opening_balances') && can('view_financials')",
            'CustomerReceipts/Index.tsx' => "can('customers.receipts') && can('view_financials')",
            'SupplierPayments/Index.tsx' => "can('suppliers.payments') && can('view_financials')",
            'TreasuryTransfers/Index.tsx' => "(can('cash.post') || can('banks.post')) && can('view_financials')",
            'Inventory/StockCounts.tsx' => "can('inventory.post') && can('view_financials')",
            'Inventory/StockAdjustments.tsx' => "can('inventory.post') && can('view_financials')",
            'Sales/CustomerInvoices.tsx' => "can('sales.post') && can('view_financials')",
            'Purchasing/SupplierBills.tsx' => "can('purchasing.post') && can('view_financials')",
            'Expenses/Index.tsx' => "can('expenses.post') && can('view_financials')",
            'Expenses/Prepaids.tsx' => "can('expenses.post') && can('view_financials')",
            'Expenses/Accruals.tsx' => "can('expenses.post') && can('view_financials')",
        ] as $relativePath => $requiredFragment) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            $this->assertStringContainsString(
                $requiredFragment,
                $source,
                "{$relativePath} must hide financial posting actions without view_financials."
            );
        }

        foreach ([
            'Sales/CustomerCreditNotes.tsx' => [
                'required' => ["const canPostCustomerCreditNotes = canManageCustomerCreditNotes && can('view_financials');", 'dict.app.pages.salesCustomerCreditNotes.settle'],
                'forbidden' => ["can('sales.post')", "can('sales.submit')", "can('sales.approve')", "can('sales.cancel')", '>Settle<'],
            ],
            'Sales/SalesReturns.tsx' => [
                'required' => ["const canPostSalesReturns = canManageSalesReturns && can('view_financials');"],
                'forbidden' => ["can('sales.post')", "can('sales.submit')", "can('sales.approve')", "can('sales.cancel')"],
            ],
            'Purchasing/PurchaseReturns.tsx' => [
                'required' => ["const canPostPurchaseReturns = canManagePurchaseReturns && can('view_financials');"],
                'forbidden' => ["can('purchasing.post')", "can('purchasing.submit')", "can('purchasing.approve')", "can('purchasing.cancel')"],
            ],
            'Purchasing/SupplierAdjustmentNotes.tsx' => [
                'required' => ["const canPostSupplierAdjustmentNotes = canManageSupplierAdjustmentNotes && can('view_financials');", 'dict.app.pages.purchasingSupplierAdjustmentNotes.settle'],
                'forbidden' => ["can('purchasing.post')", "can('purchasing.submit')", "can('purchasing.approve')", "can('purchasing.cancel')", '>Settle<'],
            ],
        ] as $relativePath => $expectations) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$relativePath}"));

            foreach ($expectations['required'] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$relativePath} must match backend route permissions.");
            }

            foreach ($expectations['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$relativePath} must not use stale generic permissions or hardcoded settlement labels.");
            }
        }
    }

    public function test_sales_returns_and_credit_notes_use_page_data_services(): void
    {
        $this->assertInstanceOf(SalesReturnPageData::class, app(SalesReturnPageData::class));
        $this->assertInstanceOf(CustomerCreditNotePageData::class, app(CustomerCreditNotePageData::class));

        foreach ([
            app_path('Http/Controllers/SalesReturnController.php') => ['SalesReturn::query(', 'TaxCode::query(', 'Warehouse::query('],
            app_path('Http/Controllers/CustomerCreditNoteController.php') => ['CustomerCreditNote::query(', 'TaxCode::query(', 'SalesReturn::query('],
        ] as $path => $forbiddenFragments) {
            $source = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate page data queries to an application service.");
            }
        }

        $salesReturnData = app(SalesReturnPageData::class)->indexData([]);
        $creditNoteData = app(CustomerCreditNotePageData::class)->indexData([]);

        foreach (['salesReturns', 'activeCustomers', 'confirmedDeliveryNotes', 'postedCustomerInvoices', 'taxCodes', 'warehouses', 'filters'] as $key) {
            $this->assertArrayHasKey($key, $salesReturnData);
        }

        foreach (['customerCreditNotes', 'activeCustomers', 'postedCustomerInvoices', 'postedSalesReturns', 'taxCodes', 'filters'] as $key) {
            $this->assertArrayHasKey($key, $creditNoteData);
        }
    }

    public function test_financial_statement_mapping_controller_uses_page_data_service(): void
    {
        $pageData = app(FinancialStatementMappingPageData::class);

        $this->assertInstanceOf(FinancialStatementMappingPageData::class, $pageData);
        $this->assertArrayHasKey('statementTypes', $pageData->indexData());
        $this->assertSame([
            'en' => 'Assets',
            'ar' => 'Assets',
        ], $pageData->createLinePayload([
            'code' => 'ASSETS',
            'statement_type' => 'balance_sheet',
            'section_code' => 'current_assets',
            'name_en' => 'Assets',
            'name_ar' => '',
            'normal_balance' => 'debit',
        ])['name']);

        $source = (string) file_get_contents(app_path('Http/Controllers/FinancialStatementMappingController.php'));

        foreach (['statementTypes', 'sectionOptions', '$payload = []', "'en' => \$validated['name_en']"] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_large_report_controllers_delegate_csv_and_page_data_composition(): void
    {
        $this->assertInstanceOf(FixedAssetCsvReportExporter::class, app(FixedAssetCsvReportExporter::class));
        $this->assertInstanceOf(VatReportPageData::class, app(VatReportPageData::class));
        $this->assertInstanceOf(VatCsvReportExporter::class, app(VatCsvReportExporter::class));

        foreach ([
            app_path('Http/Controllers/Reports/FixedAssetReportController.php') => ['fromRows(', 'fputcsv(', 'englishName('],
            app_path('Http/Controllers/Reports/VatReportController.php') => ['TaxCode::query(', 'Currency::query(', 'fputcsv(', 'fromRows('],
        ] as $path => $forbiddenFragments) {
            $source = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate CSV/page-data composition.");
            }
        }
    }

    public function test_statement_report_controllers_delegate_csv_composition(): void
    {
        $cashBankExporter = app(CashBankBookCsvExporter::class);
        $partnerExporter = app(PartnerStatementCsvExporter::class);

        $this->assertInstanceOf(CashBankBookCsvExporter::class, $cashBankExporter);
        $this->assertInstanceOf(PartnerStatementCsvExporter::class, $partnerExporter);

        foreach ([
            app_path('Http/Controllers/Reports/CashBookController.php'),
            app_path('Http/Controllers/Reports/BankBookController.php'),
            app_path('Http/Controllers/Reports/CustomerStatementController.php'),
            app_path('Http/Controllers/Reports/SupplierStatementController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            foreach (['fputcsv(', 'response()->stream(', 'number_format('] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate CSV composition to an exporter.");
            }
        }

        $cashResponse = $cashBankExporter->cash([
            'cash_account' => ['code' => 'CASH', 'name' => 'Main Cash'],
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'currency' => 'EGP',
            'opening_balance_minor' => 10000,
            'period_debit_minor' => 2500,
            'period_credit_minor' => 500,
            'closing_balance_minor' => 12000,
            'entries' => [[
                'entry_date' => '2026-01-02',
                'journal_number' => 'JV-2026-00001',
                'description' => 'Receipt',
                'debit_minor' => 2500,
                'credit_minor' => 500,
                'balance_after_minor' => 12000,
            ]],
        ]);

        ob_start();
        $cashResponse->sendContent();
        $cashContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="cash_book_CASH.csv"', (string) $cashResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Cash Book Report', $cashContent);
        $this->assertStringContainsString('CASH - Main Cash', $cashContent);
        $this->assertStringContainsString('25.00,5.00,120.00', $cashContent);

        $customerResponse = $partnerExporter->customer([
            'customer' => ['code' => 'CUS', 'name' => 'Example Customer'],
            'filters' => ['date_from' => '2026-01-01', 'date_to' => '2026-01-31', 'currency' => 'EGP'],
            'opening_balance_minor' => 10000,
            'total_debit_minor' => 4000,
            'total_credit_minor' => 1500,
            'closing_balance_minor' => 12500,
            'lines' => [[
                'date' => '2026-01-03',
                'type' => 'Receivable Entry',
                'reference' => 'RE-1',
                'description' => 'Invoice',
                'debit_minor' => 4000,
                'credit_minor' => 1500,
                'running_balance_minor' => 12500,
            ]],
        ]);

        ob_start();
        $customerResponse->sendContent();
        $customerContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="customer_statement_CUS.csv"', (string) $customerResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Customer Statement Report', $customerContent);
        $this->assertStringContainsString('CUS - Example Customer', $customerContent);
        $this->assertStringContainsString('40.00,15.00,125.00', $customerContent);
    }

    public function test_report_controllers_delegate_selector_options_to_page_options_service(): void
    {
        $this->assertInstanceOf(ReportPageOptions::class, app(ReportPageOptions::class));

        foreach ([
            app_path('Http/Controllers/Reports/ArAgingController.php'),
            app_path('Http/Controllers/Reports/ApAgingController.php'),
            app_path('Http/Controllers/Reports/ArToGlReconciliationController.php'),
            app_path('Http/Controllers/Reports/ApToGlReconciliationController.php'),
            app_path('Http/Controllers/Reports/CashBookController.php'),
            app_path('Http/Controllers/Reports/BankBookController.php'),
            app_path('Http/Controllers/Reports/CustomerStatementController.php'),
            app_path('Http/Controllers/Reports/SupplierStatementController.php'),
            app_path('Http/Controllers/Reports/ChequeRegisterReportController.php'),
            app_path('Http/Controllers/Reports/BankReconciliationReportController.php'),
            app_path('Http/Controllers/Reports/BranchOperationalReportController.php'),
            app_path('Http/Controllers/Reports/BranchProfitabilityReportController.php'),
            app_path('Http/Controllers/Reports/SalesOrderReportController.php'),
            app_path('Http/Controllers/Reports/PurchaseOrderReportController.php'),
            app_path('Http/Controllers/Reports/CustomerInvoiceReportController.php'),
            app_path('Http/Controllers/Reports/SupplierBillReportController.php'),
            app_path('Http/Controllers/Reports/DeliveryNoteReportController.php'),
            app_path('Http/Controllers/Reports/GoodsReceiptReportController.php'),
            app_path('Http/Controllers/Reports/StockMovementReportController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString('ReportPageOptions', $source, "{$path} must use the shared report selector service.");

            foreach ([
                'Customer::query(',
                'Supplier::query(',
                'Product::query(',
                'Currency::query(',
                'BankAccount::query(',
                'CashAccount::query(',
                'Warehouse::query(',
                'Branch::query(',
                'DB::table(',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must not build report selector queries inline.");
            }
        }
    }

    public function test_ar_ap_and_cheque_report_controllers_delegate_csv_composition(): void
    {
        $arApExporter = app(ArApCsvReportExporter::class);
        $chequeExporter = app(ChequeRegisterCsvExporter::class);

        $this->assertInstanceOf(ArApCsvReportExporter::class, $arApExporter);
        $this->assertInstanceOf(ChequeRegisterCsvExporter::class, $chequeExporter);

        foreach ([
            app_path('Http/Controllers/Reports/ArAgingController.php'),
            app_path('Http/Controllers/Reports/ApAgingController.php'),
            app_path('Http/Controllers/Reports/ArToGlReconciliationController.php'),
            app_path('Http/Controllers/Reports/ApToGlReconciliationController.php'),
            app_path('Http/Controllers/Reports/ChequeRegisterReportController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            foreach (['fputcsv(', 'response()->stream(', 'number_format('] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate CSV composition to an exporter.");
            }
        }

        $agingResponse = $arApExporter->arAging([
            'as_of_date' => '2026-01-31',
            'currency' => 'EGP',
            'customers' => [[
                'customer' => ['code' => 'CUS', 'name' => 'Example Customer'],
                'items' => [[
                    'reference' => 'INV-1',
                    'entry_date' => '2026-01-05',
                    'due_date' => null,
                    'basis_used' => 'due_date',
                    'age_days' => 26,
                    'original_amount_minor' => 10000,
                    'allocated_minor' => 2500,
                    'unapplied_minor' => 7500,
                    'bucket' => 'current',
                ]],
            ]],
            'grand_totals' => [
                'current' => 7500,
                'b1_30' => 0,
                'b31_60' => 0,
                'b61_90' => 0,
                'over_90' => 0,
                'total' => 7500,
            ],
        ]);

        ob_start();
        $agingResponse->sendContent();
        $agingContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="ar_aging_2026-01-31.csv"', (string) $agingResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('AR Aging Report', $agingContent);
        $this->assertStringContainsString('CUS,"Example Customer",INV-1', $agingContent);
        $this->assertStringContainsString('100.00,25.00,75.00', $agingContent);

        $reconciliationResponse = $arApExporter->apToGlReconciliation([
            'as_of_date' => '2026-01-31',
            'currency' => 'EGP',
            'subledger_total_minor' => 10000,
            'gl_total_minor' => 9800,
            'difference_minor' => 200,
            'is_reconciled' => false,
            'supplier_breakdown' => [[
                'supplier_code' => 'SUP',
                'supplier_name' => 'Example Supplier',
                'subledger_balance_minor' => 10000,
            ]],
        ]);

        ob_start();
        $reconciliationResponse->sendContent();
        $reconciliationContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="ap_to_gl_reconciliation_2026-01-31.csv"', (string) $reconciliationResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('AP to GL Reconciliation Report', $reconciliationContent);
        $this->assertStringContainsString('"UNRECONCILED DIFFERENCE"', $reconciliationContent);
        $this->assertStringContainsString('SUP,"Example Supplier",100.00', $reconciliationContent);

        $chequeResponse = $chequeExporter->export([
            'direction' => 'all',
            'filters' => ['currency' => 'EGP'],
            'items' => [[
                'direction' => 'incoming',
                'cheque_number' => 'CHQ-1',
                'party_code' => 'CUS',
                'party_name' => 'Example Customer',
                'bank_account_name' => 'Main Bank',
                'due_date' => '2026-02-01',
                'amount_minor' => 5550,
                'status' => 'on_hand',
            ]],
            'total_count' => 1,
            'incoming_total_minor' => 5550,
            'outgoing_total_minor' => 0,
            'total_amount_minor' => 5550,
        ]);

        ob_start();
        $chequeResponse->sendContent();
        $chequeContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="cheque_register_report.csv"', (string) $chequeResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Cheque Register Report', $chequeContent);
        $this->assertStringContainsString('INCOMING,CHQ-1,CUS,"Example Customer","Main Bank",2026-02-01,55.50,ON_HAND', $chequeContent);
    }

    public function test_financial_statement_and_branch_report_controllers_delegate_csv_composition(): void
    {
        $financialStatementExporter = app(FinancialStatementCsvExporter::class);
        $branchExporter = app(BranchProfitabilityCsvExporter::class);

        $this->assertInstanceOf(FinancialStatementCsvExporter::class, $financialStatementExporter);
        $this->assertInstanceOf(BranchProfitabilityCsvExporter::class, $branchExporter);

        foreach ([
            app_path('Http/Controllers/Reports/BalanceSheetReportController.php'),
            app_path('Http/Controllers/Reports/IncomeStatementReportController.php'),
            app_path('Http/Controllers/Reports/CashFlowReportController.php'),
            app_path('Http/Controllers/Reports/BranchProfitabilityReportController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            foreach (['fputcsv(', 'response()->stream(', 'localizedExportName('] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate CSV composition to an exporter.");
            }
        }

        $balanceSheetResponse = $financialStatementExporter->balanceSheet([
            'as_of_date' => '2026-01-31',
            'sections' => [
                'assets' => [
                    'total_minor' => 10000,
                    'lines' => [[
                        'code' => 'CURRENT_ASSETS',
                        'name' => ['en' => 'Current Assets', 'ar' => 'Current Assets'],
                        'total_minor' => 10000,
                        'accounts' => [[
                            'code' => '1100',
                            'name' => ['en' => 'Cash', 'ar' => 'Cash'],
                            'debit_minor' => 10000,
                            'credit_minor' => 0,
                            'net_minor' => 10000,
                        ]],
                    ]],
                ],
            ],
            'summary' => [
                'total_current_assets_minor' => 10000,
                'total_non_current_assets_minor' => 0,
                'total_assets_minor' => 10000,
                'total_current_liabilities_minor' => 0,
                'total_non_current_liabilities_minor' => 0,
                'total_liabilities_minor' => 0,
                'total_equity_minor' => 10000,
                'current_period_net_income_minor' => 0,
                'total_equity_including_net_income_minor' => 10000,
                'total_liabilities_and_equity_minor' => 10000,
                'is_balanced' => true,
                'imbalance_minor' => 0,
            ],
        ]);

        ob_start();
        $balanceSheetResponse->sendContent();
        $balanceSheetContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="balance_sheet_2026-01-31.csv"', (string) $balanceSheetResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('BALANCE SHEET REPORT', $balanceSheetContent);
        $this->assertStringContainsString('assets,CURRENT_ASSETS,"Current Assets",1100,Cash,10000,0,10000', $balanceSheetContent);

        $cashFlowResponse = $financialStatementExporter->cashFlow([
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'opening_cash_minor' => 1000,
            'operating' => ['inflows_minor' => 4000, 'outflows_minor' => 1000, 'net_minor' => 3000],
            'investing' => ['inflows_minor' => 0, 'outflows_minor' => 500, 'net_minor' => -500],
            'financing' => ['inflows_minor' => 0, 'outflows_minor' => 0, 'net_minor' => 0],
            'unclassified' => ['inflows_minor' => 0, 'outflows_minor' => 0, 'net_minor' => 0],
            'unclassified_warnings' => [],
            'net_cash_change_minor' => 2500,
            'closing_cash_minor' => 3500,
            'reconciled_closing_cash_minor' => 3500,
            'is_reconciled' => true,
        ]);

        ob_start();
        $cashFlowResponse->sendContent();
        $cashFlowContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="cash_flow_2026-01-01_to_2026-01-31.csv"', (string) $cashFlowResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('CASH FLOW STATEMENT REPORT', $cashFlowContent);
        $this->assertStringContainsString('"NET CASH FROM OPERATING ACTIVITIES",3000', $cashFlowContent);

        $branchResponse = $branchExporter->export([
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'base_currency' => 'EGP',
            'currency_codes' => ['EGP'],
            'rows' => [[
                'branch_code' => 'BR-1',
                'branch_name' => ['en' => 'Main Branch', 'ar' => 'Main Branch'],
                'is_active' => true,
                'is_unassigned' => false,
                'ledger_row_count' => 2,
                'revenue_minor' => 10000,
                'contra_revenue_minor' => 0,
                'net_revenue_minor' => 10000,
                'cogs_minor' => 4000,
                'gross_profit_minor' => 6000,
                'operating_expense_minor' => 1000,
                'operating_income_minor' => 5000,
                'other_income_minor' => 0,
                'other_expense_minor' => 0,
                'net_income_minor' => 5000,
                'profit_margin_bps' => 5000,
            ]],
            'summary' => [
                'ledger_row_count' => 2,
                'net_revenue_minor' => 10000,
                'cogs_minor' => 4000,
                'gross_profit_minor' => 6000,
                'operating_expense_minor' => 1000,
                'net_income_minor' => 5000,
            ],
            'readiness' => [
                'branch_dimension_status' => 'complete',
                'unassigned_pnl_row_count' => 0,
                'unassigned_net_income_minor' => 0,
            ],
        ]);

        ob_start();
        $branchResponse->sendContent();
        $branchContent = (string) ob_get_clean();

        $this->assertStringContainsString('attachment; filename="branch_profitability_2026-01-01_to_2026-01-31.csv"', (string) $branchResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('BRANCH PROFITABILITY REPORT', $branchContent);
        $this->assertStringContainsString('BR-1,"Main Branch",YES,NO,2,10000,0,10000,4000,6000,1000,5000,0,0,5000,5000', $branchContent);
    }

    public function test_financial_statement_report_period_options_are_centralized(): void
    {
        $periodOptions = app(FinancialPeriodReportOptions::class);

        $this->assertInstanceOf(FinancialPeriodReportOptions::class, $periodOptions);

        $fiscalYear = FiscalYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $olderPeriod = FinancialPeriod::create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $newerPeriod = FinancialPeriod::create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 2,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'closed',
        ]);

        $periods = $periodOptions->all()->all();

        $this->assertSame($newerPeriod->id, $periods[0]['id']);
        $this->assertSame($olderPeriod->id, $periods[1]['id']);
        $this->assertSame(2026, $periods[0]['year']);
        $this->assertSame(2, $periods[0]['month']);
        $this->assertSame('2026-02-01', $periods[0]['start_date']);
        $this->assertSame('2026-02-28', $periods[0]['end_date']);
        $this->assertSame('closed', $periods[0]['status']);

        foreach ([
            app_path('Http/Controllers/Reports/IncomeStatementReportController.php'),
            app_path('Http/Controllers/Reports/CashFlowReportController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString('FinancialPeriodReportOptions', $source);

            foreach (['FinancialPeriod::query(', "with('fiscalYear')", 'map(fn (FinancialPeriod'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate financial-period option composition.");
            }
        }
    }

    public function test_trial_balance_controller_uses_shared_financial_period_report_options(): void
    {
        $this->assertInstanceOf(FinancialPeriodReportOptions::class, app(FinancialPeriodReportOptions::class));

        $source = (string) file_get_contents(app_path('Http/Controllers/Accounting/TrialBalanceController.php'));

        $this->assertStringContainsString('FinancialPeriodReportOptions', $source);
        $this->assertStringContainsString("'periods' => \$this->periodOptions->all()", $source);

        foreach (['FinancialPeriod::query(', "with('fiscalYear')", 'use App\\Models\\FinancialPeriod;'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'TrialBalanceController must delegate financial-period option composition.');
        }
    }

    public function test_fixed_asset_controller_delegates_page_data_composition(): void
    {
        $this->assertInstanceOf(FixedAssetPageData::class, app(FixedAssetPageData::class));

        $source = (string) file_get_contents(app_path('Http/Controllers/FixedAssets/FixedAssetController.php'));

        foreach (['Branch::query(', 'Currency::query(', 'FixedAssetLocation::query(', 'listForEntity(', "'view_financials' => \$request->user()?->can"] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_sales_and_purchasing_document_controllers_delegate_page_data_queries(): void
    {
        foreach ([CustomerInvoicePageData::class, SupplierBillPageData::class, PurchaseReturnPageData::class, SupplierAdjustmentNotePageData::class] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        foreach ([
            app_path('Http/Controllers/CustomerInvoiceController.php') => ['CustomerInvoice::query(', 'Product::query(', 'TaxCode::query(', 'SalesOrder::query('],
            app_path('Http/Controllers/SupplierBillController.php') => ['SupplierBill::query(', 'Product::query(', 'TaxCode::query(', 'PurchaseOrder::query('],
            app_path('Http/Controllers/PurchaseReturnController.php') => ['PurchaseReturn::query(', 'GoodsReceipt::query(', 'TaxCode::query(', 'Warehouse::query('],
            app_path('Http/Controllers/SupplierAdjustmentNoteController.php') => ['SupplierAdjustmentNote::query(', 'SupplierBill::query(', 'PurchaseReturn::query(', 'TaxCode::query('],
        ] as $path => $forbiddenFragments) {
            $source = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must delegate page data queries.");
            }
        }
    }

    public function test_numbering_settings_controller_delegates_sequence_persistence(): void
    {
        $this->assertInstanceOf(NumberingSettingsService::class, app(NumberingSettingsService::class));

        $source = (string) file_get_contents(app_path('Http/Controllers/Settings/NumberingSettingsController.php'));

        foreach (['DB::table(', 'Str::uuid(', 'numberingPayload(', 'previewNumber('] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_company_settings_controller_delegates_page_and_persistence_logic(): void
    {
        $this->assertInstanceOf(CompanySettingsService::class, app(CompanySettingsService::class));

        $source = (string) file_get_contents(app_path('Http/Controllers/Settings/CompanySettingsController.php'));

        foreach (['Currency::query(', 'Company::query(', 'DB::table(', 'Str::uuid(', 'settingsArray(', 'companyNameJson(', 'OptimisticLock'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_user_settings_controller_delegates_user_persistence_and_listing(): void
    {
        $this->assertInstanceOf(UserSettingsService::class, app(UserSettingsService::class));

        $source = (string) file_get_contents(app_path('Http/Controllers/Settings/UserSettingsController.php'));

        foreach (['User::query(', 'Role::query(', 'Permission::query(', 'Hash::make(', 'syncRequestedRole(', 'SuperAdminProtection'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }
    }

    public function test_rental_controllers_delegate_page_data_and_csv_composition(): void
    {
        $this->assertInstanceOf(RentalInvoicePageData::class, app(RentalInvoicePageData::class));
        $this->assertInstanceOf(RentalOperationsReportPageData::class, app(RentalOperationsReportPageData::class));
        $this->assertInstanceOf(RentalOperationsCsvExporter::class, app(RentalOperationsCsvExporter::class));

        foreach ([
            app_path('Http/Controllers/RentalInvoiceController.php') => ['RentalInvoice::query(', 'RentalContract::query(', 'Currency::query(', 'TaxCode::query('],
            app_path('Http/Controllers/Reports/RentalOperationsReportController.php') => ['Branch::query(', 'Customer::query(', 'Currency::query(', 'fputcsv(', 'localizedExportName('],
        ] as $path => $forbiddenFragments) {
            $source = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_remaining_large_controllers_delegate_read_side_page_data(): void
    {
        $this->assertInstanceOf(BankReconciliationPageData::class, app(BankReconciliationPageData::class));
        $this->assertInstanceOf(FixedAssetDepreciationRunPageData::class, app(FixedAssetDepreciationRunPageData::class));
        $this->assertInstanceOf(GeneralLedgerPageData::class, app(GeneralLedgerPageData::class));

        foreach ([
            app_path('Http/Controllers/BankReconciliationController.php') => ['BankReconciliation::query(', 'BankAccount::query(', 'FinancialPeriod::query(', 'Currency::query('],
            app_path('Http/Controllers/FixedAssets/FixedAssetDepreciationRunController.php') => ['FixedAssetDepreciationRun::query(', 'FixedAssetDepreciationSchedule::query(', 'FinancialPeriod::query('],
            app_path('Http/Controllers/Accounting/GeneralLedgerController.php') => ['GeneralLedgerService', 'Account::query(', 'Branch::query(', 'FinancialPeriod::query('],
        ] as $path => $forbiddenFragments) {
            $source = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $source);
            }
        }
    }

    public function test_settings_and_audit_controllers_delegate_query_and_persistence_work(): void
    {
        foreach ([
            BranchSettingsService::class,
            BranchApprovalRuleService::class,
            RoleSettingsService::class,
            UserRoleAssignmentService::class,
            AuditLogQueryService::class,
        ] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, app($serviceClass));
        }

        foreach ([
            app_path('Http/Controllers/Settings/BranchSettingsController.php') => [
                'required' => ['BranchSettingsService', 'indexData(', '->create(', '->update(', '->delete('],
                'forbidden' => ['Branch::query(', 'DB::table(', 'AuditLogger', 'OptimisticLock', 'ResolvesLocalizedModelFields'],
            ],
            app_path('Http/Controllers/Settings/BranchApprovalRuleController.php') => [
                'required' => ['BranchApprovalRuleService', 'indexData('],
                'forbidden' => ['BranchApprovalRule::query(', 'Branch::query('],
            ],
            app_path('Http/Controllers/Settings/RoleSettingsController.php') => [
                'required' => ['RoleSettingsService', '->create(', '->update(', '->delete('],
                'forbidden' => ['Role::query(', 'Role::create(', 'AuditLogger'],
            ],
            app_path('Http/Controllers/Settings/UserRoleAssignmentController.php') => [
                'required' => ['UserRoleAssignmentService', '->assign(', '->revoke('],
                'forbidden' => ['User::query(', 'Role::query(', 'NotificationService', 'AuditLogger', 'SuperAdminProtection'],
            ],
            app_path('Http/Controllers/AuditLogController.php') => [
                'required' => ['AuditLogQueryService', 'pageData('],
                'forbidden' => ['User::query(', 'getAvailableActions(', 'getAvailableEntityTypes('],
            ],
        ] as $path => $expectations) {
            $source = (string) file_get_contents($path);

            foreach ($expectations['required'] as $fragment) {
                $this->assertStringContainsString($fragment, $source, "{$path} must delegate to its application service.");
            }

            foreach ($expectations['forbidden'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} must not keep query or persistence logic inline.");
            }
        }

        $controllerFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Controllers')),
        );

        foreach ($controllerFiles as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            $this->assertStringNotContainsString('DB::table(', $source, "{$file->getPathname()} must not perform table queries directly.");
            $this->assertStringNotContainsString('::query(', $source, "{$file->getPathname()} must not compose read-side queries directly.");
        }
    }

    public function test_sales_and_purchasing_flash_messages_are_localized(): void
    {
        $controllers = [
            app_path('Http/Controllers/SalesOrderController.php'),
            app_path('Http/Controllers/PurchaseOrderController.php'),
            app_path('Http/Controllers/DeliveryNoteController.php'),
            app_path('Http/Controllers/GoodsReceiptController.php'),
            app_path('Http/Controllers/CustomerInvoiceController.php'),
            app_path('Http/Controllers/SupplierBillController.php'),
            app_path('Http/Controllers/SalesReturnController.php'),
            app_path('Http/Controllers/PurchaseReturnController.php'),
            app_path('Http/Controllers/CustomerCreditNoteController.php'),
            app_path('Http/Controllers/SupplierAdjustmentNoteController.php'),
        ];

        foreach ($controllers as $controller) {
            $source = (string) file_get_contents($controller);

            $this->assertStringNotContainsString("->with('success', '", $source, "{$controller} contains a raw success flash message.");
        }

        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->salesPurchasingFlashMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic backend flash translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic backend flash translation still mirrors English for [{$message}].");
        }
    }

    public function test_controller_success_flash_messages_are_translation_backed(): void
    {
        $controllerFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Controllers')),
        );

        foreach ($controllerFiles as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            $this->assertStringNotContainsString("->with('success', '", $source, "{$file->getPathname()} contains a raw success flash message.");
            $this->assertStringNotContainsString("->with('success', \"", $source, "{$file->getPathname()} contains a raw success flash message.");
        }

        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->remainingControllerFlashMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic backend flash translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic backend flash translation still mirrors English for [{$message}].");
        }
    }

    public function test_backend_guard_error_messages_have_arabic_translations(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->backendGuardErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic backend guard translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic backend guard translation still mirrors English for [{$message}].");
        }
    }

    public function test_financial_service_error_messages_use_translation_placeholders(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->financialServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic financial service translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic financial service translation still mirrors English for [{$message}].");
        }

        foreach ($this->financialServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                'cannot be posted from status [',
                'cannot be reversed from status [',
                'is already reversed."]',
                'Financial period [{$data[\'financial_period_id\']}] does not exist.',
                'Financial period {$periodId} does not exist.',
                'Target financial period {$periodId} is closed or locked.',
                'Posting date {$pDate} is outside target financial period bounds',
                'No financial period covers posting date {$pDate}.',
                'No financial period covers date {$normalized}.',
                'Cash Account code [',
                'Bank Account code [',
                'GL Account [',
                'Currency [',
                'Branch [',
                'must match receipt currency [',
                'must match payment currency [',
                'Field [',
                'Slice 2 opening balances currently require 1:1 FX rate',
                'Slice 3 receipts currently require 1:1 FX rate',
                'Slice 3 payments currently require 1:1 FX rate',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw financial service error message.");
            }
        }
    }

    public function test_branch_approval_rule_error_messages_are_translation_backed(): void
    {
        $source = (string) file_get_contents(app_path('Application/Approvals/BranchApprovalRuleService.php'));

        foreach ([
            'requires permission [',
            "=> ['Selected branch does not exist.']",
            "=> ['Unsupported approval document type.']",
            "=> ['Unsupported branch match mode.']",
            "=> ['This document type uses document branch matching only.']",
            "=> ['Selected permission does not exist.']",
            "=> ['An approval rule already exists for this document, branch match, and branch scope.']",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Branch approval rule errors must be translation-backed.');
        }

        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->branchApprovalRuleErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic branch approval translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic branch approval translation still mirrors English for [{$message}].");
        }
    }

    public function test_tax_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->taxServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic tax service translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic tax service translation still mirrors English for [{$message}].");
        }

        foreach ($this->taxServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Tax period label is required.']",
                "=> ['Start date and end date are required.']",
                "=> ['End date must be greater than or equal to start date.']",
                'Tax period dates ({$startDate} to {$endDate}) overlap with an existing tax period.',
                "=> ['Cannot generate draft tax return for a filed tax period.']",
                "=> ['Tax period is already filed.']",
                'Tax code [{$code}] already exists.',
                'Tax code [{$newCode}] already exists.',
                "=> ['System tax codes cannot have their code changed.']",
                "=> ['Invalid calculation mode.']",
                "=> ['Invalid recoverability mode.']",
                "=> ['System tax codes cannot be deleted.']",
                "=> ['Cannot delete tax code that has rates configured.']",
                "=> ['Tax rate basis points must be a non-negative integer.']",
                "=> ['Effective to date cannot be before effective from date.']",
                'Tax-affecting postings are blocked because tax period \'{$filedPeriod->period_label}\'',
                'Tax code [{$taxCode->code}] is inactive.',
                'No active tax rate found for tax code [{$taxCode->code}] on date [{$documentDate}].',
                'Unsupported calculation mode [{$taxCode->calculation_mode}].',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw tax service error message.");
            }
        }
    }

    public function test_expense_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->expenseServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic expense service translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic expense service translation still mirrors English for [{$message}].");
        }

        foreach ($this->expenseServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Expense date is required.']",
                "=> ['Only draft expenses can be updated.']",
                "=> ['Posting an expense requires an authenticated actor.']",
                "=> ['Only approved expenses can be posted.']",
                "=> ['Cannot post an expense without lines.']",
                "=> ['Only unposted expenses can be cancelled.']",
                'Expense must be {$from} before it can move to {$to}.',
                'Line {$lineIndex} category is required.',
                'Line {$lineIndex} category must be active.',
                'Line {$lineIndex} expense account is required.',
                'Line {$lineIndex} account must be an active debit expense account and not a control account.',
                'Line {$lineIndex} quantity must be greater than zero.',
                'Line {$lineIndex} unit amount must be greater than zero.',
                "=> ['Settlement method must be payable, cash, or bank.']",
                "=> ['Payable expenses require an active supplier.']",
                "=> ['Cash settlement requires an active cash account.']",
                "=> ['Bank settlement requires an active bank account.']",
                "=> ['Settlement account currency must match expense currency.']",
                "=> ['At least one attachment is required before posting this expense.']",
                'No open financial period covers date {$date}.',
                'Line {$lineIndex} amount exceeds maximum allowable integer limit.',
                'Line {$lineIndex} quantity and unit amount result in fractional minor units.',
                'Currency [{$code}] does not exist.',
                '{$label} currency must match expense currency.',
                "=> ['Expense categories already used on expenses cannot be deleted.']",
                "=> ['Expense categories already used on schedules cannot be deleted.']",
                'Expense category code [{$code}] already exists.',
                "=> ['Default expense account must be an active debit expense account and not a control account.']",
                "=> ['Default tax code must be active.']",
                "=> ['Only draft prepaid schedules can be updated.']",
                "=> ['Prepaid schedule must be submitted before approval.']",
                "=> ['Prepaid schedule requires recognition rows.']",
                "=> ['Only unposted prepaid schedules can be cancelled.']",
                "=> ['Posting prepaid recognition requires an authenticated actor.']",
                'Prepaid schedule must be {$from} before it can move to {$to}.',
                "=> ['Only draft accrual schedules can be updated.']",
                "=> ['Accrual schedule must be submitted before approval.']",
                "=> ['Accrual schedule requires entry rows.']",
                "=> ['Only unposted accrual schedules can be cancelled.']",
                "=> ['Posting accrual entries requires an authenticated actor.']",
                'Accrual schedule must be {$from} before it can move to {$to}.',
                '{$label} currency must match the schedule currency.',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw expense service error message.");
            }
        }
    }

    public function test_payroll_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->payrollServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic payroll service translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic payroll service translation still mirrors English for [{$message}].");
        }

        foreach ($this->payrollServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Only draft payroll runs can be regenerated.']",
                "=> ['Payroll run must be submitted before approval.']",
                "=> ['Only unposted payroll runs can be cancelled.']",
                "=> ['Posting payroll requires an authenticated actor.']",
                "=> ['Only approved payroll runs can be posted.']",
                "=> ['Payroll year must be between 2000 and 2100.']",
                "=> ['Payroll month must be between 1 and 12.']",
                "=> ['Payroll period is locked.']",
                'Deductions exceed gross pay for employee {$employee->code}.',
                "=> ['No active employees matched this payroll run.']",
                "=> ['Payroll posting is not balanced.']",
                'Payroll run must be {$from} before it can move to {$to}.',
                "=> ['Payroll run has no payable lines.']",
                "=> ['Invalid branch reference.']",
                "=> ['Invalid payroll run type.']",
                '{$label} currency must match payroll currency.',
                "=> ['The component was modified by another user. Please refresh and try again.']",
                "=> ['System payroll components cannot be deleted.']",
                "=> ['Payroll component is assigned to employees and cannot be deleted.']",
                "=> ['Component code is required and may contain letters, numbers, dots, underscores, or dashes.']",
                "=> ['Component code already exists.']",
                "=> ['Invalid component type.']",
                "=> ['Invalid calculation type.']",
                "=> ['Default amount cannot be negative.']",
                "=> ['Percent-based component requires a rate between 0 and 1000000 basis points.']",
                "=> ['Liability account must be an active credit liability account.']",
                "=> ['English component name is required.']",
                "=> ['The employee was modified by another user. Please refresh and try again.']",
                "=> ['Employee code is required and may contain letters, numbers, dots, underscores, or dashes.']",
                "=> ['Employee code already exists.']",
                "=> ['Invalid employee status.']",
                "=> ['Hire date is required.']",
                "=> ['Termination date cannot be before hire date.']",
                "=> ['Base salary cannot be negative.']",
                "=> ['Invalid payment method.']",
                "=> ['English employee name is required.']",
                "=> ['Selected payroll component is inactive or missing.']",
                "=> ['Amount cannot be negative.']",
                "=> ['Rate must be between 0 and 1000000 basis points.']",
                "=> ['Effective start date is required.']",
                "=> ['Effective end date cannot be before effective start date.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw payroll service error message.");
            }
        }
    }

    public function test_inventory_workflow_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->inventoryWorkflowServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic inventory workflow translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic inventory workflow translation still mirrors English for [{$message}].");
        }

        foreach ($this->inventoryWorkflowServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                'Warehouse code [{$code}] already exists.',
                "=> ['Default warehouse cannot be deleted.']",
                "=> ['Warehouse has stock balances and cannot be deleted.']",
                "=> ['Warehouse is used by stock transfers and cannot be deleted.']",
                'Location code [{$code}] already exists in the selected warehouse.',
                "=> ['Selected warehouse is invalid or inactive.']",
                "=> ['Selected branch is invalid or inactive.']",
                "=> ['Name is required.']",
                "=> ['Invalid warehouse type.']",
                "=> ['Invalid stock location type.']",
                "=> ['Code is required.']",
                "=> ['Default warehouse is inactive.']",
                "=> ['Only draft stock counts can be updated.']",
                "=> ['Only draft or submitted stock counts can be approved.']",
                "=> ['Stock count must have at least one line.']",
                "=> ['Only approved stock counts can be posted.']",
                'Stock count cannot move from [{$count->status}] to [{$targetStatus}].',
                "=> ['Count date is required.']",
                "=> ['Product is already listed in this count.']",
                "=> ['Quantities must be greater than or equal to zero.']",
                "=> ['Unit cost must be greater than zero when provided.']",
                "=> ['Selected product must be an active stock item.']",
                "=> ['Only draft stock transfers can be updated.']",
                "=> ['Only draft or submitted stock transfers can be approved.']",
                "=> ['Stock transfer must have at least one line.']",
                "=> ['Only approved stock transfers can be issued.']",
                "=> ['Only issued stock transfers can be received.']",
                'Stock transfer cannot move from [{$transfer->status}] to [{$targetStatus}].',
                "=> ['Destination warehouse must be different from source warehouse.']",
                "=> ['Transfer date is required.']",
                "=> ['Selected unit of measure is invalid for this product.']",
                "=> ['Transfer quantity must be greater than zero.']",
                "=> ['Receipt quantity cannot exceed issued remaining quantity.']",
                "=> ['Transfer value allocation is too small for partial receipt. Receive the remaining quantity together.']",
                "=> ['No stock valuation currency exists for this product.']",
                "=> ['Only draft stock adjustments can be updated.']",
                "=> ['Only draft or submitted stock adjustments can be approved.']",
                "=> ['Stock adjustment must have at least one line.']",
                "=> ['Only approved stock adjustments can be posted.']",
                'Stock adjustment cannot move from [{$adjustment->status}] to [{$targetStatus}].',
                "=> ['Adjustment date is required.']",
                "=> ['Adjustment quantity delta must not be zero.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw inventory workflow service error message.");
            }
        }
    }

    public function test_inventory_costing_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->inventoryCostingServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic inventory costing translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic inventory costing translation still mirrors English for [{$message}].");
        }

        foreach ($this->inventoryCostingServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Receipt quantity must be greater than zero.']",
                "=> ['Receipt unit cost must be greater than zero.']",
                "=> ['Mapped GL account currencies must match movement currency.']",
                'Stock balance already exists for this product in currency [{$otherCurrencyBalance->currency}].',
                "=> ['Issue quantity must be greater than zero.']",
                'Insufficient stock balance for product. Available: {$wholeAvail}.{$fracAvail}.',
                "=> ['Quantity and unit cost result in fractional minor units.']",
                "=> ['Inventory calculation exceeds supported integer range.']",
                "=> ['Return quantity must be greater than zero.']",
                "=> ['Return unit cost must be greater than zero.']",
                "=> ['Scrap quantity must be greater than zero.']",
                'Insufficient stock balance for scrap. Available: {$wholeAvail}.{$fracAvail}.',
                'Insufficient source warehouse stock. Available: {$wholeAvail}.{$fracAvail}.',
                "=> ['Transfer receipt quantity must be greater than zero.']",
                "=> ['Transfer receipt value must be greater than zero.']",
                "=> ['Positive stock adjustments require a positive unit cost.']",
                'Insufficient stock balance for adjustment. Available: {$wholeAvail}.{$fracAvail}.',
                'No original {$movementType} movement found for source line [{$sourceLineId}].',
                "=> ['Return quantity cannot exceed original quantity.']",
                "=> ['Landed cost value must be greater than zero.']",
                "=> ['Landed cost can only be capitalized while stock remains in the target warehouse.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw inventory costing service error message.");
            }
        }
    }

    public function test_rental_item_and_contract_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->rentalItemAndContractServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic rental item/contract translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic rental item/contract translation still mirrors English for [{$message}].");
        }

        foreach ($this->rentalItemAndContractServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['The rentable item was modified by another user. Please refresh and try again.']",
                "=> ['Rentable items in active rental workflow states cannot be deleted.']",
                "=> ['Rentable item code is required and may contain letters, numbers, dots, underscores, or dashes.']",
                "=> ['Rentable item code already exists.']",
                "=> ['Invalid rentable item source.']",
                "=> ['Invalid rentable item status.']",
                "=> ['Invalid rentable item condition.']",
                "=> ['Selected product is inactive or missing.']",
                "=> ['Disposed fixed assets cannot be used as rentable items.']",
                "=> ['Selected fixed asset is missing.']",
                "=> ['Selected warehouse is inactive or missing.']",
                "=> ['Selected warehouse belongs to a different operational branch.']",
                "=> ['Standalone rentable items cannot be linked to a product or fixed asset.']",
                "=> ['Product-sourced rentable items must reference exactly one product.']",
                "=> ['Fixed-asset-sourced rentable items must reference exactly one fixed asset.']",
                "=> ['English rentable item name is required.']",
                "=> ['The rental contract was modified by another user. Please refresh and try again.']",
                "=> ['Only draft rental contracts can be updated.']",
                "=> ['Only draft rental contracts can be submitted.']",
                "=> ['Only submitted rental contracts can be approved.']",
                "=> ['Only approved rental contracts can be activated.']",
                "=> ['Active or completed rental contracts require the return workflow instead of cancellation.']",
                "=> ['Selected customer is inactive or missing.']",
                "=> ['Expected end date cannot be before start date.']",
                "=> ['Invalid billing cycle.']",
                "=> ['Rental contract must have at least one line.']",
                "=> ['Invalid rental contract line.']",
                "=> ['A rentable item can appear only once on the same contract.']",
                "=> ['Selected rentable item is not available for reservation.']",
                "=> ['Rentable item currency must match contract currency.']",
                "=> ['Line dates must be within the rental contract date range.']",
                "=> ['Invalid rental rate type.']",
                "=> ['Estimated units must be at least 1.']",
                "=> ['Invalid or missing reference.']",
                "=> ['Invalid reference.']",
                "=> ['Date is required.']",
                "=> ['Invalid date.']",
                "=> ['Amount cannot be negative.']",
                "=> ['Calculated amount is too large.']",
                "=> ['Calculated total is too large.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw rental item/contract service error message.");
            }
        }
    }

    public function test_rental_fulfillment_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->rentalFulfillmentServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic rental fulfillment translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic rental fulfillment translation still mirrors English for [{$message}].");
        }

        foreach ($this->rentalFulfillmentServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Only approved or active rental contracts can be handed over.']",
                "=> ['Only draft handovers can be confirmed.']",
                "=> ['Only draft handovers can be cancelled.']",
                "=> ['Only active rental contracts can receive returns.']",
                "=> ['Only draft returns can be submitted.']",
                "=> ['Only submitted returns can be completed after inspection.']",
                "=> ['Only active rental contracts can be completed through return inspection.']",
                "=> ['Completed rental returns cannot be cancelled.']",
                "=> ['Handover must have at least one line.']",
                "=> ['Invalid handover line.']",
                "=> ['A contract line can be handed over only once per document.']",
                "=> ['Selected line does not belong to the rental contract.']",
                "=> ['Selected line was already handed over.']",
                "=> ['Only allocated or rented contract lines can be handed over.']",
                "=> ['Invalid handover condition.']",
                "=> ['Return must have at least one line.']",
                "=> ['Invalid return line.']",
                "=> ['A contract line can be returned only once per document.']",
                "=> ['Selected line is already on an open return document.']",
                "=> ['Only rented contract lines can be returned.']",
                "=> ['Invalid return condition.']",
                "=> ['Invalid return outcome.']",
                "=> ['Rental contract must have at least one line.']",
                "=> ['Invalid or missing reference.']",
                "=> ['Date is required.']",
                "=> ['Invalid date.']",
                "=> ['Amount cannot be negative.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw rental fulfillment service error message.");
            }
        }
    }

    public function test_rental_invoice_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->rentalInvoiceServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic rental invoice translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic rental invoice translation still mirrors English for [{$message}].");
        }

        foreach ($this->rentalInvoiceServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Only draft rental invoices can be updated.']",
                "=> ['The rental invoice was modified by another user. Please refresh and try again.']",
                "=> ['Only draft rental invoices can be submitted.']",
                "=> ['Rental invoice must have at least one line before submitting.']",
                "=> ['Only draft or submitted rental invoices can be approved.']",
                "=> ['Rental invoice total must be greater than zero before approval.']",
                "=> ['Only approved rental invoices can be posted.']",
                "=> ['Rental invoice must have at least one line before posting.']",
                "=> ['Rental invoice total must be greater than zero before posting.']",
                "=> ['Posted rental invoices require a credit/reversal workflow instead of cancellation.']",
                "=> ['Only approved, active, or completed rental contracts can be invoiced.']",
                "=> ['Invalid rental invoice type.']",
                "=> ['Rental invoice currency must match the rental contract currency.']",
                "=> ['Billing period end must be on or after billing period start.']",
                "=> ['Rental invoice total must be greater than zero.']",
                "=> ['Rental invoice must have at least one line.']",
                "=> ['Invalid rental invoice line.']",
                "=> ['Invalid rental invoice line type.']",
                "=> ['Selected contract line does not belong to the rental contract.']",
                "=> ['Rent and deposit lines must reference a rental contract line.']",
                "=> ['Rent lines require a billing period start and end.']",
                "=> ['Selected return line does not belong to the rental contract.']",
                "=> ['Return line charges require a completed rental return.']",
                "=> ['Return line must match the selected contract line.']",
                "=> ['Damage charge lines must reference a completed rental return line.']",
                "=> ['Duplicate rental invoice line source in the same document.']",
                "=> ['Quantity must be greater than zero.']",
                "=> ['Unit amount cannot be negative.']",
                "=> ['Line amount must be greater than zero.']",
                "=> ['This rental line has already been invoiced for the selected billing period.']",
                "=> ['Deposit invoice amount exceeds the remaining contract-line deposit.']",
                "=> ['Damage charge exceeds the remaining inspected damage amount.']",
                'Mapped account for [{$key}] uses {$account->currency}; rental invoice currency is {$invoice->currency}.',
                'No open financial period covers date {$date}.',
                "=> ['Line amount exceeds maximum allowable integer limit.']",
                "=> ['Line amount results in fractional minor currency units.']",
                "=> ['A valid identifier is required.']",
                "=> ['Date is required.']",
                "=> ['Invalid date.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw rental invoice service error message.");
            }
        }
    }

    public function test_fixed_asset_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->fixedAssetServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic fixed asset translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic fixed asset translation still mirrors English for [{$message}].");
        }

        foreach ($this->fixedAssetServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                'Category code [{$code}] already exists.',
                "=> ['Useful life must be a positive number of months.']",
                "=> ['Salvage value cannot be negative.']",
                "=> ['Cannot delete category with linked assets.']",
                "=> ['Selected asset category is inactive.']",
                'Currency [{$currencyCode}] is missing.',
                "=> ['Asset cost must be greater than zero.']",
                "=> ['Salvage value cannot exceed historical cost.']",
                "=> ['Opening accumulated depreciation cannot be negative.']",
                "=> ['Opening accumulated depreciation cannot exceed depreciable base (Cost - Salvage).']",
                "=> ['Fixed assets must be created as draft and activated through capitalization.']",
                'Asset number [{$assetNumber}] is already in use.',
                "=> ['Only draft assets can be edited.']",
                "=> ['Fixed assets must be activated through capitalization.']",
                "=> ['Only draft assets can be deleted.']",
                "=> ['Assets with movement history cannot be deleted.']",
                'Invalid capitalization mode [{$mode}].',
                "=> ['Asset is already capitalized.']",
                "=> ['Only draft assets can be capitalized.']",
                "=> ['Only manually capitalized active assets can be reversed.']",
                "=> ['Only active fixed assets can have depreciation schedules.']",
                "=> ['Only straight-line depreciation is supported in Phase 6.']",
                "=> ['Asset useful life in months must be greater than zero.']",
                "=> ['No financial period is available for the depreciation schedule start date.']",
                'Insufficient fiscal periods configured. Required: [{$usefulLifeMonths}], Available: [{$periods->count()}].',
                "=> ['No planned active depreciation schedules found for this period.']",
                "=> ['Total depreciation amount for selected period must be greater than zero.']",
                "=> ['Depreciation runs must be posted one currency at a time.']",
                "=> ['Disposal has no linked journal entry to reverse.']",
                'Only active assets can be disposed. Current status: [{$asset->status}].',
                "=> ['Disposal type must be sale, scrap, or retirement.']",
                "=> ['Proceeds amount cannot be negative.']",
                "=> ['Cannot dispose an asset before already posted depreciation schedule periods. Reverse those depreciation runs first.']",
                "=> ['Prior depreciation schedule periods must be posted or resolved before disposal.']",
                "=> ['Locations used by assets or movement history cannot be deleted. Deactivate the location instead.']",
                "=> ['Disposed assets cannot be moved.']",
                "=> ['The selected destination matches the current asset position.']",
                "=> ['Selected location is inactive or missing.']",
                "=> ['Selected location belongs to a different branch.']",
                "=> ['Date is required.']",
                "=> ['Invalid date.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw fixed asset service error message.");
            }
        }
    }

    public function test_sales_invoice_and_supplier_bill_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->salesInvoiceAndSupplierBillServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic sales/purchasing invoice translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic sales/purchasing invoice translation still mirrors English for [{$message}].");
        }

        foreach ($this->salesInvoiceAndSupplierBillServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Customer is required.']",
                "=> ['Customer must be active.']",
                "=> ['Invoice date is required.']",
                "=> ['Customer invoice can reference either a Sales Order or a Delivery Note, not both.']",
                "=> ['Customer invoices can only reference confirmed Sales Orders.']",
                "=> ['Customer must match the Sales Order customer.']",
                "=> ['Currency must match the Sales Order currency.']",
                "=> ['Customer invoices can only reference confirmed Delivery Notes.']",
                "=> ['Customer must match the Delivery Note customer.']",
                "=> ['Currency must match the Delivery Note currency.']",
                "=> ['Only draft customer invoices can be updated.']",
                "=> ['Only draft customer invoices can be submitted.']",
                "=> ['Customer invoice must have at least one line item before submitting.']",
                "=> ['Only draft or submitted customer invoices can be approved.']",
                "=> ['Customer invoice must have at least one line item before approving.']",
                "=> ['Only approved customer invoices can be posted to AR/GL.']",
                "=> ['Customer invoice must have at least one line item before posting.']",
                "=> ['Stock product lines on customer invoices must be sourced from a Delivery Note.']",
                "=> ['Financial period is closed.']",
                "=> ['Financial period does not belong to the invoice fiscal year.']",
                "=> ['Invoice date must fall within the financial period.']",
                'Mapped GL account currency (AR: {$arAccount->currency}, Rev: {$revenueAccount->currency}) must match invoice currency ({$invoice->currency}).',
                'Mapped tax account currency ({$outputTaxAccount->currency}) must match invoice currency ({$invoice->currency}).',
                "=> ['Posted customer invoices cannot be cancelled in this slice.']",
                "=> ['At least one line item is required.']",
                'Line {$lineIndex} cannot reference both a Sales Order line and a Delivery Note line.',
                'Line {$lineIndex} references a Sales Order line but no Sales Order source was selected.',
                'Line {$lineIndex} references a Delivery Note line but no Delivery Note source was selected.',
                'Line {$lineIndex} must reference a Sales Order line.',
                'Line {$lineIndex} must reference a Delivery Note line.',
                'Product on line {$lineIndex} does not exist.',
                'Stock product [{$product->code}] must be sourced from a Delivery Note.',
                'Product [{$product->code}] is inactive or not enabled for sales.',
                'Quantity on line {$lineIndex} must be greater than zero.',
                'Unit price on line {$lineIndex} cannot be negative.',
                'Line {$lineIndex} does not belong to the selected Sales Order.',
                'Product on line {$lineIndex} must match the selected Sales Order line.',
                'Unit of measure on line {$lineIndex} must match the selected Sales Order line.',
                'Unit price on line {$lineIndex} must match the selected Sales Order line.',
                'Invoiced quantity on line {$lineIndex} exceeds remaining Sales Order quantity. Maximum remaining allowed is {$maxAllowedDecimal}.',
                'Line {$lineIndex} does not belong to the selected Delivery Note.',
                'Product on line {$lineIndex} must match the selected Delivery Note line.',
                'Unit of measure on line {$lineIndex} must match the selected Delivery Note line.',
                'Delivery Note line {$lineIndex} is not linked to a Sales Order line.',
                'Unit price on line {$lineIndex} must match the Delivery Note source Sales Order line.',
                'Invoiced quantity on line {$lineIndex} exceeds remaining Delivery Note quantity. Maximum remaining allowed is {$maxAllowedDecimal}.',
                'Unit of measure on line {$lineIndex} must match the selected product.',
                'Line {$lineIndex} total results in fractional minor currency units which is not permitted.',
                "=> ['Supplier is required.']",
                "=> ['Supplier must be active.']",
                "=> ['Bill date is required.']",
                "=> ['Supplier bill can reference either a Purchase Order or a Goods Receipt, not both.']",
                "=> ['Supplier bills can only reference confirmed Purchase Orders.']",
                "=> ['Supplier must match the Purchase Order supplier.']",
                "=> ['Currency must match the Purchase Order currency.']",
                "=> ['Supplier bills can only reference confirmed Goods Receipts.']",
                "=> ['Supplier must match the Goods Receipt supplier.']",
                "=> ['Currency must match the Goods Receipt currency.']",
                "=> ['Only draft supplier bills can be updated.']",
                "=> ['Only draft supplier bills can be submitted.']",
                "=> ['Only submitted supplier bills can be approved.']",
                "=> ['Only approved supplier bills can be posted.']",
                "=> ['Cannot post supplier bill without line items.']",
                "=> ['Stock product lines on supplier bills must be sourced from a Goods Receipt.']",
                "=> ['Financial period does not belong to the bill fiscal year.']",
                "=> ['Bill date must fall within the financial period.']",
                "=> ['Mapped Purchase Expense account currency must match bill currency.']",
                "=> ['Mapped GRNI Clearing account currency must match bill currency.']",
                "=> ['Mapped AP Control account currency must match bill currency.']",
                "=> ['Mapped Input Tax Receivable account currency must match bill currency.']",
                "=> ['Posted supplier bills cannot be cancelled in this slice.']",
                'Line {$lineIndex} cannot reference both a Purchase Order line and a Goods Receipt line.',
                'Line {$lineIndex} must reference a Purchase Order line for this Purchase Order bill.',
                'Line {$lineIndex} cannot reference a Goods Receipt line for a Purchase Order bill.',
                'Line {$lineIndex} must reference a Goods Receipt line for this Goods Receipt bill.',
                'Line {$lineIndex} cannot reference a Purchase Order line for a Goods Receipt bill.',
                'Line {$lineIndex} cannot reference a source line without a matching bill source header.',
                'Line {$lineIndex} product is required.',
                'Line {$lineIndex} product must be active and purchase-enabled.',
                'Line {$lineIndex} stock product must be sourced from a Goods Receipt.',
                'Line {$lineIndex} quantity must be greater than zero.',
                'Line {$lineIndex} unit cost cannot be negative.',
                'Line {$lineIndex} does not belong to the selected Purchase Order.',
                'Line {$lineIndex} product does not match Purchase Order line product.',
                'Line {$lineIndex} UOM does not match Purchase Order line UOM.',
                'Line {$lineIndex} unit cost must match the selected Purchase Order line.',
                'Line {$lineIndex} quantity exceeds remaining unbilled Purchase Order line quantity ({$whole}.{$frac}).',
                'Line {$lineIndex} does not belong to the selected Goods Receipt.',
                'Line {$lineIndex} product does not match Goods Receipt line product.',
                'Line {$lineIndex} UOM does not match Goods Receipt line UOM.',
                'Goods Receipt line {$lineIndex} is not linked to a Purchase Order line.',
                'Line {$lineIndex} unit cost must match the Goods Receipt source Purchase Order line.',
                'Line {$lineIndex} quantity exceeds remaining unbilled Goods Receipt line quantity ({$whole}.{$frac}).',
                'Line {$lineIndex} stock product bill unit cost must match Goods Receipt source unit cost.',
                'Line {$lineIndex} quantity and unit cost result in fractional minor units.',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw sales/purchasing invoice service error message.");
            }
        }
    }

    public function test_returns_and_adjustment_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->returnsAndAdjustmentServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic returns/adjustment translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic returns/adjustment translation still mirrors English for [{$message}].");
        }

        foreach ($this->returnsAndAdjustmentServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Delivery Note is required.']",
                "=> ['Sales returns can only reference confirmed Delivery Notes.']",
                "=> ['Return date is required.']",
                "=> ['Customer Invoice must belong to this customer.']",
                "=> ['Only draft sales returns can be updated.']",
                "=> ['Only draft sales returns can be submitted.']",
                "=> ['Sales return must have at least one line item before submitting.']",
                "=> ['Only draft or submitted sales returns can be approved.']",
                "=> ['Posted sales returns cannot be cancelled.']",
                "=> ['Only approved sales returns can be posted.']",
                "=> ['Sales return must have at least one line item before posting.']",
                "=> ['Financial period does not belong to the sales return fiscal year.']",
                "=> ['Customer Invoice must belong to the Delivery Note customer.']",
                'No open financial period covers date {$date}.',
                'Line {$lineIndex} must reference a Delivery Note line.',
                'Product [{$product->code}] is inactive.',
                'Disposition on line {$lineIndex} must be one of: ',
                'Manual restock value on line {$lineIndex} is required and must be >= 0.',
                'Returned quantity on line {$lineIndex} exceeds remaining Delivery Note line quantity. Maximum remaining allowed is {$whole}.{$fraction}.',
                'Customer Invoice line on line {$lineIndex} does not belong to the selected Customer Invoice.',
                "=> ['Only draft customer credit notes can be updated.']",
                "=> ['Only draft customer credit notes can be submitted.']",
                "=> ['Customer credit note must have at least one line item before submitting.']",
                "=> ['Only draft or submitted customer credit notes can be approved.']",
                "=> ['Posted customer credit notes cannot be cancelled in this slice.']",
                "=> ['Only approved customer credit notes can be posted to AR/GL.']",
                "=> ['Financial period does not belong to the credit note fiscal year.']",
                "=> ['Mapped GL account currency must match credit note currency.']",
                "=> ['Credit date is required.']",
                "=> ['Referenced Customer Invoice must be posted.']",
                "=> ['Sales Return must belong to this customer.']",
                "'Tax mode must be one of: '.implode(', ', self::ALLOWED_TAX_MODES).'.'",
                'Description on line {$lineIndex} is required.',
                'Credited quantity on line {$lineIndex} exceeds remaining invoiced quantity. Maximum remaining allowed is {$whole}.{$fraction}.',
                "=> ['Goods Receipt is required.']",
                "=> ['Purchase Returns can only be created for confirmed Goods Receipts.']",
                "=> ['Supplier Bill does not belong to the selected supplier.']",
                "=> ['Only draft purchase returns can be updated.']",
                "=> ['Only draft purchase returns can be submitted.']",
                "=> ['Purchase return must have at least one line item before submitting.']",
                "=> ['Only draft or submitted purchase returns can be approved.']",
                "=> ['Posted purchase returns cannot be cancelled in this slice.']",
                "=> ['Only approved purchase returns can be posted.']",
                "=> ['Financial period does not belong to the return fiscal year.']",
                "=> ['Mapped GL account currencies must match return currency.']",
                'Insufficient stock balance for product. Available: {$wholeAvail}.{$fracAvail}.',
                'Line {$lineIndex} must reference a Goods Receipt line.',
                'Returned quantity on line {$lineIndex} exceeds remaining Goods Receipt line quantity. Maximum remaining allowed is {$maxAllowedDecimal}.',
                "=> ['Adjustment date is required.']",
                "=> ['Only draft supplier adjustment notes can be updated.']",
                "=> ['Only draft supplier adjustment notes can be submitted.']",
                "=> ['Supplier adjustment note must have at least one line item before submitting.']",
                "=> ['Only draft or submitted supplier adjustment notes can be approved.']",
                "=> ['Posted supplier adjustment notes cannot be cancelled in this slice.']",
                "=> ['Only approved supplier adjustment notes can be posted.']",
                "=> ['Financial period does not belong to the note fiscal year.']",
                "=> ['Cannot post a supplier adjustment note with a zero or negative total.']",
                "=> ['Mapped AP Control account currency must match note currency.']",
                "=> ['Mapped contra account currency must match note currency.']",
                "=> ['Mapped Input Tax Receivable account currency must match note currency.']",
                "'Direction must be one of: '.implode(', ', self::ALLOWED_DIRECTIONS).'.'",
                "=> ['Tax rate cannot be negative.']",
                "=> ['Manual tax amount cannot be negative.']",
                "=> ['Supplier Bill must be posted.']",
                'Line {$lineIndex} references a Purchase Return line but no Purchase Return was selected.',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw returns/adjustment service error message.");
            }
        }
    }

    public function test_order_and_fulfillment_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->orderAndFulfillmentServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic order/fulfillment translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic order/fulfillment translation still mirrors English for [{$message}].");
        }

        foreach ($this->orderAndFulfillmentServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                'Sales Order in status [{$salesOrder->status}] cannot be updated.',
                'Sales Order in status [{$salesOrder->status}] cannot be submitted.',
                'Sales Order in status [{$salesOrder->status}] cannot be confirmed.',
                "=> ['Sales Order must have at least one line before submission.']",
                "=> ['Sales Order must have at least one line before confirmation.']",
                "=> ['Confirmed Sales Orders cannot be cancelled in this slice.']",
                "=> ['Customer is required.']",
                "=> ['Selected Customer is invalid or inactive.']",
                "=> ['Currency is required.']",
                "=> ['Selected Currency is invalid.']",
                "=> ['Order date is required.']",
                "=> ['Expected delivery date must be on or after order date.']",
                "=> ['FX rate must be a positive integer.']",
                "=> ['At least one order line is required.']",
                'Product is required on line {$lineIndex}.',
                'Selected Product on line {$lineIndex} is invalid, inactive, or not sales-enabled.',
                'Selected Product on line {$lineIndex} is invalid, inactive, or not purchase-enabled.',
                'Unit of Measure on line {$lineIndex} is invalid or inactive.',
                'Unit of Measure on line {$lineIndex} must match product default UOM.',
                "'Quantity on line '.(\$lineIndex + 1).' must be greater than zero.'",
                "'Unit price on line '.(\$lineIndex + 1).' must be greater than zero.'",
                "'Quantity and unit price product exceeds maximum integer capacity on line '.(\$lineIndex + 1).'.'",
                "=> ['Line total produces a fractional minor unit and must be an exact integer minor amount.']",
                "=> ['Only draft purchase orders can be updated.']",
                "=> ['Only draft purchase orders can be submitted.']",
                "=> ['Cannot submit a purchase order without line items.']",
                "=> ['Only draft or submitted purchase orders can be confirmed.']",
                "=> ['Cannot confirm a purchase order without line items.']",
                "=> ['Confirmed purchase orders cannot be cancelled in this slice.']",
                "=> ['Supplier is required.']",
                "=> ['Selected Supplier is invalid or inactive.']",
                "=> ['Expected receipt date must be on or after order date.']",
                "=> ['Sales Order is required.']",
                "=> ['Delivery Notes can only be created for confirmed Sales Orders.']",
                "=> ['Delivery date is required.']",
                "=> ['Only draft delivery notes can be updated.']",
                "=> ['Only draft delivery notes can be confirmed.']",
                "=> ['Cannot confirm a delivery note without line items.']",
                "=> ['Confirmed delivery notes cannot be cancelled in this slice.']",
                'Delivered quantity on line {$lineIndex} exceeds remaining Sales Order quantity. Maximum remaining allowed is {$maxAllowedDecimal}.',
                "=> ['Purchase Order is required.']",
                "=> ['Goods Receipts can only be created for confirmed Purchase Orders.']",
                "=> ['Receipt date is required.']",
                "=> ['Only draft goods receipts can be updated.']",
                "=> ['Only draft goods receipts can be confirmed.']",
                "=> ['Cannot confirm a goods receipt without line items.']",
                "=> ['Confirmed goods receipts cannot be cancelled in this slice.']",
                'Received quantity on line {$lineIndex} exceeds remaining Purchase Order quantity. Maximum remaining allowed is {$maxAllowedDecimal}.',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw order/fulfillment service error message.");
            }
        }
    }

    public function test_catalog_customer_supplier_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->catalogCustomerSupplierServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic catalog/master-data translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic catalog/master-data translation still mirrors English for [{$message}].");
        }

        foreach ($this->catalogCustomerSupplierServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Product code / SKU is required.']",
                'Product code / SKU [{$code}] already exists.',
                'Invalid product type [{$type}]. Allowed types: ',
                'Invalid product status [{$status}]. Allowed statuses: ',
                "=> ['Unit of Measure is required.']",
                "=> ['Selected Unit of Measure is invalid or inactive.']",
                "=> ['Selected Product Category is invalid or inactive.']",
                "=> ['Product category code is required.']",
                'Product category code [{$code}] already exists.',
                'Cannot delete Product Category [{$category->code}] because it is referenced by existing products.',
                "=> ['Unit of measure code is required.']",
                'Unit of measure code [{$code}] already exists.',
                'Cannot delete Unit of Measure [{$uom->code}] because it is referenced by existing products.',
                "Customer code [{\$data['code']}] already exists.",
                "=> ['Customer status must be active or inactive.']",
                "Supplier code [{\$data['code']}] already exists.",
                "=> ['Supplier status must be active or inactive.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw catalog/master-data service error message.");
            }
        }
    }

    public function test_accounting_mapping_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->accountingMappingServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic accounting mapping translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic accounting mapping translation still mirrors English for [{$message}].");
        }

        foreach ($this->accountingMappingServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                'Required accounting mapping [{$key}] is missing. Please configure it in Chart of Accounts settings.',
                'Mapped account for [{$key}] is inactive or missing.',
                "=> ['Global accounting mappings cannot be deleted. Update the mapped account instead.']",
                'Mapping key [{$key}] is not allowed.',
                'Branch [{$branchId}] does not exist.',
                'Mapping [{$key}] requires account type [',
                'Mapping [{$key}] requires account nature [{$expectedNature}].',
                "=> ['Statement line code is required.']",
                "=> ['Statement line code must be unique.']",
                "=> ['Statement type must be balance_sheet or income_statement.']",
                "=> ['Section code is required.']",
                "=> ['Normal balance must be debit or credit.']",
                "=> ['Name is required in at least one locale.']",
                "=> ['Cash flow activity must be operating, investing, or financing.']",
                "=> ['System statement line code cannot be changed.']",
                "=> ['System statement line statement type cannot be changed.']",
                "=> ['Cannot change statement type when line has assigned accounts.']",
                "=> ['System financial statement lines cannot be deleted.']",
                "=> ['Cannot delete financial statement line that has assigned accounts.']",
                "=> ['Financial statement line does not exist.']",
                "=> ['Cannot assign account to an inactive financial statement line.']",
                'Statement line statement type ({$line->statement_type}) does not match account statement type ({$expectedType}).',
                "=> ['Cash and bank GL accounts are classified through their non-cash journal counterparties.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw accounting mapping service error message.");
            }
        }
    }

    public function test_bank_reconciliation_and_cheque_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->bankReconciliationAndChequeServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic bank/cheque translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic bank/cheque translation still mirrors English for [{$message}].");
        }

        foreach ($this->bankReconciliationAndChequeServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Cash account does not have a linked GL account.']",
                "=> ['Bank account does not have a linked GL account.']",
                "=> ['Physical cheque number is required.']",
                "=> ['Amount must be a positive integer.']",
                'Cannot receive cheque from status [{$cheque->status}]. Only draft cheques can be received.',
                'Cannot deposit cheque from status [{$cheque->status}]. Only received cheques can be deposited.',
                "=> ['Selected deposit bank account is inactive.']",
                'Deposit bank account currency [{$bankAccount->currency}] does not match cheque currency [{$cheque->currency}].',
                'Cannot clear cheque from status [{$cheque->status}]. Only received or deposited cheques can be cleared.',
                'Cannot clear cheque from status [{$cheque->status}]. Only issued cheques can be cleared.',
                "=> ['Bank account must be specified to clear incoming cheque.']",
                "=> ['Selected bank account is inactive.']",
                "=> ['Bank account is inactive.']",
                'Bank account currency [{$bankAccount->currency}] does not match cheque currency',
                "=> ['Bank account GL account is inactive or currency mismatch.']",
                "InvalidArgumentException('OWNER DECISION REQUIRED: Post-clear bounce workflow is not implemented in pre-clear cheque lifecycle.",
                "InvalidArgumentException('OWNER DECISION REQUIRED: Post-clear return workflow is not implemented in pre-clear cheque lifecycle.",
                "InvalidArgumentException('OWNER DECISION REQUIRED: Post-clear cancel workflow is not implemented in pre-clear cheque lifecycle.",
                'Cannot bounce cheque from status [{$cheque->status}]. Only received or deposited pre-clear cheques can be bounced.',
                'Cannot return cheque from status [{$cheque->status}]. Only received or deposited pre-clear cheques can be returned.',
                'Cannot return cheque from status [{$cheque->status}]. Only issued pre-clear cheques can be returned.',
                'Cannot cancel cheque from status [{$cheque->status}]. Only issued pre-clear cheques can be cancelled.',
                'Cannot cancel cheque from status [{$cheque->status}]. Only draft cheques can be cancelled.',
                'Financial period is not open. Current status: [{$period->status}].',
                'Event date [{$eventDate}] is outside period range [{$startDate} - {$endDate}].',
                'Accounting mapping key [{$mappingKey}] is not configured.',
                'Mapped account [{$account->code}] for [{$mappingKey}] is inactive.',
                'Mapped account [{$account->code}] currency [{$account->currency}] does not match cheque currency [{$currency}].',
                'Mapped account [{$account->code}] type [{$account->type}] does not match expected [{$expectedType}].',
                'Mapped account [{$account->code}] nature [{$account->nature}] does not match expected [{$expectedNature}].',
                "=> ['Bank account, financial period, date from, and date to are required.']",
                "=> ['Date from must be prior to or equal to date to.']",
                'Reconciliation date range [{$dateFrom} - {$dateTo}] must fall within period range [{$startDate} - {$endDate}].',
                "=> ['Cannot modify lines on a reconciled bank reconciliation.']",
                'Statement line date [{$statementDate}] must be within reconciliation date range [{$dateFrom} - {$dateTo}].',
                "=> ['Exactly one of debit_minor or credit_minor must be greater than zero.']",
                "=> ['Unmatch statement line before modifying line details.']",
                "=> ['Cannot delete lines from a reconciled bank reconciliation.']",
                "=> ['Unmatch statement line before deleting.']",
                "=> ['Cannot match line on a reconciled bank reconciliation.']",
                "=> ['Statement line is already matched to another ledger entry. Unmatch first.']",
                "=> ['Ledger entry is already matched to another statement line.']",
                "=> ['Ledger entry GL account does not match bank account GL account.']",
                'Ledger entry currency [{$ledgerEntry->currency}] does not match reconciliation currency [{$recon->currency}].',
                'Ledger entry date [{$ledgerDate}] must be within reconciliation date range [{$dateFrom} - {$dateTo}].',
                'Statement line signed movement [{$lineSigned}] does not match ledger entry signed movement [{$ledgerSigned}].',
                "=> ['Cannot unmatch line on a reconciled bank reconciliation.']",
                "=> ['Statement self-check failed: statement opening + movement != closing balance.']",
                'Reconciliation contains [{$unmatchedLineCount}] unmatched statement line(s). All statement lines must be matched before finalization.',
                'Date range [{$dateFrom} - {$dateTo}] contains [{$unmatchedCount}] unmatched bank ledger entry(ies). All bank ledger entries in the reconciliation period must be matched or accounted for before finalization.',
                "Reconciliation difference is [{\$summary['difference_minor']}]. Difference must be zero to finalize.",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw bank/cheque service error message.");
            }
        }
    }

    public function test_landed_cost_allocation_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->landedCostAllocationServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic landed-cost translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic landed-cost translation still mirrors English for [{$message}].");
        }

        $source = (string) file_get_contents(app_path('Application/Purchasing/LandedCostAllocationService.php'));

        foreach ([
            "=> ['Landed cost currency must match the Goods Receipt purchase currency.']",
            "=> ['Only draft landed cost allocations can be updated.']",
            "=> ['The record has been modified by another user. Please refresh and try again.']",
            "=> ['Only approved landed cost allocations can be posted.']",
            "=> ['Cannot post landed cost allocation without line items.']",
            "=> ['Financial period does not belong to the landed cost fiscal year.']",
            "=> ['FX rate must be 1.000000 (1000000) in this slice.']",
            "=> ['Landed cost can only be posted against a confirmed Goods Receipt.']",
            "=> ['Allocated landed cost split does not equal the header cost amount.']",
            "=> ['Mapped landed cost GL account currencies must match allocation currency.']",
            "=> ['Posted landed cost allocations cannot be cancelled.']",
            'Only {$from} landed cost allocations can be moved to {$to}.',
            "=> ['Cannot submit landed cost allocation without line items.']",
            "=> ['Selected allocation method is not supported.']",
            "=> ['Landed cost amount must be greater than zero.']",
            "=> ['Tax amount cannot be negative.']",
            "=> ['Supplier must be active.']",
            "=> ['Landed cost can only reference a confirmed Goods Receipt.']",
            "=> ['Goods Receipt purchase order is missing.']",
            "=> ['Goods Receipt does not contain stock product lines eligible for landed cost capitalization.']",
            "=> ['Each Goods Receipt line can only appear once.']",
            "=> ['Selected Goods Receipt line is not eligible for landed cost allocation.']",
            "=> ['Manual landed cost allocations cannot be negative.']",
            "=> ['Manual landed cost line amounts must equal the header cost amount.']",
            "=> ['Selected receipt lines do not have positive allocation weight.']",
            "=> ['Goods Receipt line is missing a positive purchase unit cost.']",
            "=> ['Goods Receipt line value contains fractional minor units.']",
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'LandedCostAllocationService contains a raw landed-cost validation message.');
        }
    }

    public function test_ar_ap_allocation_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->arApAllocationServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic AR/AP allocation translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic AR/AP allocation translation still mirrors English for [{$message}].");
        }

        foreach ($this->arApAllocationServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Allocation lines cannot be empty.']",
                "=> ['Every allocation line must reference a receivable entry.']",
                "=> ['Every allocation line must reference a payable entry.']",
                "=> ['Duplicate target receivable entry IDs in single allocation command.']",
                "=> ['Duplicate target payable entry IDs in single allocation command.']",
                'Only posted receipts can be allocated. Current status: [{$receipt->status}].',
                'Only posted payments can be allocated. Current status: [{$payment->status}].',
                "=> ['Allocation amount must be a positive integer.']",
                'Allocation total [{$totalRequested}] exceeds receipt unapplied amount [{$receipt->unapplied_minor}].',
                'Allocation total [{$totalRequested}] exceeds payment unapplied amount [{$payment->unapplied_minor}].',
                "=> ['One or more target receivable entries do not exist.']",
                "=> ['One or more target payable entries do not exist.']",
                'Target receivable entry [{$targetId}] does not exist.',
                'Target payable entry [{$targetId}] does not exist.',
                'Target entry [{$targetId}] customer does not match receipt customer.',
                'Target entry [{$targetId}] supplier does not match payment supplier.',
                'Target entry [{$targetId}] currency [{$target->currency}] does not match receipt currency [{$receipt->currency}].',
                'Target entry [{$targetId}] currency [{$target->currency}] does not match payment currency [{$payment->currency}].',
                'Target entry [{$targetId}] is not a positive AR item.',
                'Target entry [{$targetId}] is not a positive AP item.',
                'Allocation amount [{$lineAmount}] exceeds target remaining allocatable amount [{$remainingAllocatable}].',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw AR/AP allocation service error message.");
            }
        }
    }

    public function test_ar_ap_settlement_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->arApSettlementServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic AR/AP settlement translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic AR/AP settlement translation still mirrors English for [{$message}].");
        }

        foreach ($this->arApSettlementServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Settlement lines cannot be empty.']",
                "=> ['Every settlement line must reference a target receivable entry.']",
                "=> ['Every settlement line must reference a target payable entry.']",
                "=> ['Cannot settle a receivable entry against itself.']",
                "=> ['Cannot settle a payable entry against itself.']",
                "=> ['Duplicate target receivable entry IDs in single settlement command.']",
                "=> ['Duplicate target payable entry IDs in single settlement command.']",
                'Source receivable entry [{$sourceCreditEntryId}] does not exist.',
                'Source payable entry [{$sourceDebitEntryId}] does not exist.',
                'Source entry [{$sourceCreditEntryId}] is not an open credit AR item.',
                'Source entry [{$sourceDebitEntryId}] is not an open debit AP item.',
                "=> ['Settlement amount must be a positive integer.']",
                'Total settlement amount [{$totalRequested}] exceeds source entry remaining credit [{$remainingSourceCredit}].',
                'Total settlement amount [{$totalRequested}] exceeds source entry remaining debit [{$remainingSourceDebit}].',
                'Target receivable entry [{$targetId}] does not exist.',
                'Target payable entry [{$targetId}] does not exist.',
                'Target entry [{$targetId}] customer does not match source entry customer.',
                'Target entry [{$targetId}] supplier does not match source entry supplier.',
                'Target entry [{$targetId}] currency [{$target->currency}] does not match source entry currency [{$sourceEntry->currency}].',
                'Target entry [{$targetId}] is not a positive debit AR item.',
                'Target entry [{$targetId}] is not a positive credit AP item.',
                'Settlement amount [{$lineAmount}] exceeds target entry remaining debit [{$remainingTargetDebit}].',
                'Settlement amount [{$lineAmount}] exceeds target entry remaining credit [{$remainingTargetCredit}].',
                "=> ['Reversal reason is required.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw AR/AP settlement service error message.");
            }
        }
    }

    public function test_ar_ap_receipt_payment_opening_balance_service_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->arApReceiptPaymentOpeningBalanceServiceErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic AR/AP receipt/payment/opening balance translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic AR/AP receipt/payment/opening balance translation still mirrors English for [{$message}].");
        }

        foreach ($this->arApReceiptPaymentOpeningBalanceServiceErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Only draft receipts can be cancelled.']",
                "=> ['Only draft payments can be cancelled.']",
                "=> ['Only draft opening balances can be cancelled.']",
                "=> ['Financial period must belong to the selected fiscal year.']",
                "=> ['Financial period is closed.']",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw AR/AP receipt/payment/opening balance service error message.");
            }
        }
    }

    public function test_invoice_revision_and_currency_input_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->invoiceRevisionAndCurrencyInputErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic invoice revision/currency input translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic invoice revision/currency input translation still mirrors English for [{$message}].");
        }

        foreach ($this->invoiceRevisionAndCurrencyInputSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "=> ['Customer Credit Note does not exist.']",
                "=> ['Sales Return does not exist.']",
                "=> ['Revisions can only be generated for posted customer invoices.']",
                "=> ['Customer invoice has no lines to revise.']",
                "=> ['Currency is required.']",
                "=> ['Currency must be a 3-letter ISO code.']",
                '["{$source} currency is required."]',
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw invoice revision/currency input service error message.");
            }
        }
    }

    public function test_report_export_error_messages_are_translation_backed(): void
    {
        $arabic = json_decode((string) file_get_contents(base_path('lang/ar.json')), true);
        $this->assertIsArray($arabic);

        foreach ($this->reportExportErrorMessages() as $message) {
            $this->assertArrayHasKey($message, $arabic, "Missing Arabic report export translation for [{$message}].");
            $this->assertNotSame($message, $arabic[$message], "Arabic report export translation still mirrors English for [{$message}].");
        }

        foreach ($this->reportExportErrorSourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach ([
                "abort(400, 'Bank account ID is required for export.')",
                "abort(400, 'Cash account ID is required for export.')",
                "abort(400, 'Customer ID is required for export.')",
                "abort(400, 'Supplier ID is required for export.')",
                "RuntimeException('Unable to open CSV output stream.')",
            ] as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, "{$path} contains a raw report export error message.");
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function sensitiveReportPaths(): array
    {
        return [
            '/reports' => 'Reports/Index',
            '/reports/sales-orders' => 'Reports/SalesOrdersReport',
            '/reports/purchase-orders' => 'Reports/PurchaseOrdersReport',
            '/reports/customer-invoices' => 'Reports/CustomerInvoicesReport',
            '/reports/supplier-bills' => 'Reports/SupplierBillsReport',
        ];
    }

    /**
     * @return list<string>
     */
    private function requiredReportDictionaryKeys(): array
    {
        return [
            'submitted',
            'approved',
            'posted',
            'dueDate',
            'orderNumber',
            'invoiceNumber',
            'billNumber',
            'qty',
            'total',
            'totalAmount',
            'totalValue',
            'mixedCurrencyAmount',
            'allCurrencies',
            'clearFilters',
            'activeFilters',
            'journal',
            'arEntry',
            'apEntry',
            'salesOrdersHeadTitle',
            'salesOrdersTitle',
            'salesOrdersDescription',
            'salesOrdersSearchPlaceholder',
            'totalSalesOrders',
            'totalOrderedQuantity',
            'emptySalesOrders',
            'purchaseOrdersHeadTitle',
            'purchaseOrdersTitle',
            'purchaseOrdersDescription',
            'purchaseOrdersSearchPlaceholder',
            'totalPurchaseOrders',
            'emptyPurchaseOrders',
            'customerInvoicesHeadTitle',
            'customerInvoicesTitle',
            'customerInvoicesDescription',
            'customerInvoicesSearchPlaceholder',
            'totalCustomerInvoices',
            'totalInvoicedAmount',
            'emptyCustomerInvoices',
            'supplierBillsHeadTitle',
            'supplierBillsTitle',
            'supplierBillsDescription',
            'supplierBillsSearchPlaceholder',
            'totalSupplierBills',
            'totalBilledAmount',
            'emptySupplierBills',
        ];
    }

    /**
     * @param  array<string, mixed>  $locale
     * @param  list<string>  $path
     */
    private function assertLocalePathIsNotEmpty(array $locale, array $path, string $label): void
    {
        $value = $locale;
        $dotPath = implode('.', $path);

        foreach ($path as $segment) {
            $this->assertIsArray($value, "Missing {$label} dictionary path [{$dotPath}].");
            $this->assertArrayHasKey($segment, $value, "Missing {$label} dictionary path [{$dotPath}].");
            $value = $value[$segment];
        }

        $this->assertNotEmpty($value, "Empty {$label} dictionary path [{$dotPath}].");
    }

    /**
     * @return list<string>
     */
    private function glPostingRouteNames(): array
    {
        return [
            'accounting.journal.post',
            'accounting.opening_balances.post',
            'treasury-transfers.post',
            'customer-opening-balances.post',
            'supplier-opening-balances.post',
            'customer-receipts.post',
            'supplier-payments.post',
            'landed-costs.post',
            'expenses.post',
            'prepaid-schedules.recognitions.post',
            'accrual-schedules.entries.post',
            'payroll.runs.post',
            'rentals.invoices.post',
            'customer-invoices.post',
            'stock-counts.post',
            'stock-adjustments.post',
            'supplier-bills.post',
            'sales-returns.post',
            'customer-credit-notes.post',
            'purchase-returns.post',
            'supplier-adjustment-notes.post',
            'fixed-assets.capitalize',
            'fixed-assets.reverse_capitalization',
            'fixed-assets.depreciation-runs.store',
            'fixed-assets.depreciation-runs.reverse',
            'fixed-assets.disposals.store',
            'fixed-assets-disposals.reverse',
        ];
    }

    /**
     * @return list<string>
     */
    private function salesPurchasingFlashMessages(): array
    {
        return [
            'Sales Order created successfully.',
            'Sales Order updated successfully.',
            'Sales Order submitted successfully.',
            'Sales Order confirmed successfully.',
            'Sales Order cancelled successfully.',
            'Purchase Order created successfully.',
            'Purchase Order updated successfully.',
            'Purchase Order submitted successfully.',
            'Purchase Order confirmed successfully.',
            'Purchase Order cancelled successfully.',
            'Delivery Note created successfully.',
            'Delivery Note updated successfully.',
            'Delivery Note confirmed successfully.',
            'Delivery Note cancelled successfully.',
            'Goods Receipt created successfully.',
            'Goods Receipt updated successfully.',
            'Goods Receipt confirmed successfully.',
            'Goods Receipt cancelled successfully.',
            'Customer Invoice created successfully.',
            'Customer Invoice updated successfully.',
            'Customer Invoice submitted successfully.',
            'Customer Invoice approved successfully.',
            'Customer Invoice posted to AR/GL successfully.',
            'Customer Invoice cancelled successfully.',
            'Supplier Bill created successfully.',
            'Supplier Bill updated successfully.',
            'Supplier Bill submitted successfully.',
            'Supplier Bill approved successfully.',
            'Supplier Bill posted successfully to AP/GL.',
            'Supplier Bill cancelled successfully.',
            'Sales Return created successfully.',
            'Sales Return updated successfully.',
            'Sales Return submitted successfully.',
            'Sales Return approved successfully.',
            'Sales Return posted to inventory/GL successfully.',
            'Sales Return cancelled successfully.',
            'Purchase Return created successfully.',
            'Purchase Return updated successfully.',
            'Purchase Return submitted successfully.',
            'Purchase Return approved successfully.',
            'Purchase Return posted to inventory/AP/GL successfully.',
            'Purchase Return cancelled successfully.',
            'Customer Credit Note created successfully.',
            'Customer Credit Note updated successfully.',
            'Customer Credit Note submitted successfully.',
            'Customer Credit Note approved successfully.',
            'Customer Credit Note posted to AR/GL successfully.',
            'Customer Credit Note posted to AR/GL successfully. Invoice revision generated.',
            'Customer Credit Note cancelled successfully.',
            'Supplier Adjustment Note created successfully.',
            'Supplier Adjustment Note updated successfully.',
            'Supplier Adjustment Note submitted successfully.',
            'Supplier Adjustment Note approved successfully.',
            'Supplier Adjustment Note posted to AP/GL successfully.',
            'Supplier Adjustment Note cancelled successfully.',
        ];
    }

    /**
     * @return list<string>
     */
    private function remainingControllerFlashMessages(): array
    {
        return [
            'Bank reconciliation draft created.',
            'Statement line added.',
            'Statement line updated.',
            'Statement line deleted.',
            'Statement line matched to system ledger entry.',
            'Statement line unmatched.',
            'Bank reconciliation finalized successfully.',
            'Bank account created successfully.',
            'Bank account updated successfully.',
            'Cash account created successfully.',
            'Cash account updated successfully.',
            'Customer created successfully.',
            'Customer updated successfully.',
            'Customer receipt created as draft.',
            'Customer receipt posted successfully.',
            'Customer opening balance created as draft.',
            'Customer opening balance posted successfully.',
            'Product Category created successfully.',
            'Product Category updated successfully.',
            'Product Category deleted successfully.',
            'Product created successfully.',
            'Product updated successfully.',
            'Product deleted successfully.',
            'Unit of Measure created successfully.',
            'Unit of Measure updated successfully.',
            'Unit of Measure deleted successfully.',
            'Incoming cheque created as draft.',
            'Incoming cheque received successfully.',
            'Incoming cheque deposited successfully.',
            'Incoming cheque cleared successfully.',
            'Incoming cheque bounced successfully.',
            'Incoming cheque returned successfully.',
            'Landed cost allocation created.',
            'Landed cost allocation updated.',
            'Landed cost allocation submitted.',
            'Landed cost allocation approved.',
            'Landed cost allocation posted.',
            'Landed cost allocation cancelled.',
            'Payment allocated successfully.',
            'Allocation reversed successfully.',
            'Outgoing cheque created as draft.',
            'Outgoing cheque issued successfully.',
            'Outgoing cheque cleared successfully.',
            'Outgoing cheque returned successfully.',
            'Outgoing cheque cancelled successfully.',
            'Adjustment debit settled successfully against target bill(s).',
            'Payable settlement reversed successfully.',
            'Receipt allocated successfully.',
            'Credit settled successfully against target invoice(s).',
            'Receivable settlement reversed successfully.',
            'Stock adjustment created.',
            'Stock adjustment updated.',
            'Stock adjustment submitted.',
            'Stock adjustment approved.',
            'Stock adjustment posted.',
            'Stock adjustment cancelled.',
            'Stock count created.',
            'Stock count updated.',
            'Stock count submitted.',
            'Stock count approved.',
            'Stock count posted.',
            'Stock count cancelled.',
            'Stock location saved.',
            'Stock location updated.',
            'Supplier created successfully.',
            'Supplier updated successfully.',
            'Stock transfer created.',
            'Stock transfer updated.',
            'Stock transfer submitted.',
            'Stock transfer approved.',
            'Stock transfer issued.',
            'Stock transfer received.',
            'Stock transfer cancelled.',
            'Warehouse saved.',
            'Warehouse updated.',
            'Warehouse deleted.',
            'Treasury transfer created.',
            'Treasury transfer updated.',
            'Treasury transfer posted.',
            'Treasury transfer cancelled.',
            'Supplier payment created as draft.',
            'Supplier payment posted successfully.',
            'Supplier opening balance created as draft.',
            'Supplier opening balance posted successfully.',
            'Tax rate created successfully.',
            'Tax rate updated successfully.',
            'Tax rate deleted successfully.',
            'Tax period created successfully.',
            'Draft tax return :number generated successfully.',
            'Tax return :number filed successfully and period locked.',
            'Tax code created successfully.',
            'Tax code updated successfully.',
            'Tax code deleted successfully.',
        ];
    }

    /**
     * @return list<string>
     */
    private function backendGuardErrorMessages(): array
    {
        return [
            'Parent group must share the same account type.',
            'Selected account group does not match the account type.',
            'System account types cannot be deleted.',
            'Cannot delete account type in use by account groups or accounts.',
            'System account categories cannot be deleted.',
            'Cannot delete account category in use by account types.',
            'Cannot remove super admin role from the last active super admin user.',
            'Cannot close financial period because unposted documents exist.',
        ];
    }

    /**
     * @return list<string>
     */
    private function financialServiceErrorMessages(): array
    {
        return [
            'Financial period ID is required for posting.',
            'Financial period :period does not exist.',
            'Target financial period :period is closed or locked.',
            'Posting date :date is outside target financial period bounds (:start to :end).',
            'No financial period covers posting date :date.',
            'No financial period covers date :date.',
            'Customer receipt :id cannot be posted from status :status.',
            'Supplier payment :id cannot be posted from status :status.',
            'Customer opening balance :id cannot be posted from status :status.',
            'Supplier opening balance :id cannot be posted from status :status.',
            'Customer :customer does not exist.',
            'Supplier :supplier does not exist.',
            'Fiscal year :year does not exist.',
            'Currency :currency does not exist.',
            'Allocation :id is already reversed.',
            'Allocation :id cannot be reversed from status :status.',
            'Settlement :id is already reversed.',
            'Settlement :id cannot be reversed from status :status.',
            'Cash Account code :code already exists.',
            'Bank Account code :code already exists.',
            'GL Account :account does not exist.',
            'GL Account :account is inactive.',
            'Branch :branch does not exist or is inactive.',
            'Mapped AR Control account currency :accountCurrency must match receipt currency :currency.',
            'Mapped AP Control account currency :accountCurrency must match payment currency :currency.',
            'Selected Cash Account is missing or inactive.',
            'Selected Bank Account is missing or inactive.',
            'Selected Cash Account does not exist or is inactive.',
            'Selected Bank Account does not exist or is inactive.',
            'Cash account currency :accountCurrency must match receipt currency :currency.',
            'Cash account currency :accountCurrency must match payment currency :currency.',
            'Bank account currency :accountCurrency must match receipt currency :currency.',
            'Bank account currency :accountCurrency must match payment currency :currency.',
            'Linked GL Account for Cash Account is missing or inactive.',
            'Linked GL Account for Bank Account is missing or inactive.',
            'Linked GL Account currency :accountCurrency must match receipt currency :currency.',
            'Linked GL Account currency :accountCurrency must match payment currency :currency.',
            'Receipt requires exactly one of Cash Account or Bank Account.',
            'Payment requires exactly one of Cash Account or Bank Account.',
            'Receipt requires exactly one of cash_account_id or bank_account_id.',
            'Payment requires exactly one of cash_account_id or bank_account_id.',
            'Field :field is required.',
            'Field :field must be a positive integer.',
            'Amount must be a positive minor integer.',
            'Receipts currently require 1:1 FX rate until exact integer FX posting is implemented.',
            'Payments currently require 1:1 FX rate until exact integer FX posting is implemented.',
            'Opening balances currently require 1:1 FX rate until exact FX posting is implemented.',
            'Customer already has an active opening balance for this fiscal year.',
            'Supplier already has an active opening balance for this fiscal year.',
            'Mapped account :key currency must match opening balance currency :currency.',
            'Only draft treasury transfers can be updated.',
            'Only draft treasury transfers can be posted.',
            'Only draft treasury transfers can be cancelled.',
            'Transfer amount must be greater than zero.',
            'FX rate must be greater than zero.',
            'Fiscal year does not exist.',
            'Financial period is invalid for the selected fiscal year.',
            'Financial period is closed.',
            'Endpoint type must be cash or bank.',
            'Cash account is required for a cash endpoint.',
            'Bank account is required for a bank endpoint.',
            'Endpoint account type and selected account must match.',
            'Selected endpoint account is missing or inactive.',
            'Linked GL account is missing, inactive, or currency-mismatched.',
            'Source and destination accounts must be different.',
            'Source, destination, and transfer currency must match.',
        ];
    }

    /**
     * @return list<string>
     */
    private function financialServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Accounting/PeriodGuard.php'),
            app_path('Application/Accounting/CustomerOpeningBalanceService.php'),
            app_path('Application/Accounting/CustomerReceiptService.php'),
            app_path('Application/Accounting/PayableAllocationService.php'),
            app_path('Application/Accounting/PayableEntrySettlementService.php'),
            app_path('Application/Accounting/ReceivableAllocationService.php'),
            app_path('Application/Accounting/ReceivableEntrySettlementService.php'),
            app_path('Application/Accounting/SupplierOpeningBalanceService.php'),
            app_path('Application/Accounting/SupplierPaymentService.php'),
            app_path('Application/Accounting/TreasuryTransferService.php'),
            app_path('Application/Expenses/AccrualScheduleService.php'),
            app_path('Application/Expenses/PrepaidScheduleService.php'),
            app_path('Application/MasterData/BankAccountService.php'),
            app_path('Application/MasterData/CashAccountService.php'),
            app_path('Application/Payroll/PayrollRunService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function branchApprovalRuleErrorMessages(): array
    {
        return [
            'This branch approval rule requires permission :permission.',
            'Selected branch does not exist.',
            'Unsupported approval document type.',
            'Unsupported branch match mode.',
            'This document type uses document branch matching only.',
            'Selected permission does not exist.',
            'An approval rule already exists for this document, branch match, and branch scope.',
        ];
    }

    /**
     * @return list<string>
     */
    private function taxServiceErrorMessages(): array
    {
        return [
            'Tax period label is required.',
            'Start date and end date are required.',
            'End date must be greater than or equal to start date.',
            'Tax period dates (:start to :end) overlap with an existing tax period.',
            'Cannot generate draft tax return for a filed tax period.',
            'Tax period is already filed.',
            'Tax code [:code] already exists.',
            'System tax codes cannot have their code changed.',
            'Invalid calculation mode.',
            'Invalid recoverability mode.',
            'System tax codes cannot be deleted.',
            'Cannot delete tax code that has rates configured.',
            'Tax rate basis points must be a non-negative integer.',
            'Effective to date cannot be before effective from date.',
            'Tax-affecting postings are blocked because tax period :period (:start to :end) is filed.',
            'Tax code [:code] is inactive.',
            'No active tax rate found for tax code [:code] on date [:date].',
            'Unsupported calculation mode [:mode].',
        ];
    }

    /**
     * @return list<string>
     */
    private function taxServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Taxes/TaxCalculationService.php'),
            app_path('Application/Taxes/TaxMasterDataService.php'),
            app_path('Application/Taxes/TaxPeriodGuard.php'),
            app_path('Application/Taxes/TaxPeriodService.php'),
            app_path('Application/Taxes/TaxReturnService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function expenseServiceErrorMessages(): array
    {
        return [
            'Expense date is required.',
            'FX rate must be 1.000000 (1000000) in this slice.',
            'Only draft expenses can be updated.',
            'The record has been modified by another user. Please refresh and try again.',
            'Posting an expense requires an authenticated actor.',
            'Only approved expenses can be posted.',
            'Cannot post an expense without lines.',
            'Financial period does not belong to the expense fiscal year.',
            'Only unposted expenses can be cancelled.',
            'Expense must be :from before it can move to :to.',
            'Expense requires at least one line.',
            'At least one expense line is required.',
            'Line :line category is required.',
            'Line :line category must be active.',
            'Line :line expense account is required.',
            'Line :line account must be an active debit expense account and not a control account.',
            'Line :line quantity must be greater than zero.',
            'Line :line unit amount must be greater than zero.',
            'Settlement method must be payable, cash, or bank.',
            'Payable expenses require an active supplier.',
            'Cash settlement requires an active cash account.',
            'Bank settlement requires an active bank account.',
            'Settlement account currency must match expense currency.',
            'Select the same operational branch as the settlement account, or use an unassigned settlement account.',
            'Expense branch must match the selected settlement account branch.',
            'At least one attachment is required before posting this expense.',
            'No open financial period covers date :date.',
            'Line :line amount exceeds maximum allowable integer limit.',
            'Line :line quantity and unit amount result in fractional minor units.',
            'Currency [:code] does not exist.',
            'Selected branch does not exist or is inactive.',
            ':label currency must match expense currency.',
            'Expense categories already used on expenses cannot be deleted.',
            'Expense categories already used on schedules cannot be deleted.',
            'Expense category code [:code] already exists.',
            'Default expense account must be an active debit expense account and not a control account.',
            'Default tax code must be active.',
            'Only draft prepaid schedules can be updated.',
            'Prepaid schedule must be submitted before approval.',
            'Prepaid schedule requires recognition rows.',
            'Only unposted prepaid schedules can be cancelled.',
            'Prepaid schedules with posted recognitions cannot be cancelled.',
            'Posting prepaid recognition requires an authenticated actor.',
            'Only approved or active prepaid schedules can be recognized.',
            'Only pending recognition rows can be posted.',
            'Prepaid schedule must be :from before it can move to :to.',
            'Schedule date is required.',
            'Start date is required.',
            'Months must be between 1 and 120.',
            'Total amount must be greater than zero.',
            'Selected currency is missing from the currency registry.',
            'Selected branch is inactive or missing.',
            'Selected expense category is inactive or missing.',
            'Prepaid asset account must be an active debit asset account.',
            'Expense account must be an active non-control debit expense account.',
            ':label currency must match the schedule currency.',
            'Only draft accrual schedules can be updated.',
            'Accrual schedule must be submitted before approval.',
            'Accrual schedule requires entry rows.',
            'Only unposted accrual schedules can be cancelled.',
            'Accrual schedules with posted entries cannot be cancelled.',
            'Posting accrual entries requires an authenticated actor.',
            'Only approved or active accrual schedules can be posted.',
            'Only pending accrual entries can be posted.',
            'Accrual schedule must be :from before it can move to :to.',
            'Accrued liability account must be an active credit liability account.',
        ];
    }

    /**
     * @return list<string>
     */
    private function expenseServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Expenses/AccrualScheduleService.php'),
            app_path('Application/Expenses/ExpenseCategoryService.php'),
            app_path('Application/Expenses/ExpenseService.php'),
            app_path('Application/Expenses/PrepaidScheduleService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function payrollServiceErrorMessages(): array
    {
        return [
            'Only draft payroll runs can be regenerated.',
            'Payroll run must be submitted before approval.',
            'Only unposted payroll runs can be cancelled.',
            'Posting payroll requires an authenticated actor.',
            'Only approved payroll runs can be posted.',
            'Payroll year must be between 2000 and 2100.',
            'Payroll month must be between 1 and 12.',
            'Payroll period is locked.',
            'Deductions exceed gross pay for employee :employee.',
            'No active employees matched this payroll run.',
            'Payroll posting is not balanced.',
            'Payroll run must be :from before it can move to :to.',
            'Payroll run has no payable lines.',
            'Invalid branch reference.',
            'Selected branch is inactive or missing.',
            'Selected currency is missing from the currency registry.',
            'Invalid payroll run type.',
            ':label currency must match payroll currency.',
            'The component was modified by another user. Please refresh and try again.',
            'System payroll components cannot be deleted.',
            'Payroll component is assigned to employees and cannot be deleted.',
            'Component code is required and may contain letters, numbers, dots, underscores, or dashes.',
            'Component code already exists.',
            'Invalid component type.',
            'Invalid calculation type.',
            'Default amount cannot be negative.',
            'Percent-based component requires a rate between 0 and 1000000 basis points.',
            'Expense account must be an active non-control debit expense account.',
            'Liability account must be an active credit liability account.',
            'English component name is required.',
            'The employee was modified by another user. Please refresh and try again.',
            'Employee code is required and may contain letters, numbers, dots, underscores, or dashes.',
            'Employee code already exists.',
            'Invalid employee status.',
            'Hire date is required.',
            'Termination date cannot be before hire date.',
            'Base salary cannot be negative.',
            'Invalid payment method.',
            'English employee name is required.',
            'Selected payroll component is inactive or missing.',
            'Amount cannot be negative.',
            'Rate must be between 0 and 1000000 basis points.',
            'Effective start date is required.',
            'Effective end date cannot be before effective start date.',
        ];
    }

    /**
     * @return list<string>
     */
    private function payrollServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Payroll/EmployeePayrollComponentService.php'),
            app_path('Application/Payroll/EmployeeService.php'),
            app_path('Application/Payroll/PayrollComponentService.php'),
            app_path('Application/Payroll/PayrollRunService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function inventoryWorkflowServiceErrorMessages(): array
    {
        return [
            'Warehouse code [:code] already exists.',
            'The record has been modified by another user. Please refresh and try again.',
            'Default warehouse cannot be deleted.',
            'Warehouse has stock balances and cannot be deleted.',
            'Warehouse is used by stock transfers and cannot be deleted.',
            'Location code [:code] already exists in the selected warehouse.',
            'Selected warehouse is invalid or inactive.',
            'Selected branch is invalid or inactive.',
            'Name is required.',
            'Invalid warehouse type.',
            'Invalid stock location type.',
            'Code is required.',
            'Default warehouse is inactive.',
            'Only draft stock counts can be updated.',
            'Only draft or submitted stock counts can be approved.',
            'Stock count must have at least one line.',
            'Only approved stock counts can be posted.',
            'Stock count cannot move from [:from] to [:to].',
            'Count date is required.',
            'Product is already listed in this count.',
            'Quantities must be greater than or equal to zero.',
            'Unit cost must be greater than zero when provided.',
            'Selected product must be an active stock item.',
            'Only draft stock transfers can be updated.',
            'Only draft or submitted stock transfers can be approved.',
            'Stock transfer must have at least one line.',
            'Only approved stock transfers can be issued.',
            'Only issued stock transfers can be received.',
            'Stock transfer cannot move from [:from] to [:to].',
            'Destination warehouse must be different from source warehouse.',
            'Transfer date is required.',
            'Selected unit of measure is invalid for this product.',
            'Transfer quantity must be greater than zero.',
            'Receipt quantity cannot exceed issued remaining quantity.',
            'Transfer value allocation is too small for partial receipt. Receive the remaining quantity together.',
            'No stock valuation currency exists for this product.',
            'Only draft stock adjustments can be updated.',
            'Only draft or submitted stock adjustments can be approved.',
            'Stock adjustment must have at least one line.',
            'Only approved stock adjustments can be posted.',
            'Stock adjustment cannot move from [:from] to [:to].',
            'Adjustment date is required.',
            'Adjustment quantity delta must not be zero.',
        ];
    }

    /**
     * @return list<string>
     */
    private function inventoryWorkflowServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Inventory/StockAdjustmentService.php'),
            app_path('Application/Inventory/StockCountService.php'),
            app_path('Application/Inventory/StockTransferService.php'),
            app_path('Application/Inventory/WarehouseResolver.php'),
            app_path('Application/Inventory/WarehouseService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function inventoryCostingServiceErrorMessages(): array
    {
        return [
            'Receipt quantity must be greater than zero.',
            'Receipt unit cost must be greater than zero.',
            'Mapped GL account currencies must match movement currency.',
            'Stock balance already exists for this product in currency [:currency]. Multi-currency valuation for the same product is not allowed.',
            'Issue quantity must be greater than zero.',
            'Insufficient stock balance for product. Available: :available.',
            'Quantity and unit cost result in fractional minor units.',
            'Inventory calculation exceeds supported integer range.',
            'Return quantity must be greater than zero.',
            'Return unit cost must be greater than zero.',
            'Scrap quantity must be greater than zero.',
            'Insufficient stock balance for scrap. Available: :available.',
            'Insufficient source warehouse stock. Available: :available.',
            'Transfer receipt quantity must be greater than zero.',
            'Transfer receipt value must be greater than zero.',
            'Stock balance already exists for this product in currency [:currency]. Multi-currency valuation for the same product and warehouse is not allowed.',
            'Positive stock adjustments require a positive unit cost.',
            'Insufficient stock balance for adjustment. Available: :available.',
            'No original :movement_type movement found for source line [:source_line_id].',
            'Return quantity cannot exceed original quantity.',
            'Landed cost value must be greater than zero.',
            'Landed cost can only be capitalized while stock remains in the target warehouse.',
            'Selected warehouse is invalid or inactive.',
        ];
    }

    /**
     * @return list<string>
     */
    private function inventoryCostingServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Inventory/MovingWeightedAverageInventoryService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function rentalItemAndContractServiceErrorMessages(): array
    {
        return [
            'The rentable item was modified by another user. Please refresh and try again.',
            'Rentable items in active rental workflow states cannot be deleted.',
            'Rentable item code is required and may contain letters, numbers, dots, underscores, or dashes.',
            'Rentable item code already exists.',
            'Invalid rentable item source.',
            'Invalid rentable item status.',
            'Invalid rentable item condition.',
            'Selected currency is missing from the currency registry.',
            'Selected product is inactive or missing.',
            'Disposed fixed assets cannot be used as rentable items.',
            'Selected fixed asset is missing.',
            'Selected branch is inactive or missing.',
            'Selected warehouse is inactive or missing.',
            'Selected warehouse belongs to a different operational branch.',
            'Standalone rentable items cannot be linked to a product or fixed asset.',
            'Product-sourced rentable items must reference exactly one product.',
            'Fixed-asset-sourced rentable items must reference exactly one fixed asset.',
            'English rentable item name is required.',
            'Invalid reference.',
            'Amount cannot be negative.',
            'The rental contract was modified by another user. Please refresh and try again.',
            'Only draft rental contracts can be updated.',
            'Only draft rental contracts can be submitted.',
            'Only available rental items can be reserved.',
            'Only submitted rental contracts can be approved.',
            'Only reserved rental items can be allocated.',
            'Only approved rental contracts can be activated.',
            'Only allocated rental items can be activated.',
            'Active or completed rental contracts require the return workflow instead of cancellation.',
            'Selected customer is inactive or missing.',
            'Expected end date cannot be before start date.',
            'Invalid billing cycle.',
            'Rental contract must have at least one line.',
            'Invalid rental contract line.',
            'A rentable item can appear only once on the same contract.',
            'Selected rentable item is not available for reservation.',
            'Rentable item currency must match contract currency.',
            'Line dates must be within the rental contract date range.',
            'Invalid rental rate type.',
            'Estimated units must be at least 1.',
            'Invalid or missing reference.',
            'Date is required.',
            'Invalid date.',
            'Calculated amount is too large.',
            'Calculated total is too large.',
        ];
    }

    /**
     * @return list<string>
     */
    private function rentalItemAndContractServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Rentals/RentableItemService.php'),
            app_path('Application/Rentals/RentalContractService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function rentalFulfillmentServiceErrorMessages(): array
    {
        return [
            'Only approved or active rental contracts can be handed over.',
            'Only draft handovers can be confirmed.',
            'Only allocated rental items can be handed over.',
            'Only draft handovers can be cancelled.',
            'Only active rental contracts can receive returns.',
            'Only draft returns can be submitted.',
            'Only rented items can be submitted for return.',
            'Only submitted returns can be completed after inspection.',
            'Only active rental contracts can be completed through return inspection.',
            'Only return-pending items can be inspected.',
            'Completed rental returns cannot be cancelled.',
            'Only return-pending items can be released from a cancelled return.',
            'Handover must have at least one line.',
            'Invalid handover line.',
            'A contract line can be handed over only once per document.',
            'Selected line does not belong to the rental contract.',
            'Selected line was already handed over.',
            'Only allocated or rented contract lines can be handed over.',
            'Invalid handover condition.',
            'Return must have at least one line.',
            'Invalid return line.',
            'A contract line can be returned only once per document.',
            'Selected line is already on an open return document.',
            'Only rented contract lines can be returned.',
            'Invalid return condition.',
            'Invalid return outcome.',
            'Rental contract must have at least one line.',
            'Invalid or missing reference.',
            'Date is required.',
            'Invalid date.',
            'Amount cannot be negative.',
        ];
    }

    /**
     * @return list<string>
     */
    private function rentalFulfillmentServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Rentals/RentalFulfillmentService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function rentalInvoiceServiceErrorMessages(): array
    {
        return [
            'Only draft rental invoices can be updated.',
            'The rental invoice was modified by another user. Please refresh and try again.',
            'Only draft rental invoices can be submitted.',
            'Rental invoice must have at least one line before submitting.',
            'Only draft or submitted rental invoices can be approved.',
            'Rental invoice total must be greater than zero before approval.',
            'Only approved rental invoices can be posted.',
            'Rental invoice must have at least one line before posting.',
            'Rental invoice total must be greater than zero before posting.',
            'Posted rental invoices require a credit/reversal workflow instead of cancellation.',
            'Only approved, active, or completed rental contracts can be invoiced.',
            'Invalid rental invoice type.',
            'Rental invoice currency must match the rental contract currency.',
            'FX rate must be 1.000000 (1000000) in this slice.',
            'Billing period end must be on or after billing period start.',
            'Rental invoice total must be greater than zero.',
            'Rental invoice must have at least one line.',
            'Invalid rental invoice line.',
            'Invalid rental invoice line type.',
            'Selected contract line does not belong to the rental contract.',
            'Rent and deposit lines must reference a rental contract line.',
            'Rent lines require a billing period start and end.',
            'Selected return line does not belong to the rental contract.',
            'Return line charges require a completed rental return.',
            'Return line must match the selected contract line.',
            'Damage charge lines must reference a completed rental return line.',
            'Duplicate rental invoice line source in the same document.',
            'Quantity must be greater than zero.',
            'Unit amount cannot be negative.',
            'Line amount must be greater than zero.',
            'This rental line has already been invoiced for the selected billing period.',
            'Deposit invoice amount exceeds the remaining contract-line deposit.',
            'Damage charge exceeds the remaining inspected damage amount.',
            'Mapped account for [:key] uses :account_currency; rental invoice currency is :invoice_currency.',
            'No open financial period covers date :date.',
            'Line amount exceeds maximum allowable integer limit.',
            'Line amount results in fractional minor currency units.',
            'A valid identifier is required.',
            'Date is required.',
            'Invalid date.',
        ];
    }

    /**
     * @return list<string>
     */
    private function rentalInvoiceServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Rentals/RentalInvoiceService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function salesInvoiceAndSupplierBillServiceErrorMessages(): array
    {
        return [
            'Customer is required.',
            'Customer must be active.',
            'FX rate must be 1.000000 (1000000) in this slice.',
            'Invoice date is required.',
            'Customer invoice can reference either a Sales Order or a Delivery Note, not both.',
            'Customer invoices can only reference confirmed Sales Orders.',
            'Customer must match the Sales Order customer.',
            'Currency must match the Sales Order currency.',
            'Customer invoices can only reference confirmed Delivery Notes.',
            'Customer must match the Delivery Note customer.',
            'Currency must match the Delivery Note currency.',
            'Only draft customer invoices can be updated.',
            'The record has been modified by another user. Please refresh and try again.',
            'Only draft customer invoices can be submitted.',
            'Customer invoice must have at least one line item before submitting.',
            'Only draft or submitted customer invoices can be approved.',
            'Customer invoice must have at least one line item before approving.',
            'Only approved customer invoices can be posted to AR/GL.',
            'Customer invoice must have at least one line item before posting.',
            'Stock product lines on customer invoices must be sourced from a Delivery Note.',
            'Financial period is closed.',
            'Financial period does not belong to the invoice fiscal year.',
            'Invoice date must fall within the financial period.',
            'Mapped GL account currency (AR: :ar_currency, Rev: :revenue_currency) must match invoice currency (:invoice_currency).',
            'Mapped tax account currency (:account_currency) must match invoice currency (:invoice_currency).',
            'Posted customer invoices cannot be cancelled in this slice.',
            'No open financial period covers date :date.',
            'At least one line item is required.',
            'Line :line cannot reference both a Sales Order line and a Delivery Note line.',
            'Line :line references a Sales Order line but no Sales Order source was selected.',
            'Line :line references a Delivery Note line but no Delivery Note source was selected.',
            'Line :line must reference a Sales Order line.',
            'Line :line must reference a Delivery Note line.',
            'Product on line :line does not exist.',
            'Stock product [:code] must be sourced from a Delivery Note.',
            'Product [:code] is inactive or not enabled for sales.',
            'Quantity on line :line must be greater than zero.',
            'Unit price on line :line cannot be negative.',
            'Line :line does not belong to the selected Sales Order.',
            'Product on line :line must match the selected Sales Order line.',
            'Unit of measure on line :line must match the selected Sales Order line.',
            'Unit price on line :line must match the selected Sales Order line.',
            'Invoiced quantity on line :line exceeds remaining Sales Order quantity. Maximum remaining allowed is :maximum.',
            'Line :line does not belong to the selected Delivery Note.',
            'Product on line :line must match the selected Delivery Note line.',
            'Unit of measure on line :line must match the selected Delivery Note line.',
            'Delivery Note line :line is not linked to a Sales Order line.',
            'Unit price on line :line must match the Delivery Note source Sales Order line.',
            'Invoiced quantity on line :line exceeds remaining Delivery Note quantity. Maximum remaining allowed is :maximum.',
            'Unit of measure on line :line must match the selected product.',
            'Line :line amount exceeds maximum allowable integer limit.',
            'Line :line total results in fractional minor currency units which is not permitted.',
            'Supplier is required.',
            'Supplier must be active.',
            'Bill date is required.',
            'Supplier bill can reference either a Purchase Order or a Goods Receipt, not both.',
            'Supplier bills can only reference confirmed Purchase Orders.',
            'Supplier must match the Purchase Order supplier.',
            'Currency must match the Purchase Order currency.',
            'Supplier bills can only reference confirmed Goods Receipts.',
            'Supplier must match the Goods Receipt supplier.',
            'Currency must match the Goods Receipt currency.',
            'Only draft supplier bills can be updated.',
            'Only draft supplier bills can be submitted.',
            'Only submitted supplier bills can be approved.',
            'Only approved supplier bills can be posted.',
            'Cannot post supplier bill without line items.',
            'Stock product lines on supplier bills must be sourced from a Goods Receipt.',
            'Financial period does not belong to the bill fiscal year.',
            'Bill date must fall within the financial period.',
            'Mapped Purchase Expense account currency must match bill currency.',
            'Mapped GRNI Clearing account currency must match bill currency.',
            'Mapped AP Control account currency must match bill currency.',
            'Mapped Input Tax Receivable account currency must match bill currency.',
            'Posted supplier bills cannot be cancelled in this slice.',
            'Line :line cannot reference both a Purchase Order line and a Goods Receipt line.',
            'Line :line must reference a Purchase Order line for this Purchase Order bill.',
            'Line :line cannot reference a Goods Receipt line for a Purchase Order bill.',
            'Line :line must reference a Goods Receipt line for this Goods Receipt bill.',
            'Line :line cannot reference a Purchase Order line for a Goods Receipt bill.',
            'Line :line cannot reference a source line without a matching bill source header.',
            'Line :line product is required.',
            'Line :line product must be active and purchase-enabled.',
            'Line :line stock product must be sourced from a Goods Receipt.',
            'Line :line quantity must be greater than zero.',
            'Line :line unit cost cannot be negative.',
            'Line :line does not belong to the selected Purchase Order.',
            'Line :line product does not match Purchase Order line product.',
            'Line :line UOM does not match Purchase Order line UOM.',
            'Line :line unit cost must match the selected Purchase Order line.',
            'Line :line quantity exceeds remaining unbilled Purchase Order line quantity (:maximum).',
            'Line :line does not belong to the selected Goods Receipt.',
            'Line :line product does not match Goods Receipt line product.',
            'Line :line UOM does not match Goods Receipt line UOM.',
            'Goods Receipt line :line is not linked to a Purchase Order line.',
            'Line :line unit cost must match the Goods Receipt source Purchase Order line.',
            'Line :line quantity exceeds remaining unbilled Goods Receipt line quantity (:maximum).',
            'Line :line stock product bill unit cost must match Goods Receipt source unit cost.',
            'Line :line quantity and unit cost result in fractional minor units.',
        ];
    }

    /**
     * @return list<string>
     */
    private function salesInvoiceAndSupplierBillServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Sales/CustomerInvoiceService.php'),
            app_path('Application/Purchasing/SupplierBillService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function returnsAndAdjustmentServiceErrorMessages(): array
    {
        return [
            'Customer is required.',
            'Customer must be active.',
            'Delivery Note is required.',
            'Sales returns can only reference confirmed Delivery Notes.',
            'Customer must match the Delivery Note customer.',
            'Currency must match the Delivery Note currency.',
            'Return date is required.',
            'Customer Invoice must belong to this customer.',
            'Only draft sales returns can be updated.',
            'The record has been modified by another user. Please refresh and try again.',
            'Only draft sales returns can be submitted.',
            'Sales return must have at least one line item before submitting.',
            'Only draft or submitted sales returns can be approved.',
            'Posted sales returns cannot be cancelled.',
            'Only approved sales returns can be posted.',
            'Sales return must have at least one line item before posting.',
            'Financial period is closed.',
            'Financial period does not belong to the sales return fiscal year.',
            'No open financial period covers date :date.',
            'At least one line item is required.',
            'Customer Invoice must belong to the Delivery Note customer.',
            'Line :line must reference a Delivery Note line.',
            'Line :line does not belong to the selected Delivery Note.',
            'Product on line :line is required.',
            'Product on line :line does not exist.',
            'Product [:code] is inactive.',
            'Product on line :line must match the selected Delivery Note line.',
            'Unit of measure on line :line must match the selected Delivery Note line.',
            'Quantity on line :line must be greater than zero.',
            'Disposition on line :line must be one of: :allowed.',
            'Manual restock value on line :line is required and must be >= 0.',
            'Returned quantity on line :line exceeds remaining Delivery Note line quantity. Maximum remaining allowed is :maximum.',
            'Customer Invoice line on line :line does not exist.',
            'Customer Invoice line on line :line does not belong to the selected Customer Invoice.',
            'Product on line :line must match the selected Customer Invoice line.',
            'Unit of measure on line :line must match the selected Customer Invoice line.',
            'Only draft customer credit notes can be updated.',
            'Only draft customer credit notes can be submitted.',
            'Customer credit note must have at least one line item before submitting.',
            'Only draft or submitted customer credit notes can be approved.',
            'Customer credit note must have at least one line item before approving.',
            'Posted customer credit notes cannot be cancelled in this slice.',
            'Only approved customer credit notes can be posted to AR/GL.',
            'Customer credit note must have at least one line item before posting.',
            'Financial period does not belong to the credit note fiscal year.',
            'Mapped GL account currency must match credit note currency.',
            'Credit date is required.',
            'Tax mode must be one of: :allowed.',
            'Tax rate in basis points is required for manual rate mode and must be an integer >= 0.',
            'Tax amount override is required for manual amount mode and must be an integer >= 0.',
            'Referenced Customer Invoice must be posted.',
            'Currency must match the referenced Customer Invoice currency.',
            'Sales Return must belong to this customer.',
            'Description on line :line is required.',
            'Line :line references a Customer Invoice line but no Customer Invoice was selected.',
            'Customer Invoice line on line :line does not belong to the referenced invoice.',
            'Credited quantity on line :line exceeds remaining invoiced quantity. Maximum remaining allowed is :maximum.',
            'Tax rate on line :line cannot be negative.',
            'Supplier is required.',
            'Supplier must be active.',
            'Goods Receipt is required.',
            'Purchase Returns can only be created for confirmed Goods Receipts.',
            'Supplier must match the Goods Receipt supplier.',
            'Currency must match the Goods Receipt currency.',
            'Supplier Bill does not belong to the selected supplier.',
            'Currency must match the Supplier Bill currency.',
            'Only draft purchase returns can be updated.',
            'Only draft purchase returns can be submitted.',
            'Purchase return must have at least one line item before submitting.',
            'Only draft or submitted purchase returns can be approved.',
            'Purchase return must have at least one line item before approving.',
            'Posted purchase returns cannot be cancelled in this slice.',
            'Only approved purchase returns can be posted.',
            'Cannot post purchase return without line items.',
            'Financial period does not belong to the return fiscal year.',
            'Mapped GL account currencies must match return currency.',
            'Insufficient stock balance for product. Available: :available.',
            'Line :line must reference a Goods Receipt line.',
            'Line :line does not belong to the selected Goods Receipt.',
            'Line :line product is required.',
            'Product on line :line is inactive or does not exist.',
            'Product on line :line must match the selected Goods Receipt line.',
            'Unit of measure on line :line must match the selected Goods Receipt line.',
            'Line :line references a Supplier Bill line but no Supplier Bill was selected.',
            'Line :line does not belong to the selected Supplier Bill.',
            'Product on line :line must match the selected Supplier Bill line.',
            'Unit of measure on line :line must match the selected Supplier Bill line.',
            'Returned quantity on line :line exceeds remaining Goods Receipt line quantity. Maximum remaining allowed is :maximum.',
            'Inventory calculation exceeds supported integer range.',
            'Calculation exceeds supported integer range.',
            'Adjustment date is required.',
            'Only draft supplier adjustment notes can be updated.',
            'Only draft supplier adjustment notes can be submitted.',
            'Supplier adjustment note must have at least one line item before submitting.',
            'Only draft or submitted supplier adjustment notes can be approved.',
            'Supplier adjustment note must have at least one line item before approving.',
            'Posted supplier adjustment notes cannot be cancelled in this slice.',
            'Only approved supplier adjustment notes can be posted.',
            'Cannot post supplier adjustment note without line items.',
            'Financial period does not belong to the note fiscal year.',
            'Cannot post a supplier adjustment note with a zero or negative total.',
            'Mapped AP Control account currency must match note currency.',
            'Mapped contra account currency must match note currency.',
            'Mapped Input Tax Receivable account currency must match note currency.',
            'Direction must be one of: :allowed.',
            'Tax rate cannot be negative.',
            'Manual tax amount cannot be negative.',
            'Supplier Bill must be posted.',
            'Purchase Return does not belong to the selected supplier.',
            'Currency must match the Purchase Return currency.',
            'Unit cost on line :line cannot be negative.',
            'Line :line references a Purchase Return line but no Purchase Return was selected.',
            'Line :line does not belong to the selected Purchase Return.',
        ];
    }

    /**
     * @return list<string>
     */
    private function returnsAndAdjustmentServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Sales/SalesReturnService.php'),
            app_path('Application/Sales/CustomerCreditNoteService.php'),
            app_path('Application/Purchasing/PurchaseReturnService.php'),
            app_path('Application/Purchasing/SupplierAdjustmentNoteService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function orderAndFulfillmentServiceErrorMessages(): array
    {
        return [
            'Sales Order in status [:status] cannot be updated.',
            'Sales Order in status [:status] cannot be submitted.',
            'Sales Order in status [:status] cannot be confirmed.',
            'Sales Order must have at least one line before submission.',
            'Sales Order must have at least one line before confirmation.',
            'Confirmed Sales Orders cannot be cancelled in this slice.',
            'Customer is required.',
            'Selected Customer is invalid or inactive.',
            'Supplier is required.',
            'Selected Supplier is invalid or inactive.',
            'Currency is required.',
            'Selected Currency is invalid.',
            'Order date is required.',
            'Expected delivery date must be on or after order date.',
            'Expected receipt date must be on or after order date.',
            'FX rate must be a positive integer.',
            'At least one order line is required.',
            'Product is required on line :line.',
            'Selected Product on line :line is invalid, inactive, or not sales-enabled.',
            'Selected Product on line :line is invalid, inactive, or not purchase-enabled.',
            'Unit of Measure on line :line is invalid or inactive.',
            'Unit of Measure on line :line must match product default UOM.',
            'Quantity on line :line must be greater than zero.',
            'Unit price on line :line must be greater than zero.',
            'Quantity and unit price product exceeds maximum integer capacity on line :line.',
            'Line total produces a fractional minor unit and must be an exact integer minor amount.',
            'Only draft purchase orders can be updated.',
            'The record has been modified by another user. Please refresh and try again.',
            'Only draft purchase orders can be submitted.',
            'Cannot submit a purchase order without line items.',
            'Only draft or submitted purchase orders can be confirmed.',
            'Cannot confirm a purchase order without line items.',
            'Confirmed purchase orders cannot be cancelled in this slice.',
            'Sales Order is required.',
            'Delivery Notes can only be created for confirmed Sales Orders.',
            'Delivery date is required.',
            'Only draft delivery notes can be updated.',
            'Only draft delivery notes can be confirmed.',
            'Cannot confirm a delivery note without line items.',
            'Confirmed delivery notes cannot be cancelled in this slice.',
            'At least one line item is required.',
            'Line :line does not belong to the selected Sales Order.',
            'Delivered quantity on line :line exceeds remaining Sales Order quantity. Maximum remaining allowed is :maximum.',
            'Purchase Order is required.',
            'Goods Receipts can only be created for confirmed Purchase Orders.',
            'Receipt date is required.',
            'Only draft goods receipts can be updated.',
            'Only draft goods receipts can be confirmed.',
            'Cannot confirm a goods receipt without line items.',
            'Confirmed goods receipts cannot be cancelled in this slice.',
            'Line :line does not belong to the selected Purchase Order.',
            'Received quantity on line :line exceeds remaining Purchase Order quantity. Maximum remaining allowed is :maximum.',
        ];
    }

    /**
     * @return list<string>
     */
    private function orderAndFulfillmentServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Sales/SalesOrderService.php'),
            app_path('Application/Purchasing/PurchaseOrderService.php'),
            app_path('Application/Sales/DeliveryNoteService.php'),
            app_path('Application/Purchasing/GoodsReceiptService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function catalogCustomerSupplierServiceErrorMessages(): array
    {
        return [
            'Product code / SKU is required.',
            'Product code / SKU [:code] already exists.',
            'Invalid product type [:type]. Allowed types: :allowed',
            'Invalid product status [:status]. Allowed statuses: :allowed',
            'Unit of Measure is required.',
            'Selected Unit of Measure is invalid or inactive.',
            'Selected Product Category is invalid or inactive.',
            'Product category code is required.',
            'Product category code [:code] already exists.',
            'Cannot delete Product Category [:code] because it is referenced by existing products.',
            'Unit of measure code is required.',
            'Unit of measure code [:code] already exists.',
            'Cannot delete Unit of Measure [:code] because it is referenced by existing products.',
            'Customer code [:code] already exists.',
            'Customer status must be active or inactive.',
            'Supplier code [:code] already exists.',
            'Supplier status must be active or inactive.',
        ];
    }

    /**
     * @return list<string>
     */
    private function catalogCustomerSupplierServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Catalog/ProductService.php'),
            app_path('Application/Catalog/ProductCategoryService.php'),
            app_path('Application/Catalog/UnitOfMeasureService.php'),
            app_path('Application/MasterData/CustomerService.php'),
            app_path('Application/MasterData/SupplierService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function accountingMappingServiceErrorMessages(): array
    {
        return [
            'Required accounting mapping [:key] is missing. Please configure it in Chart of Accounts settings.',
            'Mapped account for [:key] is inactive or missing.',
            'Global accounting mappings cannot be deleted. Update the mapped account instead.',
            'Mapping key [:key] is not allowed.',
            'Branch [:branch] does not exist.',
            'Mapping [:key] requires account type [:types].',
            'Mapping [:key] requires account nature [:nature].',
            'Statement line code is required.',
            'Statement line code must be unique.',
            'Statement type must be balance_sheet or income_statement.',
            'Section code is required.',
            'Normal balance must be debit or credit.',
            'Name is required in at least one locale.',
            'Cash flow activity must be operating, investing, or financing.',
            'System statement line code cannot be changed.',
            'System statement line statement type cannot be changed.',
            'Cannot change statement type when line has assigned accounts.',
            'System financial statement lines cannot be deleted.',
            'Cannot delete financial statement line that has assigned accounts.',
            'Financial statement line does not exist.',
            'Cannot assign account to an inactive financial statement line.',
            'Statement line statement type (:line_type) does not match account statement type (:account_type).',
            'Cash and bank GL accounts are classified through their non-cash journal counterparties.',
        ];
    }

    /**
     * @return list<string>
     */
    private function accountingMappingServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Accounting/AccountingAccountMappingService.php'),
            app_path('Application/Accounting/FinancialStatementMappingService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function bankReconciliationAndChequeServiceErrorMessages(): array
    {
        return [
            'Cash account does not have a linked GL account.',
            'Bank account does not have a linked GL account.',
            'Physical cheque number is required.',
            'Amount must be a positive integer.',
            'Cannot receive cheque from status [:status]. Only draft cheques can be received.',
            'Cannot deposit cheque from status [:status]. Only received cheques can be deposited.',
            'Selected deposit bank account is inactive.',
            'Deposit bank account currency [:bank_currency] does not match cheque currency [:cheque_currency].',
            'Cannot clear cheque from status [:status]. Only received or deposited cheques can be cleared.',
            'Cannot clear cheque from status [:status]. Only issued cheques can be cleared.',
            'Bank account must be specified to clear incoming cheque.',
            'Selected bank account is inactive.',
            'Bank account is inactive.',
            'Bank account currency [:bank_currency] does not match cheque currency [:cheque_currency].',
            'Bank account GL account is inactive or currency mismatch.',
            'OWNER DECISION REQUIRED: Post-clear bounce workflow is not implemented in pre-clear cheque lifecycle.',
            'OWNER DECISION REQUIRED: Post-clear return workflow is not implemented in pre-clear cheque lifecycle.',
            'OWNER DECISION REQUIRED: Post-clear cancel workflow is not implemented in pre-clear cheque lifecycle.',
            'Cannot bounce cheque from status [:status]. Only received or deposited pre-clear cheques can be bounced.',
            'Cannot return cheque from status [:status]. Only received or deposited pre-clear cheques can be returned.',
            'Cannot return cheque from status [:status]. Only issued pre-clear cheques can be returned.',
            'Cannot cancel cheque from status [:status]. Only issued pre-clear cheques can be cancelled.',
            'Cannot cancel cheque from status [:status]. Only draft cheques can be cancelled.',
            'Financial period is not open. Current status: [:status].',
            'Event date [:date] is outside period range [:start - :end].',
            'Accounting mapping key [:key] is not configured.',
            'Mapped account [:account] for [:key] is inactive.',
            'Mapped account [:account] currency [:account_currency] does not match cheque currency [:cheque_currency].',
            'Mapped account [:account] type [:account_type] does not match expected [:expected_type].',
            'Mapped account [:account] nature [:account_nature] does not match expected [:expected_nature].',
            'Bank account, financial period, date from, and date to are required.',
            'Date from must be prior to or equal to date to.',
            'Reconciliation date range [:date_from - :date_to] must fall within period range [:start - :end].',
            'Cannot modify lines on a reconciled bank reconciliation.',
            'Statement line date [:statement_date] must be within reconciliation date range [:date_from - :date_to].',
            'Exactly one of debit_minor or credit_minor must be greater than zero.',
            'Unmatch statement line before modifying line details.',
            'Cannot delete lines from a reconciled bank reconciliation.',
            'Unmatch statement line before deleting.',
            'Cannot match line on a reconciled bank reconciliation.',
            'Statement line is already matched to another ledger entry. Unmatch first.',
            'Ledger entry is already matched to another statement line.',
            'Ledger entry GL account does not match bank account GL account.',
            'Ledger entry currency [:ledger_currency] does not match reconciliation currency [:reconciliation_currency].',
            'Ledger entry date [:ledger_date] must be within reconciliation date range [:date_from - :date_to].',
            'Statement line signed movement [:statement_movement] does not match ledger entry signed movement [:ledger_movement].',
            'Cannot unmatch line on a reconciled bank reconciliation.',
            'Statement self-check failed: statement opening + movement != closing balance.',
            'Reconciliation contains [:count] unmatched statement line(s). All statement lines must be matched before finalization.',
            'Date range [:date_from - :date_to] contains [:count] unmatched bank ledger entry(ies). All bank ledger entries in the reconciliation period must be matched or accounted for before finalization.',
            'Reconciliation difference is [:difference]. Difference must be zero to finalize.',
        ];
    }

    /**
     * @return list<string>
     */
    private function bankReconciliationAndChequeServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Accounting/BankBookQueryService.php'),
            app_path('Application/Accounting/CashBookQueryService.php'),
            app_path('Application/Accounting/BankReconciliationService.php'),
            app_path('Application/Accounting/IncomingChequeService.php'),
            app_path('Application/Accounting/OutgoingChequeService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function landedCostAllocationServiceErrorMessages(): array
    {
        return [
            'Landed cost currency must match the Goods Receipt purchase currency.',
            'Only draft landed cost allocations can be updated.',
            'The record has been modified by another user. Please refresh and try again.',
            'Only approved landed cost allocations can be posted.',
            'Cannot post landed cost allocation without line items.',
            'Financial period does not belong to the landed cost fiscal year.',
            'FX rate must be 1.000000 (1000000) in this slice.',
            'Landed cost can only be posted against a confirmed Goods Receipt.',
            'Allocated landed cost split does not equal the header cost amount.',
            'Mapped landed cost GL account currencies must match allocation currency.',
            'Posted landed cost allocations cannot be cancelled.',
            'Only :from landed cost allocations can be moved to :to.',
            'Cannot submit landed cost allocation without line items.',
            'Selected allocation method is not supported.',
            'Landed cost amount must be greater than zero.',
            'Tax amount cannot be negative.',
            'Supplier must be active.',
            'Landed cost can only reference a confirmed Goods Receipt.',
            'Goods Receipt purchase order is missing.',
            'Goods Receipt does not contain stock product lines eligible for landed cost capitalization.',
            'Each Goods Receipt line can only appear once.',
            'Selected Goods Receipt line is not eligible for landed cost allocation.',
            'Manual landed cost allocations cannot be negative.',
            'Manual landed cost line amounts must equal the header cost amount.',
            'Selected receipt lines do not have positive allocation weight.',
            'Goods Receipt line is missing a positive purchase unit cost.',
            'Goods Receipt line value contains fractional minor units.',
        ];
    }

    /**
     * @return list<string>
     */
    private function arApAllocationServiceErrorMessages(): array
    {
        return [
            'Allocation lines cannot be empty.',
            'Every allocation line must reference a receivable entry.',
            'Every allocation line must reference a payable entry.',
            'Duplicate target receivable entry IDs in single allocation command.',
            'Duplicate target payable entry IDs in single allocation command.',
            'Only posted receipts can be allocated. Current status: [:status].',
            'Only posted payments can be allocated. Current status: [:status].',
            'Allocation amount must be a positive integer.',
            'Allocation total [:total] exceeds receipt unapplied amount [:unapplied].',
            'Allocation total [:total] exceeds payment unapplied amount [:unapplied].',
            'One or more target receivable entries do not exist.',
            'One or more target payable entries do not exist.',
            'Target receivable entry [:entry] does not exist.',
            'Target payable entry [:entry] does not exist.',
            'Target entry [:entry] customer does not match receipt customer.',
            'Target entry [:entry] supplier does not match payment supplier.',
            'Target entry [:entry] currency [:entry_currency] does not match receipt currency [:receipt_currency].',
            'Target entry [:entry] currency [:entry_currency] does not match payment currency [:payment_currency].',
            'Target entry [:entry] is not a positive AR item.',
            'Target entry [:entry] is not a positive AP item.',
            'Allocation amount [:amount] exceeds target remaining allocatable amount [:remaining].',
            'Allocation :id is already reversed.',
            'Allocation :id cannot be reversed from status :status.',
        ];
    }

    /**
     * @return list<string>
     */
    private function arApAllocationServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Accounting/ReceivableAllocationService.php'),
            app_path('Application/Accounting/PayableAllocationService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function arApSettlementServiceErrorMessages(): array
    {
        return [
            'Settlement lines cannot be empty.',
            'Every settlement line must reference a target receivable entry.',
            'Every settlement line must reference a target payable entry.',
            'Cannot settle a receivable entry against itself.',
            'Cannot settle a payable entry against itself.',
            'Duplicate target receivable entry IDs in single settlement command.',
            'Duplicate target payable entry IDs in single settlement command.',
            'Source receivable entry [:entry] does not exist.',
            'Source payable entry [:entry] does not exist.',
            'Source entry [:entry] is not an open credit AR item.',
            'Source entry [:entry] is not an open debit AP item.',
            'Settlement amount must be a positive integer.',
            'Total settlement amount [:total] exceeds source entry remaining credit [:remaining].',
            'Total settlement amount [:total] exceeds source entry remaining debit [:remaining].',
            'Target receivable entry [:entry] does not exist.',
            'Target payable entry [:entry] does not exist.',
            'Target entry [:entry] customer does not match source entry customer.',
            'Target entry [:entry] supplier does not match source entry supplier.',
            'Target entry [:entry] currency [:entry_currency] does not match source entry currency [:source_currency].',
            'Target entry [:entry] is not a positive debit AR item.',
            'Target entry [:entry] is not a positive credit AP item.',
            'Settlement amount [:amount] exceeds target entry remaining debit [:remaining].',
            'Settlement amount [:amount] exceeds target entry remaining credit [:remaining].',
            'Reversal reason is required.',
            'Settlement :id is already reversed.',
            'Settlement :id cannot be reversed from status :status.',
        ];
    }

    /**
     * @return list<string>
     */
    private function arApSettlementServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Accounting/ReceivableEntrySettlementService.php'),
            app_path('Application/Accounting/PayableEntrySettlementService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function arApReceiptPaymentOpeningBalanceServiceErrorMessages(): array
    {
        return [
            'Only draft receipts can be cancelled.',
            'Only draft payments can be cancelled.',
            'Only draft opening balances can be cancelled.',
            'Financial period must belong to the selected fiscal year.',
            'Financial period is closed.',
        ];
    }

    /**
     * @return list<string>
     */
    private function arApReceiptPaymentOpeningBalanceServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/Accounting/CustomerReceiptService.php'),
            app_path('Application/Accounting/SupplierPaymentService.php'),
            app_path('Application/Accounting/CustomerOpeningBalanceService.php'),
            app_path('Application/Accounting/SupplierOpeningBalanceService.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function invoiceRevisionAndCurrencyInputErrorMessages(): array
    {
        return [
            'Customer Credit Note does not exist.',
            'Sales Return does not exist.',
            'Revisions can only be generated for posted customer invoices.',
            'Customer invoice has no lines to revise.',
            'Currency is required.',
            'Currency must be a 3-letter ISO code.',
            ':source currency is required.',
        ];
    }

    /**
     * @return list<string>
     */
    private function invoiceRevisionAndCurrencyInputSourceFiles(): array
    {
        return [
            app_path('Application/Sales/CustomerInvoiceRevisionService.php'),
            app_path('Application/Support/CurrencyInput.php'),
        ];
    }

    /**
     * @return list<string>
     */
    private function reportExportErrorMessages(): array
    {
        return [
            'Bank account ID is required for export.',
            'Cash account ID is required for export.',
            'Customer ID is required for export.',
            'Supplier ID is required for export.',
            'Unable to open CSV output stream.',
        ];
    }

    /**
     * @return list<string>
     */
    private function reportExportErrorSourceFiles(): array
    {
        return [
            app_path('Http/Controllers/Reports/BankBookController.php'),
            app_path('Http/Controllers/Reports/CashBookController.php'),
            app_path('Http/Controllers/Reports/CustomerStatementController.php'),
            app_path('Http/Controllers/Reports/SupplierStatementController.php'),
            app_path('Application/Reports/CsvReportResponse.php'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function inertiaPageSourceFiles(): array
    {
        $root = resource_path('js/Pages');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'tsx') {
                continue;
            }

            $path = $file->getPathname();
            $relativePath = str_replace('\\', '/', substr($path, strlen($root.DIRECTORY_SEPARATOR)));
            $files[$relativePath] = (string) file_get_contents($path);
        }

        ksort($files);

        return $files;
    }

    /**
     * @return list<int>
     */
    private function missingAccessibleButtonLines(string $source): array
    {
        $missing = [];
        $offset = 0;

        while (($buttonStart = strpos($source, '<button', $offset)) !== false) {
            [$tag, $nextOffset] = $this->readOpeningTag($source, $buttonStart);

            if (! str_contains($tag, 'aria-label=') && ! str_contains($tag, 'title=')) {
                $missing[] = substr_count(substr($source, 0, $buttonStart), "\n") + 1;
            }

            $offset = $nextOffset;
        }

        return $missing;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readOpeningTag(string $source, int $start): array
    {
        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($index = $start + 7; $index < $length; $index++) {
            $char = $source[$index];
            $previous = $index > 0 ? $source[$index - 1] : '';

            if ($quote !== null) {
                if ($char === $quote && $previous !== '\\') {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;

                continue;
            }

            if ($char === '{') {
                $depth++;

                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);

                continue;
            }

            if ($char === '>' && $depth === 0 && $previous !== '=') {
                return [substr($source, $start, $index - $start + 1), $index + 1];
            }
        }

        return [substr($source, $start), $length];
    }

    /**
     * @return list<string>
     */
    private function fixedAssetServiceErrorMessages(): array
    {
        return [
            'Category code [:code] already exists.',
            'Useful life must be a positive number of months.',
            'Salvage value cannot be negative.',
            'Cannot delete category with linked assets.',
            'Selected asset category is inactive.',
            'Currency [:code] is missing.',
            'Asset cost must be greater than zero.',
            'Salvage value cannot exceed historical cost.',
            'Opening accumulated depreciation cannot be negative.',
            'Opening accumulated depreciation cannot exceed depreciable base (Cost - Salvage).',
            'Fixed assets must be created as draft and activated through capitalization.',
            'Asset number [:asset_number] is already in use.',
            'Only draft assets can be edited.',
            'Fixed assets must be activated through capitalization.',
            'Only draft assets can be deleted.',
            'Assets with movement history cannot be deleted.',
            'Invalid capitalization mode [:mode].',
            'Asset is already capitalized.',
            'Only draft assets can be capitalized.',
            'Only manually capitalized active assets can be reversed.',
            'Only active fixed assets can have depreciation schedules.',
            'Only straight-line depreciation is supported in Phase 6.',
            'Asset useful life in months must be greater than zero.',
            'No financial period is available for the depreciation schedule start date.',
            'Insufficient fiscal periods configured. Required: [:required], Available: [:available].',
            'No planned active depreciation schedules found for this period.',
            'Total depreciation amount for selected period must be greater than zero.',
            'Depreciation runs must be posted one currency at a time.',
            'Disposal has no linked journal entry to reverse.',
            'Only active assets can be disposed. Current status: [:status].',
            'Disposal type must be sale, scrap, or retirement.',
            'Proceeds amount cannot be negative.',
            'Cannot dispose an asset before already posted depreciation schedule periods. Reverse those depreciation runs first.',
            'Prior depreciation schedule periods must be posted or resolved before disposal.',
            'Locations used by assets or movement history cannot be deleted. Deactivate the location instead.',
            'Selected branch is inactive or missing.',
            'Disposed assets cannot be moved.',
            'The selected destination matches the current asset position.',
            'Selected location is inactive or missing.',
            'Selected location belongs to a different branch.',
            'Date is required.',
            'Invalid date.',
        ];
    }

    /**
     * @return list<string>
     */
    private function fixedAssetServiceErrorSourceFiles(): array
    {
        return [
            app_path('Application/FixedAssets/FixedAssetCapitalizationService.php'),
            app_path('Application/FixedAssets/FixedAssetCategoryService.php'),
            app_path('Application/FixedAssets/FixedAssetDepreciationEngineService.php'),
            app_path('Application/FixedAssets/FixedAssetDepreciationPostingService.php'),
            app_path('Application/FixedAssets/FixedAssetDisposalPostingService.php'),
            app_path('Application/FixedAssets/FixedAssetLocationService.php'),
            app_path('Application/FixedAssets/FixedAssetMovementService.php'),
            app_path('Application/FixedAssets/FixedAssetRegisterService.php'),
        ];
    }
}
