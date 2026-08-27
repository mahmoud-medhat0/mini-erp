<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\ArApSubledgerService;
use App\Application\Accounting\CustomerOpeningBalanceService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\SupplierOpeningBalanceService;
use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Models\Account;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\PayableEntry;
use App\Models\ReceivableEntry;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase3Slice2ArApOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $arControlAccount;

    private Account $apControlAccount;

    private Account $offsetAccount;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(AccountingCoreSeeder::class);

        $this->user = User::factory()->create();

        // Retrieve existing seeded accounts or create explicit accounts
        $accounts = Account::query()->where('is_active', true)->get();
        $this->arControlAccount = $accounts->firstWhere('type', 'asset') ?? Account::query()->create([
            'code' => '1100-AR-TEST',
            'name' => ['en' => 'Accounts Receivable', 'ar' => 'العملاء'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->apControlAccount = $accounts->firstWhere('type', 'liability') ?? Account::query()->create([
            'code' => '2100-AP-TEST',
            'name' => ['en' => 'Accounts Payable', 'ar' => 'الموردين'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->offsetAccount = $accounts->firstWhere('type', 'equity') ?? Account::query()->create([
            'code' => '3900-OFFSET-TEST',
            'name' => ['en' => 'Opening Balance Offset', 'ar' => 'رصيد افتتاحي مقاصة'],
            'type' => 'equity',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $this->fiscalYear = $periodService->createFiscalYear(2026, '2026-01-01', '2026-12-31');

        /** @var FinancialPeriod $period */
        $period = FinancialPeriod::query()->where('fiscal_year_id', $this->fiscalYear->id)->where('status', 'open')->firstOrFail();
        $this->period = $period;

        // Configure mappings
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $this->arControlAccount->id, 'AR Control Account', $this->user->id);
        $mappingService->setMapping('ap_control', $this->apControlAccount->id, 'AP Control Account', $this->user->id);
        $mappingService->setMapping('opening_balance_offset', $this->offsetAccount->id, 'Opening Balance Offset Account', $this->user->id);
    }

    public function test_spatie_teams_remains_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
    }

    public function test_slice2_tables_exist_without_tenant_company_or_branch_id(): void
    {
        $this->assertTrue(Schema::hasTable('accounting_account_mapping'), 'Table [accounting_account_mapping] must exist.');
        $this->assertFalse(Schema::hasColumn('accounting_account_mapping', 'company_id'), 'Table [accounting_account_mapping] must NOT contain company_id.');
        $this->assertTrue(Schema::hasColumn('accounting_account_mapping', 'branch_id'), 'Table [accounting_account_mapping] must contain Phase 10 approved optional operational branch override.');
        $this->assertFalse(Schema::hasColumn('accounting_account_mapping', 'tenant_id'), 'Table [accounting_account_mapping] must NOT contain tenant_id.');

        $tables = [
            'receivable_entry',
            'payable_entry',
            'customer_opening_balance',
            'supplier_opening_balance',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");

            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must NOT contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "Table [{$table}] must NOT contain branch_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must NOT contain tenant_id.");
        }
    }

    public function test_mapping_table_rejects_unallowed_keys(): void
    {
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $this->expectException(ValidationException::class);
        $mappingService->setMapping('unsupported_mapping_key', $this->arControlAccount->id, 'Invalid', $this->user->id);
    }

    public function test_mapping_table_rejects_wrong_account_classification(): void
    {
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $this->expectException(ValidationException::class);
        $mappingService->setMapping('ap_control', $this->arControlAccount->id, 'Wrong AP mapping', $this->user->id);
    }

    public function test_posting_fails_when_required_mapping_is_missing(): void
    {
        DB::table('accounting_account_mapping')->where('key', 'ar_control')->delete();

        $customer = Customer::query()->create(['code' => 'CUST-MISSING-MAP', 'name' => ['en' => 'Missing Mapping Customer']]);

        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $cob = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $service->post($cob->id, $this->user->id);
    }

    public function test_posting_fails_when_mapped_account_is_inactive(): void
    {
        $accountToDeactivate = Account::query()->create([
            'code' => 'INACTIVE-AR-999',
            'name' => ['en' => 'Inactive AR', 'ar' => 'حساب عملاء معطل'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $accountToDeactivate->id, 'AR Control', $this->user->id);

        $customer = Customer::query()->create(['code' => 'CUST-ERR-1', 'name' => ['en' => 'Test Customer']]);

        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $cob = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 150000,
        ], $this->user->id);

        // Deactivate mapped account before posting
        $accountToDeactivate->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $service->post($cob->id, $this->user->id);
    }

    public function test_opening_balance_period_must_belong_to_selected_fiscal_year(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-FY-MISMATCH', 'name' => ['en' => 'Fiscal Mismatch']]);

        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $otherFiscalYear = $periodService->createFiscalYear(2027, '2027-01-01', '2027-12-31');

        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $otherFiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);
    }

    public function test_customer_opening_balance_blocks_duplicate_active_balance_for_same_fiscal_year(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-DUP-OB', 'name' => ['en' => 'Duplicate Opening']]);

        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $payload = [
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ];

        $service->create($payload, $this->user->id);

        $this->expectException(ValidationException::class);
        $service->create([...$payload, 'amount_minor' => 200000], $this->user->id);
    }

    public function test_opening_balance_rejects_non_unit_fx_until_exact_fx_posting_exists(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-FX-OB', 'name' => ['en' => 'FX Supplier']]);

        /** @var SupplierOpeningBalanceService $service */
        $service = app(SupplierOpeningBalanceService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
            'fx_rate_e6' => 1200000,
        ], $this->user->id);
    }

    public function test_posting_rejects_mapped_account_currency_mismatch(): void
    {
        $usdAr = Account::query()->create([
            'code' => '1200-USD-AR',
            'name' => ['en' => 'USD Accounts Receivable', 'ar' => 'عملاء دولار'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'USD',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $usdAr->id, 'USD AR Control', $this->user->id);

        $customer = Customer::query()->create(['code' => 'CUST-CURR-MISMATCH', 'name' => ['en' => 'Currency Mismatch']]);

        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $cob = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $service->post($cob->id, $this->user->id);
    }

    public function test_customer_opening_balance_posting_subledger_creation_and_reconciliation(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUST-OB-100',
            'name' => ['en' => 'Delta Traders', 'ar' => 'تجارة دلتا'],
        ]);

        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $cob = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'reference' => 'REF-CUST-OB-100',
            'description' => 'Opening balance migration',
            'currency' => 'EGP',
            'amount_minor' => 500000, // 5,000.00 EGP
        ], $this->user->id);

        $this->assertEquals('draft', $cob->status);

        $posted = $service->post($cob->id, $this->user->id);

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->receivable_entry_id);

        // Assert ReceivableEntry created correctly
        $receivableEntry = ReceivableEntry::query()->find($posted->receivable_entry_id);
        $this->assertNotNull($receivableEntry);
        $this->assertEquals($customer->id, $receivableEntry->customer_id);
        $this->assertEquals(500000, $receivableEntry->debit_minor);
        $this->assertEquals(0, $receivableEntry->credit_minor);

        // Assert Subledger Reconciliation
        /** @var ArApSubledgerService $subledgerService */
        $subledgerService = app(ArApSubledgerService::class);
        $rec = $subledgerService->reconcileCustomerSubledgerToGl();

        $this->assertTrue($rec['is_reconciled']);
        $this->assertEquals(500000, $rec['subledger_total']);
        $this->assertEquals(500000, $rec['gl_control_total']);
        $this->assertEquals(500000, $subledgerService->getCustomerBalance($customer->id));

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'customer_opening_balance')
            ->where('properties->entity_id', $cob->id)
            ->where('event', 'post')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_supplier_opening_balance_posting_subledger_creation_and_reconciliation(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUPP-OB-200',
            'name' => ['en' => 'Omega Hardware', 'ar' => 'أوميجا للأدوات'],
        ]);

        /** @var SupplierOpeningBalanceService $service */
        $service = app(SupplierOpeningBalanceService::class);

        $sob = $service->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'reference' => 'REF-SUPP-OB-200',
            'description' => 'Supplier initial opening balance',
            'currency' => 'EGP',
            'amount_minor' => 750000, // 7,500.00 EGP
        ], $this->user->id);

        $this->assertEquals('draft', $sob->status);

        $posted = $service->post($sob->id, $this->user->id);

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->payable_entry_id);

        // Assert PayableEntry created correctly
        $payableEntry = PayableEntry::query()->find($posted->payable_entry_id);
        $this->assertNotNull($payableEntry);
        $this->assertEquals($supplier->id, $payableEntry->supplier_id);
        $this->assertEquals(0, $payableEntry->debit_minor);
        $this->assertEquals(750000, $payableEntry->credit_minor);

        // Assert Subledger Reconciliation
        /** @var ArApSubledgerService $subledgerService */
        $subledgerService = app(ArApSubledgerService::class);
        $rec = $subledgerService->reconcileSupplierSubledgerToGl();

        $this->assertTrue($rec['is_reconciled']);
        $this->assertEquals(750000, $rec['subledger_total']);
        $this->assertEquals(750000, $rec['gl_control_total']);
        $this->assertEquals(750000, $subledgerService->getSupplierBalance($supplier->id));

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'supplier_opening_balance')
            ->where('properties->entity_id', $sob->id)
            ->where('event', 'post')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_idempotency_of_repeated_posting_calls(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-IDEM-1', 'name' => ['en' => 'Idem Customer']]);
        /** @var CustomerOpeningBalanceService $service */
        $service = app(CustomerOpeningBalanceService::class);

        $cob = $service->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => (string) $this->period->start_date,
            'currency' => 'EGP',
            'amount_minor' => 300000,
        ], $this->user->id);

        $postedFirst = $service->post($cob->id, $this->user->id);
        $postedSecond = $service->post($cob->id, $this->user->id);

        $this->assertEquals($postedFirst->id, $postedSecond->id);
        $this->assertEquals($postedFirst->journal_entry_id, $postedSecond->journal_entry_id);
        $this->assertEquals($postedFirst->receivable_entry_id, $postedSecond->receivable_entry_id);

        // Verify only 1 ReceivableEntry exists for this customer
        $this->assertEquals(1, ReceivableEntry::query()->where('customer_id', $customer->id)->count());
    }

    public function test_attachment_registry_accepts_slice2_opening_balance_entities(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);
        $allowedTypes = $authorizer->allowedEntityTypes();

        $this->assertContains('customer_opening_balance', $allowedTypes);
        $this->assertContains('supplier_opening_balance', $allowedTypes);
    }
}
