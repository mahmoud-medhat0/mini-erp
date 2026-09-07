<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\IncomingCheque;
use App\Models\OutgoingCheque;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3Slice7UiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Currency $currency;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $assetAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create();

        $this->user->givePermissionTo([
            'customers.view',
            'suppliers.view',
            'cash.view',
            'banks.view',
            'cheques.view',
        ]);

        $this->currency = Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            [
                'name' => 'Egyptian Pound',
                'symbol' => 'EGP',
                'decimals' => 2,
                'is_active' => true,
            ]
        );

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $this->assetAccount = Account::query()->create([
            'code' => '101000',
            'name' => 'Cash & Bank Asset Account',
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);
    }

    public function test_customers_index_page(): void
    {
        Customer::query()->create([
            'code' => 'CUST-001',
            'name' => 'Acme Trading Co',
            'status' => 'active',
            'lock_version' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/customers');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->has('customers.data', 1)
        );
    }

    public function test_suppliers_index_page(): void
    {
        Supplier::query()->create([
            'code' => 'SUPP-001',
            'name' => 'Global Supplies Corp',
            'status' => 'active',
            'lock_version' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/suppliers');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Suppliers/Index')
            ->has('suppliers.data', 1)
        );
    }

    public function test_cash_accounts_index_page(): void
    {
        CashAccount::query()->create([
            'code' => 'CASH-01',
            'name' => 'Main Safe',
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
            'lock_version' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/cash-accounts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CashAccounts/Index')
            ->where('cashAccounts', [])
        );
    }

    public function test_bank_accounts_index_page(): void
    {
        BankAccount::query()->create([
            'code' => 'BANK-01',
            'name' => 'Corporate EGP Account',
            'account_number' => '1234567890',
            'bank_name' => 'National Bank of Egypt',
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
            'lock_version' => 0,
        ]);

        $response = $this->actingAs($this->user)->get('/bank-accounts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BankAccounts/Index')
            ->where('bankAccounts', [])
        );
    }

    public function test_customer_opening_balances_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/customer-opening-balances');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CustomerOpeningBalances/Index')
        );
    }

    public function test_supplier_opening_balances_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/supplier-opening-balances');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SupplierOpeningBalances/Index')
        );
    }

    public function test_customer_receipts_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/customer-receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CustomerReceipts/Index')
        );
    }

    public function test_supplier_payments_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/supplier-payments');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SupplierPayments/Index')
        );
    }

    public function test_receivable_allocations_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/receivable-allocations');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ReceivableAllocations/Index')
        );
    }

    public function test_payable_allocations_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/payable-allocations');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('PayableAllocations/Index')
        );
    }

    public function test_incoming_cheques_index_page(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUST-CHEQUE',
            'name' => ['en' => 'Incoming Cheque Customer', 'ar' => 'عميل شيك وارد'],
            'status' => 'active',
        ]);
        IncomingCheque::query()->create([
            'customer_id' => $customer->id,
            'cheque_number' => 'IN-CHQ-001',
            'drawer_bank_name' => 'Drawer Bank',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 12500,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get('/incoming-cheques');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('IncomingCheques/Index')
            ->missing('cheques')
        );

        $this->actingAs($this->user)
            ->getJson('/incoming-cheques/data?'.http_build_query($this->gridQuery([
                'cheque_number', 'customer_name', 'bank_name', 'due_date', 'amount_minor', 'status', 'id',
            ], 'Incoming Cheque Customer')))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.cheque_number', 'IN-CHQ-001');
    }

    public function test_outgoing_cheques_index_page(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUP-CHQ',
            'name' => ['en' => 'Outgoing Cheque Supplier', 'ar' => 'مورد شيك صادر'],
            'status' => 'active',
        ]);
        $bank = BankAccount::query()->create([
            'code' => 'BANK-CHQ',
            'name' => ['en' => 'Cheque Bank Account', 'ar' => 'حساب بنك الشيكات'],
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
        ]);
        OutgoingCheque::query()->create([
            'supplier_id' => $supplier->id,
            'bank_account_id' => $bank->id,
            'cheque_number' => 'OUT-CHQ-001',
            'due_date' => '2026-01-16',
            'currency' => 'EGP',
            'amount_minor' => 25000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get('/outgoing-cheques');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OutgoingCheques/Index')
            ->missing('cheques')
        );

        $this->actingAs($this->user)
            ->getJson('/outgoing-cheques/data?'.http_build_query($this->gridQuery([
                'cheque_number', 'supplier_name', 'bank_account_name', 'due_date', 'amount_minor', 'status', 'id',
            ], 'Outgoing Cheque Supplier')))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.cheque_number', 'OUT-CHQ-001');
    }

    public function test_bank_reconciliations_index_page(): void
    {
        $bank = BankAccount::query()->create([
            'code' => 'BANK-RECON',
            'name' => ['en' => 'Reconciliation Account', 'ar' => 'حساب التسوية'],
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
        ]);
        BankReconciliation::query()->create([
            'bank_account_id' => $bank->id,
            'financial_period_id' => $this->period->id,
            'statement_reference' => 'STMT-001',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'currency' => 'EGP',
            'statement_opening_balance_minor' => 10000,
            'statement_closing_balance_minor' => 15000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get('/bank-reconciliations');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BankReconciliations/Index')
            ->missing('reconciliations')
        );

        $this->actingAs($this->user)
            ->getJson('/bank-reconciliations/data?'.http_build_query($this->gridQuery([
                'bank_account_name', 'statement_reference', 'date_from', 'statement_opening_balance_minor',
                'statement_closing_balance_minor', 'status', 'id',
            ], 'BANK-RECON')))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.statement_reference', 'STMT-001');
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function gridQuery(array $names, string $search = ''): array
    {
        $nonSearchable = ['amount_minor', 'statement_opening_balance_minor', 'statement_closing_balance_minor', 'status', 'id'];
        $columns = [];

        foreach ($names as $index => $name) {
            $columns[$index] = [
                'data' => $name,
                'name' => $name,
                'searchable' => in_array($name, $nonSearchable, true) ? 'false' : 'true',
                'orderable' => $name === 'id' ? 'false' : 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return [
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => $columns,
            'order' => [],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
