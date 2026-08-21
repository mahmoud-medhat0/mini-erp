<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\ArApSubledgerService;
use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\SupplierPaymentService;
use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\ReceivableEntry;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase3Slice3ReceiptPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $arControlAccount;

    private Account $apControlAccount;

    private Account $cashGlAccount;

    private Account $bankGlAccount;

    private CashAccount $cashAccount;

    private BankAccount $bankAccount;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(AccountingCoreSeeder::class);

        $this->user = User::factory()->create();

        // 1. Create Fiscal Year & Open Period
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $this->fiscalYear = $periodService->createFiscalYear(2026, '2026-01-01', '2026-12-31');

        /** @var FinancialPeriod $period */
        $period = FinancialPeriod::query()
            ->where('fiscal_year_id', $this->fiscalYear->id)
            ->where('status', 'open')
            ->firstOrFail();
        $this->period = $period;

        // 2. Control Accounts
        $this->arControlAccount = Account::query()->create([
            'code' => '1100-AR-SLICE3',
            'name' => ['en' => 'Accounts Receivable', 'ar' => 'العملاء'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->apControlAccount = Account::query()->create([
            'code' => '2100-AP-SLICE3',
            'name' => ['en' => 'Accounts Payable', 'ar' => 'الموردين'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        // 3. Cash/Bank GL Accounts
        $this->cashGlAccount = Account::query()->create([
            'code' => '1010-CASH-GL',
            'name' => ['en' => 'Main Safe GL', 'ar' => 'خزينة رئيسية'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $this->bankGlAccount = Account::query()->create([
            'code' => '1020-BANK-GL',
            'name' => ['en' => 'CIB Bank GL', 'ar' => 'بنك CIB'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        // 4. Master Data Cash/Bank Accounts
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'CASH-MAIN',
            'name' => ['en' => 'Main Cash Drawer', 'ar' => 'صندوق الخزينة'],
            'gl_account_id' => $this->cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 0,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'code' => 'BANK-CIB',
            'name' => ['en' => 'CIB Corporate Account', 'ar' => 'حساب بنك CIB'],
            'gl_account_id' => $this->bankGlAccount->id,
            'bank_name' => 'CIB',
            'account_number' => '100099887766',
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 0,
        ]);

        // 5. Account Mappings
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $this->arControlAccount->id, 'AR Control', $this->user->id);
        $mappingService->setMapping('ap_control', $this->apControlAccount->id, 'AP Control', $this->user->id);
    }

    public function test_spatie_teams_remains_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
    }

    public function test_slice3_tables_exist_without_tenant_company_or_branch_id(): void
    {
        $tables = ['customer_receipt', 'supplier_payment'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");

            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must NOT contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "Table [{$table}] must NOT contain branch_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must NOT contain tenant_id.");
        }
    }

    public function test_no_allocation_table_is_introduced_in_slice3(): void
    {
        $this->assertFalse(Schema::hasTable('customer_receipt_allocation'));
        $this->assertFalse(Schema::hasTable('supplier_payment_allocation'));
    }

    public function test_receipt_requires_exactly_one_of_cash_or_bank_account(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-ACC-TEST', 'name' => ['en' => 'Acc Customer']]);

        /** @var CustomerReceiptService $service */
        $service = app(CustomerReceiptService::class);

        // Neither cash nor bank
        try {
            $service->create([
                'customer_id' => $customer->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'financial_period_id' => $this->period->id,
                'receipt_date' => (string) $this->period->start_date,
                'currency' => 'EGP',
                'amount_minor' => 100000,
            ], $this->user->id);
            $this->fail('Expected ValidationException when neither cash nor bank account is specified.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }

        // Both cash and bank
        try {
            $service->create([
                'customer_id' => $customer->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'financial_period_id' => $this->period->id,
                'receipt_date' => (string) $this->period->start_date,
                'cash_account_id' => $this->cashAccount->id,
                'bank_account_id' => $this->bankAccount->id,
                'currency' => 'EGP',
                'amount_minor' => 100000,
            ], $this->user->id);
            $this->fail('Expected ValidationException when both cash and bank accounts are specified.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }
    }

    public function test_payment_requires_exactly_one_of_cash_or_bank_account(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-ACC-TEST', 'name' => ['en' => 'Acc Supplier']]);

        /** @var SupplierPaymentService $service */
        $service = app(SupplierPaymentService::class);

        try {
            $service->create([
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'financial_period_id' => $this->period->id,
                'payment_date' => (string) $this->period->start_date,
                'currency' => 'EGP',
                'amount_minor' => 100000,
            ], $this->user->id);
            $this->fail('Expected ValidationException when neither cash nor bank account is specified.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }

        try {
            $service->create([
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'financial_period_id' => $this->period->id,
                'payment_date' => (string) $this->period->start_date,
                'cash_account_id' => $this->cashAccount->id,
                'bank_account_id' => $this->bankAccount->id,
                'currency' => 'EGP',
                'amount_minor' => 100000,
            ], $this->user->id);
            $this->fail('Expected ValidationException when both cash and bank accounts are specified.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }
    }

    public function test_receipt_posting_rejects_linked_gl_currency_mismatch(): void
    {
        $usdCashGl = Account::query()->create([
            'code' => '1010-CASH-USD-GL',
            'name' => ['en' => 'USD Cash GL', 'ar' => 'خزينة دولار'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'USD',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $cashAccount = CashAccount::query()->create([
            'code' => 'CASH-EGP-LINKED-USD',
            'name' => ['en' => 'EGP Cash Linked USD', 'ar' => 'خزينة جنيه بحساب دولار'],
            'gl_account_id' => $usdCashGl->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 0,
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-GL-CURR',
            'name' => ['en' => 'GL Currency Customer'],
        ]);

        /** @var CustomerReceiptService $service */
        $service = app(CustomerReceiptService::class);

        $receipt = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $service->post($receipt->id, $this->user->id);
    }

    public function test_customer_receipt_posting_creates_balanced_journal_and_subledger_credit(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUST-REC-001',
            'name' => ['en' => 'Apex Solutions', 'ar' => 'حلول أبكس'],
        ]);

        /** @var CustomerReceiptService $service */
        $service = app(CustomerReceiptService::class);

        $receipt = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'reference' => 'REF-REC-001',
            'description' => 'Advance customer payment',
            'currency' => 'EGP',
            'amount_minor' => 250000, // 2,500.00 EGP
        ], $this->user->id);

        $this->assertEquals('draft', $receipt->status);
        $this->assertEquals(0, $receipt->allocated_minor);
        $this->assertEquals(250000, $receipt->unapplied_minor);

        $posted = $service->post($receipt->id, $this->user->id);

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertStringStartsWith('REC-2026-', $posted->number);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->receivable_entry_id);
        $this->assertEquals(0, $posted->allocated_minor);
        $this->assertEquals(250000, $posted->unapplied_minor);

        // Assert Journal Entry Lines (Dr Cash GL 250,000, Cr AR Control 250,000)
        /** @var JournalEntry $journalEntry */
        $journalEntry = JournalEntry::query()->with('lines')->find($posted->journal_entry_id);
        $this->assertEquals('posted', $journalEntry->status);
        $this->assertCount(2, $journalEntry->lines);

        $drLine = $journalEntry->lines->firstWhere('debit_minor', '>', 0);
        $crLine = $journalEntry->lines->firstWhere('credit_minor', '>', 0);

        $this->assertEquals($this->cashGlAccount->id, $drLine->account_id);
        $this->assertEquals(250000, $drLine->debit_minor);

        $this->assertEquals($this->arControlAccount->id, $crLine->account_id);
        $this->assertEquals(250000, $crLine->credit_minor);

        // Assert Subledger ReceivableEntry
        $receivableEntry = ReceivableEntry::query()->find($posted->receivable_entry_id);
        $this->assertNotNull($receivableEntry);
        $this->assertEquals($customer->id, $receivableEntry->customer_id);
        $this->assertEquals(0, $receivableEntry->debit_minor);
        $this->assertEquals(250000, $receivableEntry->credit_minor);

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'customer_receipt')
            ->where('properties->entity_id', $receipt->id)
            ->where('event', 'post')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_customer_receipt_db_integrity_blocks_invalid_unapplied_amounts(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL check constraint coverage only.');
        }

        $customer = Customer::query()->create([
            'code' => 'CUST-DB-CHECK',
            'name' => ['en' => 'DB Check Customer'],
        ]);

        /** @var CustomerReceiptService $service */
        $service = app(CustomerReceiptService::class);

        $receipt = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);

        $this->expectException(QueryException::class);
        DB::table('customer_receipt')
            ->where('id', $receipt->id)
            ->update(['allocated_minor' => 1, 'unapplied_minor' => 1]);
    }

    public function test_customer_with_receipt_cannot_be_deleted_by_database_cascade(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL foreign-key delete restriction coverage only.');
        }

        $customer = Customer::query()->create([
            'code' => 'CUST-DELETE-RESTRICT',
            'name' => ['en' => 'Delete Restrict Customer'],
        ]);

        /** @var CustomerReceiptService $service */
        $service = app(CustomerReceiptService::class);

        $receipt = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);

        try {
            $customer->delete();
            $this->fail('Expected database restriction when deleting customer with receipt.');
        } catch (QueryException) {
            $this->assertDatabaseHas('customer_receipt', [
                'id' => $receipt->id,
                'customer_id' => $customer->id,
            ]);
        }
    }

    public function test_supplier_payment_posting_creates_balanced_journal_and_subledger_debit(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUPP-PAY-001',
            'name' => ['en' => 'Global Logistics', 'ar' => 'الخدمات اللوجستية'],
        ]);

        /** @var SupplierPaymentService $service */
        $service = app(SupplierPaymentService::class);

        $payment = $service->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => (string) $this->period->start_date,
            'bank_account_id' => $this->bankAccount->id,
            'reference' => 'REF-PAY-001',
            'description' => 'Supplier advance payment',
            'currency' => 'EGP',
            'amount_minor' => 400000, // 4,000.00 EGP
        ], $this->user->id);

        $this->assertEquals('draft', $payment->status);
        $this->assertEquals(0, $payment->allocated_minor);
        $this->assertEquals(400000, $payment->unapplied_minor);

        $posted = $service->post($payment->id, $this->user->id);

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertStringStartsWith('PAY-2026-', $posted->number);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->payable_entry_id);
        $this->assertEquals(0, $posted->allocated_minor);
        $this->assertEquals(400000, $posted->unapplied_minor);

        // Assert Journal Entry Lines (Dr AP Control 400,000, Cr Bank GL 400,000)
        /** @var JournalEntry $journalEntry */
        $journalEntry = JournalEntry::query()->with('lines')->find($posted->journal_entry_id);
        $this->assertEquals('posted', $journalEntry->status);
        $this->assertCount(2, $journalEntry->lines);

        $drLine = $journalEntry->lines->firstWhere('debit_minor', '>', 0);
        $crLine = $journalEntry->lines->firstWhere('credit_minor', '>', 0);

        $this->assertEquals($this->apControlAccount->id, $drLine->account_id);
        $this->assertEquals(400000, $drLine->debit_minor);

        $this->assertEquals($this->bankGlAccount->id, $crLine->account_id);
        $this->assertEquals(400000, $crLine->credit_minor);

        // Assert Subledger PayableEntry
        $payableEntry = PayableEntry::query()->find($posted->payable_entry_id);
        $this->assertNotNull($payableEntry);
        $this->assertEquals($supplier->id, $payableEntry->supplier_id);
        $this->assertEquals(400000, $payableEntry->debit_minor);
        $this->assertEquals(0, $payableEntry->credit_minor);

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'supplier_payment')
            ->where('properties->entity_id', $payment->id)
            ->where('event', 'post')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_subledger_remains_reconciled_after_receipts_and_payments(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-REC-REC', 'name' => ['en' => 'Reconciled Customer']]);
        $supplier = Supplier::query()->create(['code' => 'SUPP-REC-REC', 'name' => ['en' => 'Reconciled Supplier']]);

        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);
        /** @var SupplierPaymentService $paymentService */
        $paymentService = app(SupplierPaymentService::class);

        $receipt = $receiptService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 120000,
        ], $this->user->id);
        $receiptService->post($receipt->id, $this->user->id);

        $payment = $paymentService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => (string) $this->period->start_date,
            'bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 180000,
        ], $this->user->id);
        $paymentService->post($payment->id, $this->user->id);

        /** @var ArApSubledgerService $subledgerService */
        $subledgerService = app(ArApSubledgerService::class);

        $arRec = $subledgerService->reconcileCustomerSubledgerToGl();
        $apRec = $subledgerService->reconcileSupplierSubledgerToGl();

        $this->assertTrue($arRec['is_reconciled']);
        $this->assertTrue($apRec['is_reconciled']);
    }

    public function test_receipt_and_payment_posting_is_idempotent(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-IDEM', 'name' => ['en' => 'Idem Customer']]);

        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);

        $receipt = $receiptService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 50000,
        ], $this->user->id);

        $postedFirst = $receiptService->post($receipt->id, $this->user->id);
        $postedSecond = $receiptService->post($receipt->id, $this->user->id);

        $this->assertEquals($postedFirst->id, $postedSecond->id);
        $this->assertEquals($postedFirst->journal_entry_id, $postedSecond->journal_entry_id);
        $this->assertEquals($postedFirst->receivable_entry_id, $postedSecond->receivable_entry_id);
        $this->assertEquals(1, ReceivableEntry::query()->where('customer_id', $customer->id)->count());
    }

    public function test_draft_cancellation_does_not_create_journal_or_subledger(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-CANCEL', 'name' => ['en' => 'Cancel Supplier']]);

        /** @var SupplierPaymentService $paymentService */
        $paymentService = app(SupplierPaymentService::class);

        $payment = $paymentService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => (string) $this->period->start_date,
            'bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 90000,
        ], $this->user->id);

        $cancelled = $paymentService->cancel($payment->id, $this->user->id);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertNull($cancelled->journal_entry_id);
        $this->assertNull($cancelled->payable_entry_id);
        $this->assertEquals(0, PayableEntry::query()->where('supplier_id', $supplier->id)->count());
        $this->assertEquals(0, JournalEntry::query()->where('source_id', $payment->id)->count());
    }

    public function test_attachment_registry_accepts_slice3_entities(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);
        $allowedTypes = $authorizer->allowedEntityTypes();

        $this->assertContains('customer_receipt', $allowedTypes);
        $this->assertContains('supplier_payment', $allowedTypes);
    }
}
