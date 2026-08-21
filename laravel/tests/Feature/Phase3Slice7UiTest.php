<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\User;
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

        $this->user = User::factory()->create();

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
            'is_closed' => false,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 1,
            'month' => 1,
            'name' => 'January 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_closed' => false,
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
            ->has('cashAccounts.data', 1)
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
            ->has('bankAccounts.data', 1)
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
        $response = $this->actingAs($this->user)->get('/incoming-cheques');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('IncomingCheques/Index')
        );
    }

    public function test_outgoing_cheques_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/outgoing-cheques');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OutgoingCheques/Index')
        );
    }

    public function test_bank_reconciliations_index_page(): void
    {
        $response = $this->actingAs($this->user)->get('/bank-reconciliations');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BankReconciliations/Index')
        );
    }
}
