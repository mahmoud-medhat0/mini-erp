<?php

namespace Tests\Feature;

use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\SupplierPaymentService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Sales\SalesReturnService;
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
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PayableEntry;
use App\Models\Product;
use App\Models\Project;
use App\Models\ReceivableEntry;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovementLedger;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Security\RouteAuthorizationAuditor;
use Database\Seeders\AccountantAcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Support\AccountantWorkflowScenario;
use Tests\TestCase;

class Phase19AccountantAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $acceptanceUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountantAcceptanceSeeder::class);

        $this->acceptanceUser = User::query()->where('email', 'accept.accountant@example.com')->first()
            ?? User::query()->firstOrFail();
    }

    public function test_accountant_acceptance_seeder_runs_successfully_and_populates_master_data(): void
    {
        // 1. Acceptance User
        $user = $this->acceptanceUser;
        $this->assertNotNull($user, 'Acceptance user or administrator must exist.');
        $this->assertTrue((bool) $user->is_active, 'Acceptance user must be active.');

        // 2. Currencies and GL Accounts
        $bankGl = Account::query()->where('code', '1110')->first();
        $cashGl = Account::query()->where('code', '1100')->first();
        $arGl = Account::query()->where('code', '1200')->first();
        $apGl = Account::query()->where('code', '2100')->first();

        $this->assertNotNull($bankGl, 'GL Account 1110 (Bank Account GL) must exist.');
        $this->assertNotNull($cashGl, 'GL Account 1100 (Cash Clearing GL) must exist.');
        $this->assertNotNull($arGl, 'GL Account 1200 (AR Control GL) must exist.');
        $this->assertNotNull($apGl, 'GL Account 2100 (AP Control GL) must exist.');

        // 3. Fiscal Year & Financial Periods
        $currentYear = (int) date('Y');
        $fiscalYear = FiscalYear::query()->where('year', $currentYear)->first();
        $this->assertNotNull($fiscalYear, "Fiscal Year {$currentYear} must exist.");
        $this->assertEquals('open', $fiscalYear->status);

        $openPeriodsCount = FinancialPeriod::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('status', ['open', 'reopened'])
            ->count();
        $this->assertGreaterThanOrEqual(1, $openPeriodsCount, 'At least one open financial period must exist.');

        // 4. Operational Branches
        $branchHO = Branch::query()->where('code', 'ACC-HO')->first();
        $branchAlex = Branch::query()->where('code', 'ACC-ALX')->first();
        $this->assertNotNull($branchHO, 'Acceptance Head Office Branch ACC-HO must exist.');
        $this->assertNotNull($branchAlex, 'Acceptance Alexandria Branch ACC-ALX must exist.');
        $this->assertTrue($branchHO->is_active);
        $this->assertTrue($branchAlex->is_active);

        // 5. Warehouses & Stock Locations
        $whMain = Warehouse::query()->where('code', 'ACC-WH-MAIN')->first();
        $whAlex = Warehouse::query()->where('code', 'ACC-WH-ALX')->first();
        $this->assertNotNull($whMain, 'Main warehouse ACC-WH-MAIN must exist.');
        $this->assertNotNull($whAlex, 'Alexandria warehouse ACC-WH-ALX must exist.');
        $this->assertEquals($branchHO->id, $whMain->branch_id);
        $this->assertEquals($branchAlex->id, $whAlex->branch_id);

        $locMain = StockLocation::query()->where('code', 'ACC-LOC-MAIN-01')->first();
        $locAlex = StockLocation::query()->where('code', 'ACC-LOC-ALX-01')->first();
        $this->assertNotNull($locMain, 'Stock location ACC-LOC-MAIN-01 must exist.');
        $this->assertNotNull($locAlex, 'Stock location ACC-LOC-ALX-01 must exist.');

        // 6. Customer & Supplier
        $customer = Customer::query()->where('code', 'ACC-CUST-001')->first();
        $supplier = Supplier::query()->where('code', 'ACC-SUPP-001')->first();
        $this->assertNotNull($customer, 'Acceptance customer ACC-CUST-001 must exist.');
        $this->assertNotNull($supplier, 'Acceptance supplier ACC-SUPP-001 must exist.');
        $this->assertEquals('active', $customer->status);
        $this->assertEquals('active', $supplier->status);
        $this->assertNotEmpty($customer->tax_number);
        $this->assertNotEmpty($supplier->tax_number);

        // 7. Products: Stock, Service, Non-Stock
        $stockProduct = Product::query()->where('code', 'ACC-PRD-STOCK-01')->first();
        $serviceProduct = Product::query()->where('code', 'ACC-PRD-SERV-01')->first();
        $nonStockProduct = Product::query()->where('code', 'ACC-PRD-NONSTOCK-01')->first();

        $this->assertNotNull($stockProduct, 'Stock product ACC-PRD-STOCK-01 must exist.');
        $this->assertNotNull($serviceProduct, 'Service product ACC-PRD-SERV-01 must exist.');
        $this->assertNotNull($nonStockProduct, 'Non-stock product ACC-PRD-NONSTOCK-01 must exist.');
        $this->assertEquals('stock', $stockProduct->type);
        $this->assertEquals('service', $serviceProduct->type);
        $this->assertEquals('non_stock', $nonStockProduct->type);
        $this->assertEquals('active', $stockProduct->status);
        $this->assertEquals('active', $serviceProduct->status);

        // 8. Tax Codes & Rates
        $taxCode = TaxCode::query()->where('code', 'VAT_STD_14')->first();
        $this->assertNotNull($taxCode, 'Standard VAT 14% tax code must exist.');
        $this->assertTrue($taxCode->is_active);
        $this->assertTrue($taxCode->rates()->where('is_active', true)->exists());

        // 9. Cash and Bank Accounts
        $cashAccount = CashAccount::query()->where('code', 'ACC-CASH-01')->first();
        $bankAccount = BankAccount::query()->where('code', 'ACC-BANK-01')->first();
        $this->assertNotNull($cashAccount, 'Cash account ACC-CASH-01 must exist.');
        $this->assertNotNull($bankAccount, 'Bank account ACC-BANK-01 must exist.');
        $this->assertEquals($cashGl->id, $cashAccount->gl_account_id);
        $this->assertEquals($bankGl->id, $bankAccount->gl_account_id);
        $this->assertTrue($cashAccount->is_active);
        $this->assertTrue($bankAccount->is_active);

        // 10. Multi-dimensional fixtures: Project, Cost Center, Budget
        $project = Project::query()->where('code', 'ACC-PRJ-01')->first();
        $costCenter = CostCenter::query()->where('code', 'ACC-CC-01')->first();
        $budget = Budget::query()->where('code', 'ACC-BDG-2026')->first();

        $this->assertNotNull($project, 'Project ACC-PRJ-01 must exist.');
        $this->assertNotNull($costCenter, 'Cost Center ACC-CC-01 must exist.');
        $this->assertNotNull($budget, 'Budget ACC-BDG-2026 must exist.');
        $this->assertTrue($project->is_active);
        $this->assertTrue($costCenter->is_active);

        // 11. Fixed Asset Category & Employee
        $faCat = FixedAssetCategory::query()->where('code', 'ACC-FAC-01')->first();
        $emp = Employee::query()->where('code', 'ACC-EMP-001')->first();
        $this->assertNotNull($faCat, 'Fixed asset category ACC-FAC-01 must exist.');
        $this->assertNotNull($emp, 'Employee ACC-EMP-001 must exist.');
    }

    public function test_accountant_acceptance_seeder_is_strictly_idempotent_on_repeated_runs(): void
    {
        $countsFirstRun = [
            'branches' => Branch::query()->where('code', 'like', 'ACC-%')->count(),
            'warehouses' => Warehouse::query()->where('code', 'like', 'ACC-%')->count(),
            'locations' => StockLocation::query()->where('code', 'like', 'ACC-%')->count(),
            'customers' => Customer::query()->where('code', 'like', 'ACC-%')->count(),
            'suppliers' => Supplier::query()->where('code', 'like', 'ACC-%')->count(),
            'products' => Product::query()->where('code', 'like', 'ACC-%')->count(),
            'cash_accounts' => CashAccount::query()->where('code', 'like', 'ACC-%')->count(),
            'bank_accounts' => BankAccount::query()->where('code', 'like', 'ACC-%')->count(),
            'projects' => Project::query()->where('code', 'like', 'ACC-%')->count(),
            'cost_centers' => CostCenter::query()->where('code', 'like', 'ACC-%')->count(),
            'budgets' => Budget::query()->where('code', 'like', 'ACC-%')->count(),
            'fixed_asset_categories' => FixedAssetCategory::query()->where('code', 'like', 'ACC-%')->count(),
            'employees' => Employee::query()->where('code', 'like', 'ACC-%')->count(),
            'accounts_1110' => Account::query()->where('code', '1110')->count(),
            'fiscal_years' => FiscalYear::query()->where('year', (int) date('Y'))->count(),
            'financial_periods' => FinancialPeriod::query()->count(),
        ];

        // Second run
        $this->seed(AccountantAcceptanceSeeder::class);

        $countsSecondRun = [
            'branches' => Branch::query()->where('code', 'like', 'ACC-%')->count(),
            'warehouses' => Warehouse::query()->where('code', 'like', 'ACC-%')->count(),
            'locations' => StockLocation::query()->where('code', 'like', 'ACC-%')->count(),
            'customers' => Customer::query()->where('code', 'like', 'ACC-%')->count(),
            'suppliers' => Supplier::query()->where('code', 'like', 'ACC-%')->count(),
            'products' => Product::query()->where('code', 'like', 'ACC-%')->count(),
            'cash_accounts' => CashAccount::query()->where('code', 'like', 'ACC-%')->count(),
            'bank_accounts' => BankAccount::query()->where('code', 'like', 'ACC-%')->count(),
            'projects' => Project::query()->where('code', 'like', 'ACC-%')->count(),
            'cost_centers' => CostCenter::query()->where('code', 'like', 'ACC-%')->count(),
            'budgets' => Budget::query()->where('code', 'like', 'ACC-%')->count(),
            'fixed_asset_categories' => FixedAssetCategory::query()->where('code', 'like', 'ACC-%')->count(),
            'employees' => Employee::query()->where('code', 'like', 'ACC-%')->count(),
            'accounts_1110' => Account::query()->where('code', '1110')->count(),
            'fiscal_years' => FiscalYear::query()->where('year', (int) date('Y'))->count(),
            'financial_periods' => FinancialPeriod::query()->count(),
        ];

        $this->assertEquals($countsFirstRun, $countsSecondRun, 'Seeder must produce identical counts on repeated executions.');
        $this->assertEquals(2, $countsFirstRun['branches']);
        $this->assertEquals(2, $countsFirstRun['warehouses']);
        $this->assertEquals(2, $countsFirstRun['locations']);
        $this->assertEquals(1, $countsFirstRun['customers']);
        $this->assertEquals(1, $countsFirstRun['suppliers']);
        $this->assertEquals(3, $countsFirstRun['products']);
        $this->assertEquals(1, $countsFirstRun['cash_accounts']);
        $this->assertEquals(1, $countsFirstRun['bank_accounts']);
        $this->assertEquals(1, $countsFirstRun['projects']);
        $this->assertEquals(1, $countsFirstRun['cost_centers']);
        $this->assertEquals(1, $countsFirstRun['budgets']);
    }

    public function test_fixture_contains_branch_capable_operational_data_without_tenant_company_scope(): void
    {
        $branchHO = Branch::query()->where('code', 'ACC-HO')->firstOrFail();
        $whMain = Warehouse::query()->where('code', 'ACC-WH-MAIN')->firstOrFail();
        $cashAccount = CashAccount::query()->where('code', 'ACC-CASH-01')->firstOrFail();
        $bankAccount = BankAccount::query()->where('code', 'ACC-BANK-01')->firstOrFail();

        // Operational dimension relations
        $this->assertEquals($branchHO->id, $whMain->branch_id);
        $this->assertEquals($branchHO->id, $cashAccount->branch_id);
        $this->assertEquals($branchHO->id, $bankAccount->branch_id);

        // Verify no forbidden multi-tenancy columns exist on branch table
        $this->assertFalse(
            Schema::hasColumn('branch', 'company_id'),
            'branch table must not contain company_id'
        );
        $this->assertFalse(
            Schema::hasColumn('branch', 'tenant_id'),
            'branch table must not contain tenant_id'
        );
    }

    public function test_no_secrets_are_stored_in_acceptance_seeder(): void
    {
        $seederContent = file_get_contents(database_path('seeders/AccountantAcceptanceSeeder.php'));

        $forbiddenSecretPatterns = [
            '/api[_-]?key/i',
            '/bearer/i',
            '/bot[_-]?token/i',
            '/telegram/i',
            '/aws[_-]?key/i',
            '/BootstrapUserSeeder/',
            '/FirstUserSuperAdminSeeder/',
            '/User::factory/',
        ];

        foreach ($forbiddenSecretPatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression($pattern, $seederContent, "Seeder source code must not contain secret matching {$pattern}");
        }
    }

    public function test_no_forbidden_scope_terms_are_introduced_in_slice_1(): void
    {
        $seederContent = file_get_contents(database_path('seeders/AccountantAcceptanceSeeder.php'));

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
            $this->assertStringNotContainsString($term, $seederContent, "Seeder source code must not contain forbidden scope term: {$term}");
        }
    }

    public function test_procure_to_pay_workflow_produces_accurate_grni_inventory_ap_and_settlement(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        $po = $scenario['purchase_order'];
        $gr = $scenario['goods_receipt'];
        $bill = $scenario['supplier_bill'];
        $payment = $scenario['supplier_payment'];

        // PO assertions
        $this->assertEquals('confirmed', $po->status);
        $this->assertEquals(1000000, $po->total_minor); // 10,000.00 EGP

        // Goods Receipt & Inventory Costing assertions
        $this->assertEquals('confirmed', $gr->status);
        $grMovement = StockMovementLedger::query()
            ->where('source_type', 'goods_receipt')
            ->where('source_id', $gr->id)
            ->first();
        $this->assertNotNull($grMovement, 'Goods Receipt must create a StockMovementLedger entry.');
        $this->assertNotNull($grMovement->journal_entry_id);

        $grJournal = JournalEntry::query()->with('lines.account')->findOrFail($grMovement->journal_entry_id);
        $this->assertEquals('posted', $grJournal->status);

        // Dr 1400 (Inventory Asset) 10,000.00 EGP / Cr 2300 (GRNI) 10,000.00 EGP
        $invLine = $grJournal->lines->firstWhere('account.code', '1400');
        $grniLine = $grJournal->lines->firstWhere('account.code', '2300');
        $this->assertNotNull($invLine, 'GR must debit Inventory Asset 1400.');
        $this->assertNotNull($grniLine, 'GR must credit GRNI Clearing 2300.');
        $this->assertEquals(1000000, $invLine->debit_minor);
        $this->assertEquals(1000000, $grniLine->credit_minor);

        // Supplier Bill assertions
        $this->assertEquals('posted', $bill->status);
        $this->assertEquals(1000000, $bill->subtotal_minor); // 10,000.00 EGP
        $this->assertEquals(140000, $bill->tax_amount_minor); // 1,400.00 EGP (14% VAT)
        $this->assertEquals(1140000, $bill->total_minor); // 11,400.00 EGP

        $billJournal = JournalEntry::query()->with('lines.account')->findOrFail($bill->journal_entry_id);
        $this->assertEquals('posted', $billJournal->status);

        // Dr 2300 (GRNI) 10,000.00 / Dr 1300 (Input Tax) 1,400.00 / Cr 2100 (AP) 11,400.00
        $billGrniLine = $billJournal->lines->firstWhere('account.code', '2300');
        $billTaxLine = $billJournal->lines->firstWhere('account.code', '1300');
        $billApLine = $billJournal->lines->firstWhere('account.code', '2100');

        $this->assertNotNull($billGrniLine, 'Supplier Bill must debit GRNI 2300.');
        $this->assertNotNull($billTaxLine, 'Supplier Bill must debit Input Tax 1300.');
        $this->assertNotNull($billApLine, 'Supplier Bill must credit AP Control 2100.');
        $this->assertEquals(1000000, $billGrniLine->debit_minor);
        $this->assertEquals(140000, $billTaxLine->debit_minor);
        $this->assertEquals(1140000, $billApLine->credit_minor);

        // Supplier Payment assertions
        $this->assertEquals('posted', $payment->status);
        $this->assertEquals(1140000, $payment->amount_minor);

        $pmtJournal = JournalEntry::query()->with('lines.account')->findOrFail($payment->journal_entry_id);
        $this->assertEquals('posted', $pmtJournal->status);

        // Dr 2100 (AP) 11,400.00 / Cr 1110 (Bank Account) 11,400.00
        $pmtApLine = $pmtJournal->lines->firstWhere('account.code', '2100');
        $pmtBankLine = $pmtJournal->lines->firstWhere('account.code', '1110');

        $this->assertNotNull($pmtApLine, 'Supplier Payment must debit AP Control 2100.');
        $this->assertNotNull($pmtBankLine, 'Supplier Payment must credit Bank Account GL 1110.');
        $this->assertEquals(1140000, $pmtApLine->debit_minor);
        $this->assertEquals(1140000, $pmtBankLine->credit_minor);

        // Payable Subledger Settlement
        $billPayable = PayableEntry::query()->findOrFail($bill->payable_entry_id);
        $this->assertEquals(1140000, $billPayable->credit_minor);
        $this->assertNotEmpty($scenario['payable_allocations']);
        $this->assertEquals(1140000, $scenario['payable_allocations'][0]->amount_minor);
    }

    public function test_order_to_cash_workflow_produces_moving_average_cogs_revenue_ar_and_output_tax(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        $so = $scenario['sales_order'];
        $dn = $scenario['delivery_note'];
        $invoice = $scenario['customer_invoice'];

        // SO assertions
        $this->assertEquals('confirmed', $so->status);
        $this->assertEquals(600000, $so->total_minor); // 6,000.00 EGP (40 units @ 150.00)

        // Delivery Note & Moving Weighted Average COGS
        $this->assertEquals('confirmed', $dn->status);
        $dnMovement = StockMovementLedger::query()
            ->where('source_type', 'delivery_note')
            ->where('source_id', $dn->id)
            ->first();
        $this->assertNotNull($dnMovement, 'Delivery Note must create a StockMovementLedger entry.');
        $this->assertNotNull($dnMovement->journal_entry_id);

        $dnJournal = JournalEntry::query()->with('lines.account')->findOrFail($dnMovement->journal_entry_id);
        $this->assertEquals('posted', $dnJournal->status);

        // 40 units issued @ 100.00 EGP cost = 4,000.00 EGP (400,000 minor)
        // Dr 5500 (COGS) 4,000.00 / Cr 1400 (Inventory Asset) 4,000.00
        $cogsLine = $dnJournal->lines->firstWhere('account.code', '5500');
        $invLine = $dnJournal->lines->firstWhere('account.code', '1400');

        $this->assertNotNull($cogsLine, 'DN must debit COGS 5500.');
        $this->assertNotNull($invLine, 'DN must credit Inventory Asset 1400.');
        $this->assertEquals(400000, $cogsLine->debit_minor);
        $this->assertEquals(400000, $invLine->credit_minor);

        // Customer Invoice assertions
        $this->assertEquals('posted', $invoice->status);
        $this->assertEquals(600000, $invoice->subtotal_minor); // 6,000.00 EGP
        $this->assertEquals(84000, $invoice->tax_amount_minor); // 840.00 EGP (14% VAT)
        $this->assertEquals(684000, $invoice->total_minor); // 6,840.00 EGP

        $invJournal = JournalEntry::query()->with('lines.account')->findOrFail($invoice->journal_entry_id);
        $this->assertEquals('posted', $invJournal->status);

        // Dr 1200 (AR) 6,840.00 / Cr 4100 (Sales Revenue) 6,000.00 / Cr 2200 (Output Tax) 840.00
        $invArLine = $invJournal->lines->firstWhere('account.code', '1200');
        $invRevLine = $invJournal->lines->firstWhere('account.code', '4100');
        $invTaxLine = $invJournal->lines->firstWhere('account.code', '2200');

        $this->assertNotNull($invArLine, 'Invoice must debit AR Control 1200.');
        $this->assertNotNull($invRevLine, 'Invoice must credit Sales Revenue 4100.');
        $this->assertNotNull($invTaxLine, 'Invoice must credit Output Tax Payable 2200.');
        $this->assertEquals(684000, $invArLine->debit_minor);
        $this->assertEquals(600000, $invRevLine->credit_minor);
        $this->assertEquals(84000, $invTaxLine->credit_minor);

        // Receivable Subledger Entry
        $receivableEntry = ReceivableEntry::query()->findOrFail($invoice->receivable_entry_id);
        $this->assertEquals(684000, $receivableEntry->debit_minor);
    }

    public function test_sales_return_and_credit_note_workflow_restocks_inventory_reduces_cogs_and_adjusts_ar_vat(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        $salesReturn = $scenario['sales_return'];
        $creditNote = $scenario['customer_credit_note'];

        // Sales Return assertions
        $this->assertEquals('posted', $salesReturn->status);

        // 10 units restocked @ 100.00 EGP = 1,000.00 EGP (100,000 minor)
        // Check Stock Movement Ledger for Return
        $returnMovement = StockMovementLedger::query()
            ->where('source_type', 'sales_return')
            ->where('source_id', $salesReturn->id)
            ->first();

        $this->assertNotNull($returnMovement, 'Sales Return must create StockMovementLedger entry.');
        $this->assertEquals(10_000_000, $returnMovement->quantity_delta_e6); // +10 units
        $this->assertEquals(100000, $returnMovement->value_delta_minor); // +1,000.00 EGP

        // Return Journal: Dr 1400 (Inventory Asset) 1,000.00 / Cr 5500 (COGS) 1,000.00
        $retJournal = JournalEntry::query()->with('lines.account')->findOrFail($returnMovement->journal_entry_id);
        $retInvLine = $retJournal->lines->firstWhere('account.code', '1400');
        $retCogsLine = $retJournal->lines->firstWhere('account.code', '5500');

        $this->assertNotNull($retInvLine, 'Sales Return journal must debit Inventory Asset 1400.');
        $this->assertNotNull($retCogsLine, 'Sales Return journal must credit COGS 5500.');
        $this->assertEquals(100000, $retInvLine->debit_minor);
        $this->assertEquals(100000, $retCogsLine->credit_minor);

        // Customer Credit Note assertions
        $this->assertEquals('posted', $creditNote->status);
        $this->assertEquals(150000, $creditNote->subtotal_minor); // 1,500.00 EGP (10 units @ 150.00)
        $this->assertEquals(21000, $creditNote->tax_minor); // 210.00 EGP (14% VAT)
        $this->assertEquals(171000, $creditNote->total_minor); // 1,710.00 EGP

        $cnJournal = JournalEntry::query()->with('lines.account')->findOrFail($creditNote->journal_entry_id);
        $this->assertEquals('posted', $cnJournal->status);

        // Dr 4200 (Sales Returns) 1,500.00 / Dr 2200 (Output Tax) 210.00 / Cr 1200 (AR) 1,710.00
        $cnRetLine = $cnJournal->lines->firstWhere('account.code', '4200');
        $cnTaxLine = $cnJournal->lines->firstWhere('account.code', '2200');
        $cnArLine = $cnJournal->lines->firstWhere('account.code', '1200');

        $this->assertNotNull($cnRetLine, 'Credit Note must debit Sales Returns 4200.');
        $this->assertNotNull($cnTaxLine, 'Credit Note must debit Output Tax Payable 2200.');
        $this->assertNotNull($cnArLine, 'Credit Note must credit AR Control 1200.');
        $this->assertEquals(150000, $cnRetLine->debit_minor);
        $this->assertEquals(21000, $cnTaxLine->debit_minor);
        $this->assertEquals(171000, $cnArLine->credit_minor);

        // Credit Note Subledger Settlement
        $this->assertNotEmpty($scenario['credit_settlements']);
        $this->assertEquals(171000, $scenario['credit_settlements'][0]->amount_minor);
    }

    public function test_customer_receipt_and_settlement_clears_open_receivable(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        $receipt = $scenario['customer_receipt'];
        $this->assertEquals('posted', $receipt->status);
        $this->assertEquals(513000, $receipt->amount_minor); // 5,130.00 EGP (6,840 - 1,710)

        $recJournal = JournalEntry::query()->with('lines.account')->findOrFail($receipt->journal_entry_id);
        $this->assertEquals('posted', $recJournal->status);

        // Dr 1110 (Bank Account GL) 5,130.00 / Cr 1200 (AR Control) 5,130.00
        $recBankLine = $recJournal->lines->firstWhere('account.code', '1110');
        $recArLine = $recJournal->lines->firstWhere('account.code', '1200');

        $this->assertNotNull($recBankLine, 'Customer Receipt must debit Bank Account GL 1110.');
        $this->assertNotNull($recArLine, 'Customer Receipt must credit AR Control 1200.');
        $this->assertEquals(513000, $recBankLine->debit_minor);
        $this->assertEquals(513000, $recArLine->credit_minor);

        // Receivable Allocation
        $this->assertNotEmpty($scenario['receipt_allocations']);
        $this->assertEquals(513000, $scenario['receipt_allocations'][0]->amount_minor);
    }

    public function test_vat_register_summary_and_gl_reconciliation_are_fully_reconciled(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        $vatRegister = $scenario['reports']['vat_register'];
        $vatSummary = $scenario['reports']['vat_summary'];
        $vatRecon = $scenario['reports']['vat_reconciliation'];

        // Output Tax: Invoice (+840.00) + Credit Note (-210.00) = Net Output 630.00 EGP (63,000 minor)
        $invRow = collect($vatRegister['rows'])->firstWhere('document_type', 'customer_invoice');
        $cnRow = collect($vatRegister['rows'])->firstWhere('document_type', 'customer_credit_note');
        $billRow = collect($vatRegister['rows'])->firstWhere('document_type', 'supplier_bill');

        $this->assertNotNull($invRow);
        $this->assertNotNull($cnRow);
        $this->assertNotNull($billRow);

        $this->assertEquals(84000, $invRow['tax_amount_minor']);
        $this->assertEquals(-21000, $cnRow['tax_amount_minor']);
        $this->assertEquals(140000, $billRow['tax_amount_minor']);

        $this->assertEquals(63000, $vatRegister['summary']['total_output_tax_minor']);
        $this->assertEquals(140000, $vatRegister['summary']['total_input_tax_minor']);
        $this->assertEquals(-77000, $vatRegister['summary']['net_vat_payable_minor']);

        // VAT Summary Report consistency
        $this->assertNotEmpty($vatSummary['output_vat_breakdown']);
        $this->assertNotEmpty($vatSummary['input_vat_breakdown']);
        $this->assertEquals(63000, $vatSummary['summary']['total_output_tax_minor']);
        $this->assertEquals(140000, $vatSummary['summary']['total_input_tax_minor']);

        // VAT to GL Reconciliation
        $this->assertTrue($vatRecon['is_reconciled'], 'VAT register must reconcile with GL tax accounts.');
        $this->assertEquals(0, $vatRecon['output_tax_difference_minor']);
        $this->assertEquals(0, $vatRecon['input_tax_difference_minor']);
        $this->assertEquals(0, $vatRecon['net_vat_difference_minor']);
    }

    public function test_general_ledger_trial_balance_and_financial_reports_remain_balanced_and_consistent(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        // 1. Trial Balance Check
        $tb = $scenario['reports']['trial_balance'];
        $this->assertTrue($tb['is_balanced'], 'General Ledger Trial Balance must be balanced.');
        $this->assertEquals(1290000, $tb['total_debit']); // 12,900.00 EGP
        $this->assertEquals(1290000, $tb['total_credit']); // 12,900.00 EGP

        // 2. AR to GL Reconciliation Check (AR subledger balance matches GL Account 1200)
        $arRecon = $scenario['reports']['ar_reconciliation'];
        $this->assertTrue($arRecon['is_reconciled'], 'AR subledger must reconcile with GL AR Control 1200.');
        $this->assertEquals(0, $arRecon['difference_minor']);

        // 3. AP to GL Reconciliation Check (AP subledger balance matches GL Account 2100)
        $apRecon = $scenario['reports']['ap_reconciliation'];
        $this->assertTrue($apRecon['is_reconciled'], 'AP subledger must reconcile with GL AP Control 2100.');
        $this->assertEquals(0, $apRecon['difference_minor']);

        // 4. Income Statement Check (Revenue 6,000 - Returns 1,500 - COGS 3,000 = Net Income 1,500.00 EGP)
        $pnl = $scenario['reports']['income_statement'];
        $this->assertEquals(600000, $pnl['summary']['total_revenue_minor']);
        $this->assertEquals(150000, $pnl['summary']['total_contra_revenue_minor']);
        $this->assertEquals(450000, $pnl['summary']['net_revenue_minor']);
        $this->assertEquals(300000, $pnl['summary']['total_cogs_minor']);
        $this->assertEquals(150000, $pnl['summary']['gross_profit_minor']);
        $this->assertEquals(150000, $pnl['summary']['net_income_minor']);

        // 5. Balance Sheet Check
        $bs = $scenario['reports']['balance_sheet'];
        $this->assertTrue($bs['summary']['is_balanced'], 'Balance Sheet report must be balanced.');
        $this->assertEquals(213000, $bs['summary']['total_assets_minor']); // 2,130.00 EGP
        $this->assertEquals(213000, $bs['summary']['total_liabilities_and_equity_minor']); // 2,130.00 EGP
        $this->assertEquals(0, $bs['summary']['imbalance_minor']);
    }

    public function test_end_to_end_full_accountant_workflow_scenario_executes_completely(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        // Verify Stock Balance after complete scenario
        /** @var StockBalance $stockBalance */
        $stockBalance = StockBalance::query()
            ->where('warehouse_id', $scenario['warehouse']->id)
            ->where('product_id', $scenario['product']->id)
            ->firstOrFail();

        // 100 received - 40 delivered + 10 returned = 70 units @ 100.00 EGP = 7,000.00 EGP (700,000 minor)
        $this->assertEquals(70_000_000, $stockBalance->quantity_e6);
        $this->assertEquals(700000, $stockBalance->valuation_amount_minor);
        $this->assertEquals(10_000, $stockBalance->avg_unit_cost_e6); // 100.00 EGP (10,000 minor)

        // Verify Total Journal Entries and Ledger Entries
        $postedJournalsCount = JournalEntry::query()->where('status', 'posted')->count();
        $this->assertEquals(8, $postedJournalsCount, 'Workflow scenario must generate exactly 8 posted journals (GR, Bill, Payment, DN, Invoice, Return, Credit Note, Receipt).');

        $ledgerEntriesCount = LedgerEntry::query()->count();
        $this->assertGreaterThanOrEqual(14, $ledgerEntriesCount, 'Workflow scenario must generate balanced double-entry ledger records.');
    }

    public function test_acceptance_scenario_is_idempotent_and_protects_against_duplicate_postings(): void
    {
        $scenario = AccountantWorkflowScenario::run($this->acceptanceUser);

        $initialJournalCount = JournalEntry::query()->where('status', 'posted')->count();
        $initialLedgerCount = LedgerEntry::query()->count();

        // Re-post all posted documents (must be idempotent no-op)
        $billService = app(SupplierBillService::class);
        $repostedBill = $billService->post($scenario['supplier_bill']->id, $this->acceptanceUser->id);
        $this->assertEquals($scenario['supplier_bill']->id, $repostedBill->id);

        $paymentService = app(SupplierPaymentService::class);
        $repostedPayment = $paymentService->post($scenario['supplier_payment']->id, $this->acceptanceUser->id);
        $this->assertEquals($scenario['supplier_payment']->id, $repostedPayment->id);

        $invoiceService = app(CustomerInvoiceService::class);
        $repostedInvoice = $invoiceService->post($scenario['customer_invoice']->id, $this->acceptanceUser->id);
        $this->assertEquals($scenario['customer_invoice']->id, $repostedInvoice->id);

        $returnService = app(SalesReturnService::class);
        $repostedReturn = $returnService->post($scenario['sales_return']->id, $this->acceptanceUser->id);
        $this->assertEquals($scenario['sales_return']->id, $repostedReturn->id);

        $creditNoteService = app(CustomerCreditNoteService::class);
        $repostedCredit = $creditNoteService->post($scenario['customer_credit_note']->id, $this->acceptanceUser->id);
        $this->assertEquals($scenario['customer_credit_note']->id, $repostedCredit->id);

        $receiptService = app(CustomerReceiptService::class);
        $repostedReceipt = $receiptService->post($scenario['customer_receipt']->id, $this->acceptanceUser->id);
        $this->assertEquals($scenario['customer_receipt']->id, $repostedReceipt->id);

        // Verify no duplicate journals or ledger entries were created
        $finalJournalCount = JournalEntry::query()->where('status', 'posted')->count();
        $finalLedgerCount = LedgerEntry::query()->count();

        $this->assertEquals($initialJournalCount, $finalJournalCount, 'Re-posting documents must not create duplicate journal entries.');
        $this->assertEquals($initialLedgerCount, $finalLedgerCount, 'Re-posting documents must not create duplicate ledger entries.');
    }

    public function test_no_forbidden_scope_terms_are_introduced_in_slice_2(): void
    {
        $supportContent = file_get_contents(base_path('tests/Support/AccountantWorkflowScenario.php'));
        $seederContent = file_get_contents(database_path('seeders/AccountantAcceptanceSeeder.php'));

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
            $this->assertStringNotContainsString($term, $supportContent, "Support file must not contain forbidden scope term: {$term}");
            $this->assertStringNotContainsString($term, $seederContent, "Seeder file must not contain forbidden scope term: {$term}");
        }
    }

    public function test_super_admin_persona_can_access_all_representative_acceptance_routes(): void
    {
        $this->withoutVite();
        $superAdmin = $this->createPersonaUser('SUPER_ADMIN');

        $routes = [
            '/dashboard',
            '/settings',
            '/settings/company',
            '/settings/branches',
            '/settings/users',
            '/audit-log',
            '/accounting',
            '/accounting/coa',
            '/accounting/journal',
            '/accounting/ledger',
            '/accounting/trial-balance',
            '/accounting/periods',
            '/customers',
            '/suppliers',
            '/cash-accounts',
            '/bank-accounts',
            '/treasury-transfers',
            '/customer-receipts',
            '/supplier-payments',
            '/incoming-cheques',
            '/outgoing-cheques',
            '/bank-reconciliations',
            '/reports',
            '/reports/balance-sheet',
            '/reports/income-statement',
            '/reports/ar-aging',
            '/reports/ap-aging',
            '/reports/vat-summary',
            '/sales/orders',
            '/sales/delivery-notes',
            '/sales/invoices',
            '/sales/returns',
            '/sales/credit-notes',
            '/purchasing/orders',
            '/purchasing/goods-receipts',
            '/purchasing/bills',
            '/purchasing/returns',
            '/purchasing/adjustment-notes',
            '/inventory/warehouses',
            '/inventory/stock-balances',
            '/inventory/transfers',
            '/inventory/stock-counts',
            '/inventory/adjustments',
            '/fixed-assets',
            '/fixed-assets-depreciation-runs',
            '/fixed-assets-disposals',
            '/expenses',
            '/expenses/prepaids',
            '/expenses/accruals',
            '/payroll/employees',
            '/payroll/runs',
            '/taxes/codes',
            '/taxes/periods',
            '/projects',
            '/cost-centers',
            '/budgeting/budgets',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($superAdmin)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Super Admin persona must be able to access [{$route}], got status {$response->status()}."
            );
        }
    }

    public function test_accountant_persona_can_access_financial_operational_and_reporting_views(): void
    {
        $this->withoutVite();
        $accountant = $this->createPersonaUser('ACCOUNTANT');

        $allowedRoutes = [
            '/dashboard',
            '/accounting',
            '/accounting/coa',
            '/accounting/journal',
            '/accounting/ledger',
            '/accounting/trial-balance',
            '/accounting/periods',
            '/customers',
            '/suppliers',
            '/cash-accounts',
            '/bank-accounts',
            '/treasury-transfers',
            '/customer-receipts',
            '/supplier-payments',
            '/incoming-cheques',
            '/outgoing-cheques',
            '/bank-reconciliations',
            '/reports',
            '/reports/balance-sheet',
            '/reports/income-statement',
            '/reports/vat-summary',
            '/reports/ar-aging',
            '/reports/ap-aging',
            '/reports/vat-register',
            '/reports/vat-gl-reconciliation',
            '/fixed-assets',
            '/fixed-assets-depreciation-runs',
            '/fixed-assets-disposals',
            '/expenses',
            '/expenses/prepaids',
            '/expenses/accruals',
            '/taxes/codes',
            '/taxes/periods',
            '/budgeting/budgets',
            '/budgeting/variance',
        ];

        foreach ($allowedRoutes as $route) {
            $response = $this->actingAs($accountant)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Accountant persona must be able to access [{$route}], got status {$response->status()}."
            );
        }

        // Accountant must NOT access restricted admin/payroll routes or sensitive capabilities
        $forbiddenRoutes = [
            '/settings/company',
            '/settings/branches',
            '/settings/users',
            '/payroll/runs',
            '/payroll/employees',
        ];

        foreach ($forbiddenRoutes as $route) {
            $response = $this->actingAs($accountant)->get($route);
            $this->assertEquals(
                403,
                $response->status(),
                "Accountant persona must be forbidden from [{$route}], got status {$response->status()}."
            );
        }

        // Sensitive capability test: filing tax return without taxes.file permission
        $response = $this->actingAs($accountant)->post('/taxes/returns/1/file');
        $this->assertEquals(403, $response->status(), 'Accountant must not have sensitive taxes.file capability.');
    }

    public function test_sales_persona_can_access_sales_workflows_and_is_blocked_from_unauthorized_areas(): void
    {
        $this->withoutVite();
        $salesUser = $this->createPersonaUser('SALES');

        $allowedRoutes = [
            '/dashboard',
            '/customers',
            '/sales/orders',
            '/sales/delivery-notes',
            '/sales/invoices',
            '/sales/returns',
            '/sales/credit-notes',
            '/customer-opening-balances',
            '/customer-receipts',
            '/receivable-allocations',
        ];

        foreach ($allowedRoutes as $route) {
            $response = $this->actingAs($salesUser)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Sales persona must be able to access [{$route}], got status {$response->status()}."
            );
        }

        $forbiddenRoutes = [
            '/payroll/runs',
            '/payroll/employees',
            '/settings/company',
            '/settings/branches',
            '/settings/users',
            '/settings/numbering',
            '/accounting',
            '/accounting/journal',
            '/accounting/ledger',
            '/accounting/trial-balance',
            '/accounting/periods',
            '/fixed-assets',
            '/purchasing/orders',
            '/purchasing/bills',
            '/audit-log',
            '/reports',
        ];

        foreach ($forbiddenRoutes as $route) {
            $response = $this->actingAs($salesUser)->get($route);
            $this->assertEquals(
                403,
                $response->status(),
                "Sales persona must be forbidden from [{$route}], got status {$response->status()}."
            );
        }

        // Sales persona cannot post invoices directly (requires sales.post + view_financials)
        $response = $this->actingAs($salesUser)->post('/sales/invoices/1/post');
        $this->assertEquals(403, $response->status(), 'Sales persona must not be authorized to execute accounting posting.');
    }

    public function test_purchasing_persona_can_access_purchasing_workflows_and_is_blocked_from_unauthorized_areas(): void
    {
        $this->withoutVite();
        $purchasingUser = $this->createPersonaUser('PURCHASING');

        $allowedRoutes = [
            '/dashboard',
            '/suppliers',
            '/purchasing/orders',
            '/purchasing/goods-receipts',
            '/purchasing/bills',
            '/purchasing/returns',
            '/purchasing/adjustment-notes',
            '/inventory/stock-balances',
            '/inventory/warehouses',
            '/supplier-opening-balances',
            '/supplier-payments',
            '/payable-allocations',
        ];

        foreach ($allowedRoutes as $route) {
            $response = $this->actingAs($purchasingUser)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Purchasing persona must be able to access [{$route}], got status {$response->status()}."
            );
        }

        $forbiddenRoutes = [
            '/payroll/runs',
            '/payroll/employees',
            '/settings/company',
            '/settings/branches',
            '/settings/users',
            '/settings/numbering',
            '/accounting',
            '/accounting/journal',
            '/accounting/ledger',
            '/accounting/trial-balance',
            '/accounting/periods',
            '/fixed-assets',
            '/sales/orders',
            '/sales/invoices',
            '/audit-log',
            '/reports',
        ];

        foreach ($forbiddenRoutes as $route) {
            $response = $this->actingAs($purchasingUser)->get($route);
            $this->assertEquals(
                403,
                $response->status(),
                "Purchasing persona must be forbidden from [{$route}], got status {$response->status()}."
            );
        }

        // Purchasing persona cannot post bills directly (requires purchasing.post + view_financials)
        $response = $this->actingAs($purchasingUser)->post('/purchasing/bills/1/post');
        $this->assertEquals(403, $response->status(), 'Purchasing persona must not be authorized to execute accounting posting.');
    }

    public function test_inventory_persona_can_access_inventory_workflows_and_is_blocked_from_unauthorized_areas(): void
    {
        $this->withoutVite();
        $inventoryUser = $this->createPersonaUser('INVENTORY');

        $allowedRoutes = [
            '/dashboard',
            '/inventory/warehouses',
            '/inventory/stock-balances',
            '/inventory/transfers',
            '/inventory/stock-counts',
            '/inventory/adjustments',
        ];

        foreach ($allowedRoutes as $route) {
            $response = $this->actingAs($inventoryUser)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Inventory persona must be able to access [{$route}], got status {$response->status()}."
            );
        }

        $forbiddenRoutes = [
            '/payroll/runs',
            '/payroll/employees',
            '/settings/company',
            '/settings/branches',
            '/settings/users',
            '/settings/numbering',
            '/accounting',
            '/accounting/journal',
            '/accounting/ledger',
            '/accounting/trial-balance',
            '/accounting/periods',
            '/customers',
            '/suppliers',
            '/fixed-assets',
            '/sales/orders',
            '/sales/invoices',
            '/purchasing/orders',
            '/purchasing/bills',
            '/audit-log',
            '/reports',
        ];

        foreach ($forbiddenRoutes as $route) {
            $response = $this->actingAs($inventoryUser)->get($route);
            $this->assertEquals(
                403,
                $response->status(),
                "Inventory persona must be forbidden from [{$route}], got status {$response->status()}."
            );
        }
    }

    public function test_auditor_read_only_persona_can_access_audit_and_reports_and_cannot_perform_mutating_actions(): void
    {
        $this->withoutVite();
        $auditorUser = $this->createPersonaUser('AUDITOR');

        $allowedViews = [
            '/dashboard',
            '/audit-log',
            '/reports',
            '/reports/balance-sheet',
            '/reports/income-statement',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
            '/reports/vat-gl-reconciliation',
            '/accounting/journal',
            '/accounting/ledger',
            '/accounting/trial-balance',
            '/customers',
            '/suppliers',
            '/sales/orders',
            '/purchasing/orders',
            '/inventory/stock-balances',
            '/fixed-assets',
        ];

        foreach ($allowedViews as $route) {
            $response = $this->actingAs($auditorUser)->get($route);
            $this->assertEquals(
                200,
                $response->status(),
                "Auditor persona must be able to view [{$route}], got status {$response->status()}."
            );
        }

        // Auditor must NOT have mutating permissions
        $mutatingEndpoints = [
            ['method' => 'post', 'uri' => '/accounting/journal', 'data' => []],
            ['method' => 'post', 'uri' => '/customers', 'data' => ['name' => ['en' => 'Test', 'ar' => 'اختبار']]],
            ['method' => 'post', 'uri' => '/suppliers', 'data' => ['name' => ['en' => 'Test', 'ar' => 'اختبار']]],
            ['method' => 'post', 'uri' => '/sales/orders', 'data' => []],
            ['method' => 'post', 'uri' => '/purchasing/orders', 'data' => []],
            ['method' => 'post', 'uri' => '/fixed-assets', 'data' => []],
            ['method' => 'post', 'uri' => '/settings/company', 'data' => []],
            ['method' => 'post', 'uri' => '/settings/users', 'data' => []],
            ['method' => 'post', 'uri' => '/payroll/runs', 'data' => []],
        ];

        foreach ($mutatingEndpoints as $endpoint) {
            $method = $endpoint['method'];
            $uri = $endpoint['uri'];
            $data = $endpoint['data'];

            $response = $this->actingAs($auditorUser)->{$method}($uri, $data);
            $this->assertEquals(
                403,
                $response->status(),
                "Auditor persona must be forbidden from mutating action [{$method} {$uri}], got status {$response->status()}."
            );
        }
    }

    public function test_guest_users_are_redirected_or_blocked_from_all_representative_acceptance_routes(): void
    {
        $acceptanceRoutes = [
            '/dashboard',
            '/accounting',
            '/accounting/journal',
            '/customers',
            '/suppliers',
            '/sales/orders',
            '/purchasing/orders',
            '/inventory/stock-balances',
            '/payroll/runs',
            '/fixed-assets',
            '/reports',
            '/reports/balance-sheet',
            '/settings',
            '/audit-log',
        ];

        foreach ($acceptanceRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->isRedirect(route('login')),
                "Guest user accessing [{$route}] must be redirected to login, got status {$response->status()}."
            );
        }
    }

    public function test_strict_route_audit_remains_green(): void
    {
        $auditor = app(RouteAuthorizationAuditor::class);
        $result = $auditor->audit();

        $this->assertSame(0, $result['counts']['failing'], 'Failing routes count in route audit must be 0.');
        $this->assertEmpty($result['failures'], 'There must be no failing routes in route authorization audit.');
        $this->assertGreaterThan(400, $result['counts']['explicitly_authorized']);

        $this->artisan('security:route-audit', ['--strict' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('All protected routes satisfy authorization requirements.');
    }

    public function test_no_forbidden_scope_terms_are_introduced_in_slice_3(): void
    {
        $scriptPath = dirname(base_path()).DIRECTORY_SEPARATOR.'OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md';
        $this->assertFileExists($scriptPath);
        $scriptContent = (string) file_get_contents($scriptPath);

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
            $this->assertStringNotContainsString($term, $scriptContent, "Execution script must not contain forbidden scope term: {$term}");
        }
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
