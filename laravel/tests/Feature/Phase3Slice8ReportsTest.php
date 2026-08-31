<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\IncomingCheque;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\ReceivableEntry;
use App\Models\Supplier;
use App\Models\SupplierOpeningBalance;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase3Slice8ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private JournalEntry $journalEntry;

    private AccountType $assetType;

    private AccountType $liabilityType;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('reports.export', 'web');
        Permission::findOrCreate('view_financials', 'web');

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);

        $this->unauthorizedUser = User::factory()->create();

        $this->fiscalYear = FiscalYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->period = FinancialPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $this->journalEntry = JournalEntry::create([
            'entry_number' => 'JE-2026-00001',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-01',
            'source_type' => 'manual',
            'status' => 'posted',
        ]);

        $this->assetType = AccountType::create([
            'code' => 'ASSET',
            'name' => 'Assets',
            'category' => 'asset',
            'statement_type' => 'balance_sheet',
            'normal_balance' => 'debit',
        ]);

        $this->liabilityType = AccountType::create([
            'code' => 'LIABILITY',
            'name' => 'Liabilities',
            'category' => 'liability',
            'statement_type' => 'balance_sheet',
            'normal_balance' => 'credit',
        ]);
    }

    public function test_reports_permission_denial(): void
    {
        $routes = [
            '/reports',
            '/reports/customer-statement',
            '/reports/supplier-statement',
            '/reports/ar-aging',
            '/reports/ap-aging',
            '/reports/cash-book',
            '/reports/bank-book',
            '/reports/cheque-register',
            '/reports/bank-reconciliations',
            '/reports/ar-gl-reconciliation',
            '/reports/ap-gl-reconciliation',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->unauthorizedUser)->get($route);
            $response->assertStatus(403);
        }
    }

    public function test_reports_hub_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/reports');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Reports/Index'));
    }

    public function test_customer_statement_report(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Acme Client',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        CustomerOpeningBalance::create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'amount_minor' => 100000,
            'currency' => 'EGP',
            'entry_date' => '2026-01-01',
            'status' => 'posted',
        ]);

        ReceivableEntry::create([
            'customer_id' => $customer->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-02-01',
            'currency' => 'EGP',
            'debit_minor' => 50000,
            'credit_minor' => 0,
        ]);

        CustomerReceipt::create([
            'number' => 'REC-2026-00001',
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-02-15',
            'currency' => 'EGP',
            'amount_minor' => 30000,
            'allocated_minor' => 0,
            'unapplied_minor' => 30000,
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/customer-statement?customer_id='.$customer->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/CustomerStatement')
            ->where('report.opening_balance_minor', 0)
            ->where('report.closing_balance_minor', 120000)
            ->where('report.total_debit_minor', 150000)
            ->where('report.total_credit_minor', 30000)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/customer-statement/export?customer_id='.$customer->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_supplier_statement_report(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUPP-001',
            'name' => 'Global Supplier',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        SupplierOpeningBalance::create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'amount_minor' => 200000,
            'currency' => 'EGP',
            'entry_date' => '2026-01-01',
            'status' => 'posted',
        ]);

        PayableEntry::create([
            'supplier_id' => $supplier->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-02-01',
            'currency' => 'EGP',
            'debit_minor' => 0,
            'credit_minor' => 80000,
        ]);

        SupplierPayment::create([
            'number' => 'PAY-2026-00001',
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => '2026-02-10',
            'currency' => 'EGP',
            'amount_minor' => 50000,
            'allocated_minor' => 0,
            'unapplied_minor' => 50000,
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/supplier-statement?supplier_id='.$supplier->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/SupplierStatement')
            ->where('report.closing_balance_minor', 230000)
            ->where('report.total_debit_minor', 50000)
            ->where('report.total_credit_minor', 280000)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/supplier-statement/export?supplier_id='.$supplier->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $csvResponse->assertStatus(200);
    }

    public function test_ar_aging_report(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-002',
            'name' => 'Beta Corp',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        ReceivableEntry::create([
            'customer_id' => $customer->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-02-15',
            'currency' => 'EGP',
            'debit_minor' => 10000,
            'credit_minor' => 0,
        ]);

        ReceivableEntry::create([
            'customer_id' => $customer->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-01-01',
            'currency' => 'EGP',
            'debit_minor' => 20000,
            'credit_minor' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/ar-aging?as_of_date=2026-02-21&currency=EGP');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/ArAging')
            ->where('report.grand_totals.total', 30000)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/ar-aging/export?as_of_date=2026-02-21&currency=EGP');
        $csvResponse->assertStatus(200);
    }

    public function test_ap_aging_report(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUPP-002',
            'name' => 'Delta Traders',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        PayableEntry::create([
            'supplier_id' => $supplier->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-02-15',
            'currency' => 'EGP',
            'debit_minor' => 0,
            'credit_minor' => 15000,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/ap-aging?as_of_date=2026-02-21&currency=EGP');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/ApAging')
            ->where('report.grand_totals.total', 15000)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/ap-aging/export?as_of_date=2026-02-21&currency=EGP');
        $csvResponse->assertStatus(200);
    }

    public function test_cash_book_report(): void
    {
        $glAccount = Account::create([
            'code' => '101001',
            'name' => 'Main Cash Box',
            'account_type_id' => $this->assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);

        $cashAccount = CashAccount::create([
            'code' => 'CASH-01',
            'name' => 'Main Cash Desk',
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/cash-book?cash_account_id='.$cashAccount->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/CashBook')
            ->where('report.cash_account.id', $cashAccount->id)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/cash-book/export?cash_account_id='.$cashAccount->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $csvResponse->assertStatus(200);
    }

    public function test_bank_book_report(): void
    {
        $glAccount = Account::create([
            'code' => '102001',
            'name' => 'National Bank GL',
            'account_type_id' => $this->assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::create([
            'code' => 'BANK-01',
            'name' => 'NBE Current Account',
            'gl_account_id' => $glAccount->id,
            'account_number' => '123456789',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/bank-book?bank_account_id='.$bankAccount->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/BankBook')
            ->where('report.bank_account.id', $bankAccount->id)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/bank-book/export?bank_account_id='.$bankAccount->id.'&date_from=2026-01-01&date_to=2026-02-28');
        $csvResponse->assertStatus(200);
    }

    public function test_cheque_register_report(): void
    {
        $glAccount = Account::create(['code' => '102002', 'name' => 'Bank Account GL', 'account_type_id' => $this->assetType->id, 'type' => 'asset', 'nature' => 'debit', 'is_active' => true]);
        $customer = Customer::create(['code' => 'CUST-003', 'name' => 'Gamma Client', 'currency' => 'EGP', 'status' => 'active']);
        $bankAccount = BankAccount::create(['code' => 'BANK-02', 'name' => 'CIB Bank Account', 'gl_account_id' => $glAccount->id, 'currency' => 'EGP', 'is_active' => true]);

        IncomingCheque::create([
            'cheque_number' => 'ICHQ-1001',
            'customer_id' => $customer->id,
            'deposit_bank_account_id' => $bankAccount->id,
            'drawer_bank_name' => 'CIB',
            'received_date' => '2026-03-01',
            'amount_minor' => 45000,
            'currency' => 'EGP',
            'status' => 'received',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/cheque-register?direction=all&currency=EGP');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/ChequeRegister')
            ->where('report.total_count', 1)
            ->where('report.incoming_total_minor', 45000)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/cheque-register/export?direction=all&currency=EGP');
        $csvResponse->assertStatus(200);
    }

    public function test_bank_reconciliation_report(): void
    {
        $glAccount = Account::create(['code' => '102003', 'name' => 'HSBC GL', 'account_type_id' => $this->assetType->id, 'type' => 'asset', 'nature' => 'debit', 'is_active' => true]);
        $bankAccount = BankAccount::create(['code' => 'BANK-03', 'name' => 'HSBC Account', 'gl_account_id' => $glAccount->id, 'currency' => 'EGP', 'is_active' => true]);

        $recon = BankReconciliation::create([
            'bank_account_id' => $bankAccount->id,
            'financial_period_id' => $this->period->id,
            'statement_reference' => 'STMT-2026-01',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'currency' => 'EGP',
            'statement_opening_balance_minor' => 100000,
            'statement_closing_balance_minor' => 150000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/reports/bank-reconciliations');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/BankReconciliation')
            ->where('report.reconciliations.0.id', $recon->id)
        );

        $detailResponse = $this->actingAs($this->adminUser)->get('/reports/bank-reconciliations/'.$recon->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertInertia(fn ($page) => $page
            ->component('Reports/BankReconciliationDetail')
            ->where('detail.reconciliation.id', $recon->id)
        );
    }

    public function test_ar_to_gl_reconciliation_report(): void
    {
        $customer = Customer::create(['code' => 'CUST-004', 'name' => 'Zeta Corp', 'currency' => 'EGP', 'status' => 'active']);

        ReceivableEntry::create([
            'customer_id' => $customer->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-02-01',
            'currency' => 'EGP',
            'debit_minor' => 60000,
            'credit_minor' => 0,
        ]);

        // Unmapped state
        $responseUnmapped = $this->actingAs($this->adminUser)->get('/reports/ar-gl-reconciliation?as_of_date=2026-02-21&currency=EGP');
        $responseUnmapped->assertStatus(200);
        $responseUnmapped->assertInertia(fn ($page) => $page
            ->component('Reports/ArGlReconciliation')
            ->where('report.mapping_configured', false)
            ->where('report.subledger_total_minor', 60000)
            ->where('report.difference_minor', 60000)
        );

        // Mapped state
        $glAccount = Account::create(['code' => '110001', 'name' => 'Accounts Receivable Control', 'account_type_id' => $this->assetType->id, 'type' => 'asset', 'nature' => 'debit', 'is_active' => true]);
        AccountingAccountMapping::create(['key' => 'ar_control', 'account_id' => $glAccount->id]);

        $responseMapped = $this->actingAs($this->adminUser)->get('/reports/ar-gl-reconciliation?as_of_date=2026-02-21&currency=EGP');
        $responseMapped->assertStatus(200);
        $responseMapped->assertInertia(fn ($page) => $page
            ->component('Reports/ArGlReconciliation')
            ->where('report.mapping_configured', true)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/ar-gl-reconciliation/export?as_of_date=2026-02-21&currency=EGP');
        $csvResponse->assertStatus(200);
    }

    public function test_ap_to_gl_reconciliation_report(): void
    {
        $supplier = Supplier::create(['code' => 'SUPP-004', 'name' => 'Omega Logistics', 'currency' => 'EGP', 'status' => 'active']);

        PayableEntry::create([
            'supplier_id' => $supplier->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-02-01',
            'currency' => 'EGP',
            'debit_minor' => 0,
            'credit_minor' => 90000,
        ]);

        // Unmapped state
        $responseUnmapped = $this->actingAs($this->adminUser)->get('/reports/ap-gl-reconciliation?as_of_date=2026-02-21&currency=EGP');
        $responseUnmapped->assertStatus(200);
        $responseUnmapped->assertInertia(fn ($page) => $page
            ->component('Reports/ApGlReconciliation')
            ->where('report.mapping_configured', false)
            ->where('report.subledger_total_minor', 90000)
            ->where('report.difference_minor', 90000)
        );

        // Mapped state
        $glAccount = Account::create(['code' => '210001', 'name' => 'Accounts Payable Control', 'account_type_id' => $this->liabilityType->id, 'type' => 'liability', 'nature' => 'credit', 'is_active' => true]);
        AccountingAccountMapping::create(['key' => 'ap_control', 'account_id' => $glAccount->id]);

        $responseMapped = $this->actingAs($this->adminUser)->get('/reports/ap-gl-reconciliation?as_of_date=2026-02-21&currency=EGP');
        $responseMapped->assertStatus(200);
        $responseMapped->assertInertia(fn ($page) => $page
            ->component('Reports/ApGlReconciliation')
            ->where('report.mapping_configured', true)
        );

        $csvResponse = $this->actingAs($this->adminUser)->get('/reports/ap-gl-reconciliation/export?as_of_date=2026-02-21&currency=EGP');
        $csvResponse->assertStatus(200);
    }
}
