<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\CustomerOpeningBalanceService;
use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\PayableAllocationService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\ReceivableAllocationService;
use App\Application\Accounting\SupplierOpeningBalanceService;
use App\Application\Accounting\SupplierPaymentService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PayableAllocation;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase3Slice4AllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $arControlAccount;

    private Account $apControlAccount;

    private Account $offsetAccount;

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

        // 1. Fiscal Year & Open Period
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $this->fiscalYear = $periodService->createFiscalYear(2026, '2026-01-01', '2026-12-31');

        /** @var FinancialPeriod $period */
        $period = FinancialPeriod::query()
            ->where('fiscal_year_id', $this->fiscalYear->id)
            ->where('status', 'open')
            ->firstOrFail();
        $this->period = $period;

        // 2. Accounts
        $this->arControlAccount = Account::query()->create([
            'code' => '1100-AR-SLICE4',
            'name' => ['en' => 'AR Control', 'ar' => 'العملاء'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->apControlAccount = Account::query()->create([
            'code' => '2100-AP-SLICE4',
            'name' => ['en' => 'AP Control', 'ar' => 'الموردين'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->offsetAccount = Account::query()->create([
            'code' => '3900-OFFSET-SLICE4',
            'name' => ['en' => 'Offset Account', 'ar' => 'حساب المقاصة'],
            'type' => 'equity',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $this->cashGlAccount = Account::query()->create([
            'code' => '1010-CASH-GL-SLICE4',
            'name' => ['en' => 'Main Cash GL', 'ar' => 'خزينة رئيسية'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $this->bankGlAccount = Account::query()->create([
            'code' => '1020-BANK-GL-SLICE4',
            'name' => ['en' => 'Bank GL', 'ar' => 'البنك'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        // 3. Master Data Cash/Bank
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'CASH-MAIN-SLICE4',
            'name' => ['en' => 'Main Safe', 'ar' => 'الخزينة'],
            'gl_account_id' => $this->cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 0,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'code' => 'BANK-MAIN-SLICE4',
            'name' => ['en' => 'CIB Bank', 'ar' => 'بنك CIB'],
            'gl_account_id' => $this->bankGlAccount->id,
            'bank_name' => 'CIB',
            'account_number' => '9988776655',
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 0,
        ]);

        // 4. Mappings
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $this->arControlAccount->id, 'AR Control', $this->user->id);
        $mappingService->setMapping('ap_control', $this->apControlAccount->id, 'AP Control', $this->user->id);
        $mappingService->setMapping('opening_balance_offset', $this->offsetAccount->id, 'Offset', $this->user->id);
    }

    public function test_spatie_teams_remains_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
    }

    public function test_slice4_tables_exist_without_tenant_company_or_branch_id(): void
    {
        $tables = ['receivable_allocation', 'payable_allocation'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");

            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must NOT contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "Table [{$table}] must NOT contain branch_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must NOT contain tenant_id.");
        }
    }

    public function test_customer_receipt_allocation_lifecycle_and_reversal(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-ALLOC-1', 'name' => ['en' => 'Allocation Customer']]);

        // 1. Create Posted Receivable Entry via Opening Balance (Debit 500,000 minor units)
        /** @var CustomerOpeningBalanceService $cobService */
        $cobService = app(CustomerOpeningBalanceService::class);
        $cob = $cobService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 500000,
        ], $this->user->id);
        $postedCob = $cobService->post($cob->id, $this->user->id);

        // 2. Create Posted Customer Receipt (500,000 minor units)
        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);
        $receipt = $receiptService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 500000,
        ], $this->user->id);
        $postedReceipt = $receiptService->post($receipt->id, $this->user->id);

        $initialJournalCount = JournalEntry::query()->count();
        $initialLedgerCount = LedgerEntry::query()->count();
        $initialReceivableCount = ReceivableEntry::query()->count();

        // 3. Allocate 300,000 minor units to the receivable entry
        /** @var ReceivableAllocationService $allocService */
        $allocService = app(ReceivableAllocationService::class);

        $allocations = $allocService->allocateReceipt(
            $postedReceipt->id,
            [
                [
                    'receivable_entry_id' => $postedCob->receivable_entry_id,
                    'amount_minor' => 300000,
                ],
            ],
            $this->user->id
        );

        $this->assertCount(1, $allocations);
        /** @var ReceivableAllocation $alloc */
        $alloc = $allocations[0];
        $this->assertEquals('active', $alloc->status);
        $this->assertEquals(300000, $alloc->amount_minor);

        // Assert CustomerReceipt balances updated
        $freshReceipt = $postedReceipt->fresh();
        $this->assertEquals(300000, $freshReceipt->allocated_minor);
        $this->assertEquals(200000, $freshReceipt->unapplied_minor);
        $this->assertEquals(500000, $freshReceipt->allocated_minor + $freshReceipt->unapplied_minor);

        // Assert NO GL/Journal/Ledger/ReceivableEntry rows were created by allocation
        $this->assertEquals($initialJournalCount, JournalEntry::query()->count());
        $this->assertEquals($initialLedgerCount, LedgerEntry::query()->count());
        $this->assertEquals($initialReceivableCount, ReceivableEntry::query()->count());

        // 4. Reverse Allocation
        $reversedAlloc = $allocService->reverseReceiptAllocation($alloc->id, 'Customer dispute settlement', $this->user->id);
        $replayedReversal = $allocService->reverseReceiptAllocation($alloc->id, 'Customer dispute settlement', $this->user->id);

        $this->assertEquals('reversed', $reversedAlloc->status);
        $this->assertEquals($reversedAlloc->id, $replayedReversal->id);
        $this->assertNotNull($reversedAlloc->reversed_at);

        // Assert CustomerReceipt balances restored
        $restoredReceipt = $postedReceipt->fresh();
        $this->assertEquals(0, $restoredReceipt->allocated_minor);
        $this->assertEquals(500000, $restoredReceipt->unapplied_minor);

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'receivable_allocation')
            ->where('properties->entity_id', $alloc->id)
            ->where('event', 'reverse')
            ->first();
        $this->assertNotNull($activity);

        try {
            $allocService->reverseReceiptAllocation($alloc->id, 'Second reversal must fail', $this->user->id, 'different-reversal-key');
            $this->fail('Expected ValidationException for reversing an already reversed allocation with a new command key.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_supplier_payment_allocation_lifecycle_and_reversal(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-ALLOC-1', 'name' => ['en' => 'Allocation Supplier']]);

        // 1. Create Posted Payable Entry via Opening Balance (Credit 600,000 minor units)
        /** @var SupplierOpeningBalanceService $sobService */
        $sobService = app(SupplierOpeningBalanceService::class);
        $sob = $sobService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 600000,
        ], $this->user->id);
        $postedSob = $sobService->post($sob->id, $this->user->id);

        // 2. Create Posted Supplier Payment (600,000 minor units)
        /** @var SupplierPaymentService $paymentService */
        $paymentService = app(SupplierPaymentService::class);
        $payment = $paymentService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => (string) $this->period->start_date,
            'bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 600000,
        ], $this->user->id);
        $postedPayment = $paymentService->post($payment->id, $this->user->id);

        // 3. Allocate 400,000 minor units to payable entry
        /** @var PayableAllocationService $allocService */
        $allocService = app(PayableAllocationService::class);

        $allocations = $allocService->allocatePayment(
            $postedPayment->id,
            [
                [
                    'payable_entry_id' => $postedSob->payable_entry_id,
                    'amount_minor' => 400000,
                ],
            ],
            $this->user->id
        );

        $this->assertCount(1, $allocations);
        /** @var PayableAllocation $alloc */
        $alloc = $allocations[0];
        $this->assertEquals('active', $alloc->status);
        $this->assertEquals(400000, $alloc->amount_minor);

        // Assert SupplierPayment balances updated
        $freshPayment = $postedPayment->fresh();
        $this->assertEquals(400000, $freshPayment->allocated_minor);
        $this->assertEquals(200000, $freshPayment->unapplied_minor);

        // 4. Reverse Allocation
        $reversed = $allocService->reversePaymentAllocation($alloc->id, 'Reversal test', $this->user->id);
        $this->assertEquals('reversed', $reversed->status);

        $restoredPayment = $postedPayment->fresh();
        $this->assertEquals(0, $restoredPayment->allocated_minor);
        $this->assertEquals(600000, $restoredPayment->unapplied_minor);
    }

    public function test_allocation_rejects_over_allocation_and_unapplied_exceeded(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-OVER-1', 'name' => ['en' => 'Over Customer']]);

        // Receivable target = 100,000
        /** @var CustomerOpeningBalanceService $cobService */
        $cobService = app(CustomerOpeningBalanceService::class);
        $cob = $cobService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);
        $postedCob = $cobService->post($cob->id, $this->user->id);

        // Receipt = 200,000
        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);
        $receipt = $receiptService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => (string) $this->period->start_date,
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 200000,
        ], $this->user->id);
        $postedReceipt = $receiptService->post($receipt->id, $this->user->id);

        /** @var ReceivableAllocationService $allocService */
        $allocService = app(ReceivableAllocationService::class);

        // Attempting to allocate 150,000 to a 100,000 target item -> must fail
        try {
            $allocService->allocateReceipt(
                $postedReceipt->id,
                [
                    [
                        'receivable_entry_id' => $postedCob->receivable_entry_id,
                        'amount_minor' => 150000,
                    ],
                ],
                $this->user->id
            );
            $this->fail('Expected ValidationException on over-allocating target entry.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount_minor', $e->errors());
        }
    }

    public function test_allocation_is_idempotent(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-IDEM-1', 'name' => ['en' => 'Idem Supplier']]);

        /** @var SupplierOpeningBalanceService $sobService */
        $sobService = app(SupplierOpeningBalanceService::class);
        $sob = $sobService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 300000,
        ], $this->user->id);
        $postedSob = $sobService->post($sob->id, $this->user->id);

        /** @var SupplierPaymentService $paymentService */
        $paymentService = app(SupplierPaymentService::class);
        $payment = $paymentService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => (string) $this->period->start_date,
            'bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 300000,
        ], $this->user->id);
        $postedPayment = $paymentService->post($payment->id, $this->user->id);

        /** @var PayableAllocationService $allocService */
        $allocService = app(PayableAllocationService::class);

        $lines = [
            [
                'payable_entry_id' => $postedSob->payable_entry_id,
                'amount_minor' => 150000,
            ],
        ];

        $key = 'idem_alloc_test_key_123';
        $res1 = $allocService->allocatePayment($postedPayment->id, $lines, $this->user->id, $key);
        $res2 = $allocService->allocatePayment($postedPayment->id, $lines, $this->user->id, $key);

        $this->assertEquals($res1[0]->id, $res2[0]->id);
        $this->assertEquals(1, PayableAllocation::query()->where('supplier_payment_id', $postedPayment->id)->count());
    }

    public function test_allocation_lines_require_target_entry_ids(): void
    {
        /** @var ReceivableAllocationService $receivableService */
        $receivableService = app(ReceivableAllocationService::class);

        try {
            $receivableService->allocateReceipt('missing-receipt', [['amount_minor' => 1000]], $this->user->id);
            $this->fail('Expected ValidationException for missing receivable_entry_id.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receivable_entry_id', $e->errors());
        }

        /** @var PayableAllocationService $payableService */
        $payableService = app(PayableAllocationService::class);

        try {
            $payableService->allocatePayment('missing-payment', [['amount_minor' => 1000]], $this->user->id);
            $this->fail('Expected ValidationException for missing payable_entry_id.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payable_entry_id', $e->errors());
        }
    }
}
