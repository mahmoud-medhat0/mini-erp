<?php

namespace Tests\Feature;

use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\SupplierPaymentService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Sales\CustomerInvoiceService;
use App\Domain\Accounting\PeriodClosedException;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Models\SupplierPayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase5Slice4PeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $closeUser;

    private User $reopenUser;

    private User $unprivilegedUser;

    private User $settingsOnlyUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $cashAccount;

    private Account $arAccount;

    private Account $apAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    private CashAccount $cashAccountSubledger;

    private Product $serviceProduct;

    private UnitOfMeasure $uom;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function closeConfirmation(array $payload = []): array
    {
        return array_merge($payload, [
            'confirm_action' => 'CLOSE_FINANCIAL_PERIOD',
            'reason' => 'Automated period close test approval.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reopenConfirmation(array $payload = []): array
    {
        return array_merge($payload, [
            'confirm_action' => 'REOPEN_FINANCIAL_PERIOD',
            'reason' => 'Automated period reopen test approval.',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
        ]);

        Permission::findOrCreate('close_period', 'web');
        Permission::findOrCreate('reopen_period', 'web');
        Permission::findOrCreate('accounting.periods', 'web');

        $this->closeUser = User::factory()->create();
        $this->closeUser->givePermissionTo(['close_period', 'accounting.periods', 'accounting.create', 'accounting.post']);

        $this->reopenUser = User::factory()->create();
        $this->reopenUser->givePermissionTo(['reopen_period', 'accounting.periods']);

        $this->unprivilegedUser = User::factory()->create();

        $this->settingsOnlyUser = User::factory()->create();
        $this->settingsOnlyUser->givePermissionTo(['settings.configure']);

        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->period = FinancialPeriod::create([
            'id' => (string) Str::uuid(),
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $assetType = AccountType::query()->where('category', 'asset')->firstOrFail();
        $liabilityType = AccountType::query()->where('category', 'liability')->firstOrFail();
        $revenueType = AccountType::query()->where('category', 'revenue')->firstOrFail();
        $expenseType = AccountType::query()->where('category', 'expense')->firstOrFail();

        $this->cashAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1010-P5S4',
            'name' => ['en' => 'Cash Account P5S4', 'ar' => 'حساب نقدية'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
        ]);

        $this->arAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1100-P5S4',
            'name' => ['en' => 'AR Control P5S4', 'ar' => 'حساب العملاء'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
        ]);

        $this->apAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '2100-P5S4',
            'name' => ['en' => 'AP Control P5S4', 'ar' => 'حساب الموردين'],
            'account_type_id' => $liabilityType->id,
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
        ]);

        $this->revenueAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '4000-P5S4',
            'name' => ['en' => 'Sales Revenue P5S4', 'ar' => 'إيرادات مبيعات'],
            'account_type_id' => $revenueType->id,
            'type' => 'revenue',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
        ]);

        $this->expenseAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '5000-P5S4',
            'name' => ['en' => 'Operating Expense P5S4', 'ar' => 'مصروفات تشغيل'],
            'account_type_id' => $expenseType->id,
            'type' => 'expense',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
        ]);

        $this->cashAccountSubledger = CashAccount::create([
            'id' => (string) Str::uuid(),
            'code' => 'CSH-P5S4',
            'account_id' => $this->cashAccount->id,
            'gl_account_id' => $this->cashAccount->id,
            'name' => ['en' => 'Cash Account Subledger P5S4', 'ar' => 'حساب صندوق نقدية'],
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $this->uom = UnitOfMeasure::create([
            'id' => (string) Str::uuid(),
            'code' => 'PCS-P5S4',
            'symbol' => 'pc',
            'name' => ['en' => 'Pieces', 'ar' => 'قطع'],
            'status' => 'active',
        ]);

        $this->serviceProduct = Product::create([
            'id' => (string) Str::uuid(),
            'code' => 'SERV-P5S4',
            'name' => ['en' => 'Service Product', 'ar' => 'منتج خدمي'],
            'type' => 'service',
            'unit_of_measure_id' => $this->uom->id,
            'status' => 'active',
        ]);
    }

    public function test_posting_journal_to_closed_period_is_blocked_by_period_guard(): void
    {
        $this->period->update([
            'status' => 'closed',
            'closed_by' => $this->closeUser->id,
            'closed_at' => now(),
        ]);

        $journal = JournalEntry::create([
            'id' => (string) Str::uuid(),
            'entry_date' => '2026-01-15',
            'financial_period_id' => $this->period->id,
            'source_type' => 'manual_journal',
            'description' => 'Test Manual Journal',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'approved',
            'created_by' => $this->closeUser->id,
        ]);

        JournalLine::create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journal->id,
            'line_no' => 1,
            'account_id' => $this->expenseAccount->id,
            'debit_minor' => 1000,
            'credit_minor' => 0,
            'currency' => 'EGP',
        ]);

        JournalLine::create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journal->id,
            'line_no' => 2,
            'account_id' => $this->cashAccount->id,
            'debit_minor' => 0,
            'credit_minor' => 1000,
            'currency' => 'EGP',
        ]);

        $postingEngine = app(PostingEngine::class);

        $this->expectException(PeriodClosedException::class);
        $postingEngine->post($journal, $this->closeUser->id);
    }

    public function test_period_close_is_blocked_when_unposted_documents_exist(): void
    {
        JournalEntry::create([
            'id' => (string) Str::uuid(),
            'entry_date' => '2026-01-10',
            'financial_period_id' => $this->period->id,
            'source_type' => 'manual_journal',
            'description' => 'Draft Journal Blocker',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'draft',
            'created_by' => $this->closeUser->id,
        ]);

        $periodService = app(PeriodService::class);

        $readiness = $periodService->checkCloseReadiness($this->period);

        $this->assertFalse($readiness['can_close']);
        $this->assertNotEmpty($readiness['blockers']);
        $this->assertEquals('journal_entry', $readiness['blockers'][0]['entity_type']);

        $this->actingAs($this->closeUser);
        $response = $this->post(route('accounting.periods.close', $this->period->id), $this->closeConfirmation([
            'close_note' => 'Attempting close with draft journal',
        ]));

        $response->assertSessionHasErrors(['period', 'blockers']);

        $this->period->refresh();
        $this->assertEquals('open', $this->period->status);
    }

    public function test_period_close_is_blocked_by_approved_unposted_customer_invoice(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'code' => 'CUST-BLOCK-P5S4',
            'name' => 'Customer Blocker P5S4',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        CustomerInvoice::create([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'invoice_date' => '2026-01-20',
            'due_date' => '2026-01-31',
            'subtotal_minor' => 1000,
            'total_minor' => 1000,
            'currency' => 'EGP',
            'status' => 'approved',
            'created_by' => $this->closeUser->id,
        ]);

        $readiness = app(PeriodService::class)->checkCloseReadiness($this->period);

        $this->assertFalse($readiness['can_close']);
        $this->assertContains('customer_invoice', array_column($readiness['blockers'], 'entity_type'));
        $this->assertContains('approved', array_column($readiness['blockers'], 'status'));
    }

    public function test_period_close_succeeds_when_clean(): void
    {
        $periodService = app(PeriodService::class);

        $readiness = $periodService->checkCloseReadiness($this->period);
        $this->assertTrue($readiness['can_close']);
        $this->assertEmpty($readiness['blockers']);

        $this->actingAs($this->closeUser);
        $response = $this->post(route('accounting.periods.close', $this->period->id), $this->closeConfirmation([
            'close_note' => 'Closing January 2026 after full audit review.',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->period->refresh();
        $this->assertEquals('closed', $this->period->status);
        $this->assertEquals($this->closeUser->id, $this->period->closed_by);
        $this->assertNotNull($this->period->closed_at);
        $this->assertEquals('Closing January 2026 after full audit review.', $this->period->close_note);
    }

    public function test_reopen_period_succeeds_and_allows_postings(): void
    {
        $this->period->update([
            'status' => 'closed',
            'closed_by' => $this->closeUser->id,
            'closed_at' => now(),
            'close_note' => 'Initial close',
        ]);

        $this->actingAs($this->reopenUser);
        $response = $this->post(route('accounting.periods.reopen', $this->period->id), $this->reopenConfirmation([
            'close_note' => 'Reopening for tax audit adjustment.',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->period->refresh();
        $this->assertEquals('reopened', $this->period->status);
        $this->assertEquals($this->reopenUser->id, $this->period->reopened_by);
        $this->assertNotNull($this->period->reopened_at);
        $this->assertEquals('Reopening for tax audit adjustment.', $this->period->close_note);
    }

    public function test_close_period_permission_gate_enforcement(): void
    {
        $this->actingAs($this->unprivilegedUser);
        $response = $this->post(route('accounting.periods.close', $this->period->id));
        $response->assertStatus(403);

        $this->actingAs($this->settingsOnlyUser);
        $response = $this->post(route('accounting.periods.close', $this->period->id));
        $response->assertStatus(403);

        $this->period->refresh();
        $this->assertEquals('open', $this->period->status);
    }

    public function test_reopen_period_permission_gate_enforcement(): void
    {
        $this->period->update(['status' => 'closed']);

        $this->actingAs($this->unprivilegedUser);
        $response = $this->post(route('accounting.periods.reopen', $this->period->id));
        $response->assertStatus(403);

        $this->actingAs($this->closeUser); // closeUser has close_period but not reopen_period
        $response = $this->post(route('accounting.periods.reopen', $this->period->id));
        $response->assertStatus(403);

        $this->period->refresh();
        $this->assertEquals('closed', $this->period->status);
    }

    public function test_date_mismatch_with_period_is_rejected(): void
    {
        $periodGuard = app(PeriodGuard::class);

        $this->expectException(\InvalidArgumentException::class);
        $periodGuard->assertPeriodOpenForPosting($this->period->id, '2026-02-15');
    }

    public function test_date_based_period_resolution_rejects_closed_period(): void
    {
        $this->period->update(['status' => 'closed']);

        $this->expectException(PeriodClosedException::class);
        app(PeriodGuard::class)->resolveOpenPeriodForPostingDateWithLock('2026-01-15');
    }

    public function test_customer_invoice_posting_to_closed_period_is_blocked(): void
    {
        $this->period->update(['status' => 'closed']);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'code' => 'CUST-P5S4',
            'name' => 'Customer P5S4',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $invoice = CustomerInvoice::create([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'invoice_date' => '2026-01-15',
            'due_date' => '2026-01-31',
            'subtotal_minor' => 5000,
            'total_minor' => 5000,
            'currency' => 'EGP',
            'status' => 'approved',
            'created_by' => $this->closeUser->id,
        ]);

        CustomerInvoiceLine::create([
            'id' => (string) Str::uuid(),
            'customer_invoice_id' => $invoice->id,
            'line_no' => 1,
            'product_id' => $this->serviceProduct->id,
            'unit_of_measure_id' => $this->uom->id,
            'description' => 'Test Service Line',
            'quantity_e6' => 1000000,
            'unit_price_minor' => 5000,
            'line_total_minor' => 5000,
        ]);

        $service = app(CustomerInvoiceService::class);

        $this->expectException(PeriodClosedException::class);
        $service->post($invoice->id, $this->closeUser->id);
    }

    public function test_customer_receipt_posting_to_closed_period_is_blocked(): void
    {
        $this->period->update(['status' => 'closed']);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'code' => 'CUST-REC-P5S4',
            'name' => 'Customer Receipt P5S4',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $receipt = CustomerReceipt::create([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-15',
            'cash_account_id' => $this->cashAccountSubledger->id,
            'currency' => 'EGP',
            'amount_minor' => 2500,
            'unapplied_minor' => 2500,
            'allocated_minor' => 0,
            'fx_rate_e6' => 1000000,
            'status' => 'draft',
            'created_by' => $this->closeUser->id,
        ]);

        $service = app(CustomerReceiptService::class);

        $this->expectException(PeriodClosedException::class);
        $service->post($receipt->id, $this->closeUser->id);
    }

    public function test_supplier_bill_posting_to_closed_period_is_blocked(): void
    {
        $this->period->update(['status' => 'closed']);

        $supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'code' => 'SUPP-P5S4',
            'name' => 'Supplier P5S4',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'bill_date' => '2026-01-15',
            'due_date' => '2026-01-31',
            'subtotal_minor' => 3000,
            'total_minor' => 3000,
            'currency' => 'EGP',
            'status' => 'approved',
            'created_by' => $this->closeUser->id,
        ]);

        SupplierBillLine::create([
            'id' => (string) Str::uuid(),
            'supplier_bill_id' => $bill->id,
            'line_no' => 1,
            'product_id' => $this->serviceProduct->id,
            'unit_of_measure_id' => $this->uom->id,
            'description' => 'Test Service Line',
            'quantity_e6' => 1000000,
            'unit_cost_minor' => 3000,
            'unit_price_minor' => 3000,
            'line_total_minor' => 3000,
        ]);

        $service = app(SupplierBillService::class);

        $this->expectException(PeriodClosedException::class);
        $service->post($bill->id, $this->closeUser->id);
    }

    public function test_supplier_payment_posting_to_closed_period_is_blocked(): void
    {
        $this->period->update(['status' => 'closed']);

        $supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'code' => 'SUPP-PAY-P5S4',
            'name' => 'Supplier Payment P5S4',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $payment = SupplierPayment::create([
            'id' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => '2026-01-15',
            'cash_account_id' => $this->cashAccountSubledger->id,
            'currency' => 'EGP',
            'amount_minor' => 1500,
            'unapplied_minor' => 1500,
            'allocated_minor' => 0,
            'fx_rate_e6' => 1000000,
            'status' => 'draft',
            'created_by' => $this->closeUser->id,
        ]);

        $service = app(SupplierPaymentService::class);

        $this->expectException(PeriodClosedException::class);
        $service->post($payment->id, $this->closeUser->id);
    }
}
