<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\IncomingChequeService;
use App\Application\Accounting\OutgoingChequeService;
use App\Application\Accounting\PeriodService;
use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Models\Account;
use App\Models\BankAccount;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase3Slice5ChequeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $arControlAccount;

    private Account $apControlAccount;

    private Account $chequesUnderCollAccount;

    private Account $chequesPayableAccount;

    private Account $bankGlAccount;

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

        // 2. Control & Cheque Accounts
        $this->arControlAccount = Account::query()->create([
            'code' => '1100-AR-SLICE5',
            'name' => ['en' => 'AR Control', 'ar' => 'العملاء'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->apControlAccount = Account::query()->create([
            'code' => '2100-AP-SLICE5',
            'name' => ['en' => 'AP Control', 'ar' => 'الموردين'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $this->chequesUnderCollAccount = Account::query()->create([
            'code' => '1050-CHQ-COLL-SLICE5',
            'name' => ['en' => 'Cheques Under Collection', 'ar' => 'شيكات برسم التحصيل'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $this->chequesPayableAccount = Account::query()->create([
            'code' => '2050-CHQ-PAY-SLICE5',
            'name' => ['en' => 'Cheques Payable', 'ar' => 'شيكات برسم الدفع'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $this->bankGlAccount = Account::query()->create([
            'code' => '1020-BANK-GL-SLICE5',
            'name' => ['en' => 'Bank GL', 'ar' => 'البنك'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        // 3. Master Data Bank
        $this->bankAccount = BankAccount::query()->create([
            'code' => 'BANK-MAIN-SLICE5',
            'name' => ['en' => 'CIB Corporate', 'ar' => 'بنك CIB'],
            'gl_account_id' => $this->bankGlAccount->id,
            'bank_name' => 'CIB',
            'account_number' => '1122334455',
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 0,
        ]);

        // 4. Mappings
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $this->arControlAccount->id, 'AR Control', $this->user->id);
        $mappingService->setMapping('ap_control', $this->apControlAccount->id, 'AP Control', $this->user->id);
        $mappingService->setMapping('cheques_under_collection', $this->chequesUnderCollAccount->id, 'Under Collection', $this->user->id);
        $mappingService->setMapping('cheques_payable', $this->chequesPayableAccount->id, 'Cheques Payable', $this->user->id);
    }

    public function test_spatie_teams_remains_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
    }

    public function test_slice5_tables_exist_without_tenant_company_or_branch_id(): void
    {
        $tables = ['incoming_cheque', 'outgoing_cheque'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");

            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must NOT contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "Table [{$table}] must NOT contain branch_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must NOT contain tenant_id.");
        }
    }

    public function test_incoming_cheque_receive_deposit_and_clear_lifecycle(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-IN-CHQ-1', 'name' => ['en' => 'Incoming Customer']]);

        /** @var IncomingChequeService $service */
        $service = app(IncomingChequeService::class);

        // 1. Create Draft
        $cheque = $service->createDraft([
            'customer_id' => $customer->id,
            'cheque_number' => 'PHYS-100200',
            'drawer_bank_name' => 'HSBC',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 350000,
        ], $this->user->id);

        $this->assertEquals('draft', $cheque->status);

        // 2. Receive Cheque (Dr Cheques Under Collection 350,000, Cr AR Control 350,000)
        $received = $service->receive($cheque->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->user->id);

        $this->assertEquals('received', $received->status);
        $this->assertNotNull($received->number);
        $this->assertStringStartsWith('ICHQ-2026-', $received->number);
        $this->assertNotNull($received->receive_journal_entry_id);
        $this->assertNotNull($received->receivable_entry_id);

        // ReceivableEntry Credit check
        $recEntry = ReceivableEntry::query()->find($received->receivable_entry_id);
        $this->assertNotNull($recEntry);
        $this->assertEquals(0, $recEntry->debit_minor);
        $this->assertEquals(350000, $recEntry->credit_minor);

        // 3. Deposit Cheque (no GL impact)
        $initialJournalCount = JournalEntry::query()->count();
        $deposited = $service->deposit($received->id, $this->bankAccount->id, (string) $this->period->start_date, $this->user->id);

        $this->assertEquals('deposited', $deposited->status);
        $this->assertEquals($initialJournalCount, JournalEntry::query()->count());

        // 4. Clear Cheque (Dr Bank GL 350,000, Cr Cheques Under Collection 350,000)
        $cleared = $service->clear($deposited->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->bankAccount->id, $this->user->id);

        $this->assertEquals('cleared', $cleared->status);
        $this->assertNotNull($cleared->clear_journal_entry_id);

        // Assert NO second ReceivableEntry created
        $this->assertEquals(1, ReceivableEntry::query()->where('customer_id', $customer->id)->count());

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'incoming_cheque')
            ->where('properties->entity_id', $cheque->id)
            ->where('event', 'clear')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_incoming_cheque_pre_clear_bounce_restores_ar_subledger(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-BOUNCE-1', 'name' => ['en' => 'Bounce Customer']]);

        /** @var IncomingChequeService $service */
        $service = app(IncomingChequeService::class);

        $cheque = $service->createDraft([
            'customer_id' => $customer->id,
            'cheque_number' => 'PHYS-BOUNCE-1',
            'drawer_bank_name' => 'HSBC',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 200000,
        ], $this->user->id);

        $received = $service->receive($cheque->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->user->id);

        // Pre-clear Bounce (Dr AR Control 200,000, Cr Cheques Under Collection 200,000)
        $bounced = $service->bounceBeforeClear($received->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, 'Insufficient funds', $this->user->id);

        $this->assertEquals('bounced', $bounced->status);
        $this->assertNotNull($bounced->bounce_journal_entry_id);
        $this->assertNotNull($bounced->bounce_receivable_entry_id);

        // Assert ReceivableEntry Debit created (restoring customer debt)
        $bounceRecEntry = ReceivableEntry::query()->find($bounced->bounce_receivable_entry_id);
        $this->assertNotNull($bounceRecEntry);
        $this->assertEquals(200000, $bounceRecEntry->debit_minor);
        $this->assertEquals(0, $bounceRecEntry->credit_minor);
    }

    public function test_incoming_cheque_post_clear_bounce_reverses_clearing_and_restores_ar(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-REJECT-1', 'name' => ['en' => 'Reject Customer']]);

        /** @var IncomingChequeService $service */
        $service = app(IncomingChequeService::class);

        $cheque = $service->createDraft([
            'customer_id' => $customer->id,
            'cheque_number' => 'PHYS-POST-CLEAR',
            'drawer_bank_name' => 'HSBC',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $this->user->id);

        $received = $service->receive($cheque->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->user->id);
        $cleared = $service->clear($received->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->bankAccount->id, $this->user->id);

        $journalCount = JournalEntry::query()->count();
        $bounced = $service->bounceBeforeClear($cleared->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, 'Post-clear bounce attempt', $this->user->id);

        $this->assertSame('bounced', $bounced->status);
        $this->assertNotNull($bounced->bounce_receivable_entry_id);
        $this->assertSame($journalCount + 2, JournalEntry::query()->count());

        $clearJournal = JournalEntry::query()->findOrFail($cleared->clear_journal_entry_id);
        $this->assertSame('reversed', $clearJournal->status);
        $this->assertNotNull($clearJournal->reversal_entry_id);

        $reversal = JournalEntry::query()->with('lines')->findOrFail($clearJournal->reversal_entry_id);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame($clearJournal->id, $reversal->reverses_entry_id);
        $this->assertSame(100000, (int) $reversal->lines->firstWhere('account_id', $this->chequesUnderCollAccount->id)?->debit_minor);
        $this->assertSame(100000, (int) $reversal->lines->firstWhere('account_id', $this->bankGlAccount->id)?->credit_minor);

        $bounceJournal = JournalEntry::query()->with('lines')->findOrFail($bounced->bounce_journal_entry_id);
        $this->assertSame(100000, (int) $bounceJournal->lines->firstWhere('account_id', $this->arControlAccount->id)?->debit_minor);
        $this->assertSame(100000, (int) $bounceJournal->lines->firstWhere('account_id', $this->chequesUnderCollAccount->id)?->credit_minor);

        $replayed = $service->bounceBeforeClear($bounced->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, 'Post-clear bounce attempt', $this->user->id);
        $this->assertSame($bounced->bounce_journal_entry_id, $replayed->bounce_journal_entry_id);
        $this->assertSame($journalCount + 2, JournalEntry::query()->count());
    }

    public function test_outgoing_cheque_issue_clear_and_pre_clear_cancel(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-OUT-CHQ-1', 'name' => ['en' => 'Outgoing Supplier']]);

        /** @var OutgoingChequeService $service */
        $service = app(OutgoingChequeService::class);

        // 1. Create Draft
        $cheque = $service->createDraft([
            'supplier_id' => $supplier->id,
            'bank_account_id' => $this->bankAccount->id,
            'cheque_number' => 'PHYS-OUT-7788',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 450000,
        ], $this->user->id);

        $this->assertEquals('draft', $cheque->status);

        // 2. Issue Cheque (Dr AP Control 450,000, Cr Cheques Payable 450,000)
        $issued = $service->issue($cheque->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->user->id);

        $this->assertEquals('issued', $issued->status);
        $this->assertNotNull($issued->number);
        $this->assertStringStartsWith('OCHQ-2026-', $issued->number);
        $this->assertNotNull($issued->issue_journal_entry_id);
        $this->assertNotNull($issued->payable_entry_id);

        // PayableEntry Debit check
        $payEntry = PayableEntry::query()->find($issued->payable_entry_id);
        $this->assertNotNull($payEntry);
        $this->assertEquals(450000, $payEntry->debit_minor);
        $this->assertEquals(0, $payEntry->credit_minor);

        // 3. Clear Cheque (Dr Cheques Payable 450,000, Cr Bank GL 450,000)
        $cleared = $service->clear($issued->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->user->id);

        $this->assertEquals('cleared', $cleared->status);
        $this->assertNotNull($cleared->clear_journal_entry_id);

        // Assert NO second PayableEntry created
        $this->assertEquals(1, PayableEntry::query()->where('supplier_id', $supplier->id)->count());
    }

    public function test_outgoing_cheque_pre_clear_cancel_restores_ap_subledger(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-CANCEL-1', 'name' => ['en' => 'Cancel Supplier']]);

        /** @var OutgoingChequeService $service */
        $service = app(OutgoingChequeService::class);

        $cheque = $service->createDraft([
            'supplier_id' => $supplier->id,
            'bank_account_id' => $this->bankAccount->id,
            'cheque_number' => 'PHYS-CANCEL-99',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 300000,
        ], $this->user->id);

        $issued = $service->issue($cheque->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, $this->user->id);

        // Pre-clear Cancel (Dr Cheques Payable 300,000, Cr AP Control 300,000)
        $cancelled = $service->cancelBeforeClear($issued->id, $this->fiscalYear->id, $this->period->id, (string) $this->period->start_date, 'Cancelled by agreement', $this->user->id);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancel_journal_entry_id);
        $this->assertNotNull($cancelled->cancel_payable_entry_id);

        // Assert PayableEntry Credit created (restoring supplier AP balance)
        $cancelPayEntry = PayableEntry::query()->find($cancelled->cancel_payable_entry_id);
        $this->assertNotNull($cancelPayEntry);
        $this->assertEquals(0, $cancelPayEntry->debit_minor);
        $this->assertEquals(300000, $cancelPayEntry->credit_minor);
    }

    public function test_incoming_cheque_post_clear_return_reverses_clearing(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST-RETURN-CLEAR', 'name' => ['en' => 'Return Customer']]);
        $service = app(IncomingChequeService::class);
        $date = (string) $this->period->start_date;

        $cheque = $service->createDraft([
            'customer_id' => $customer->id,
            'cheque_number' => 'PHYS-RETURN-CLEAR',
            'drawer_bank_name' => 'HSBC',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 125000,
        ], $this->user->id);

        $received = $service->receive($cheque->id, $this->fiscalYear->id, $this->period->id, $date, $this->user->id);
        $cleared = $service->clear($received->id, $this->fiscalYear->id, $this->period->id, $date, $this->bankAccount->id, $this->user->id);
        $returned = $service->returnBeforeClear($cleared->id, $this->fiscalYear->id, $this->period->id, $date, 'Bank returned after clearing', $this->user->id);

        $this->assertSame('returned', $returned->status);
        $this->assertNotNull($returned->return_receivable_entry_id);
        $this->assertSame('reversed', JournalEntry::query()->findOrFail($cleared->clear_journal_entry_id)->status);
        $this->assertSame(125000, (int) ReceivableEntry::query()->findOrFail($returned->return_receivable_entry_id)->debit_minor);
    }

    public function test_outgoing_cheque_post_clear_return_reverses_clearing_and_restores_ap(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-RETURN-CLEAR', 'name' => ['en' => 'Return Supplier']]);
        $service = app(OutgoingChequeService::class);
        $date = (string) $this->period->start_date;

        $cheque = $service->createDraft([
            'supplier_id' => $supplier->id,
            'bank_account_id' => $this->bankAccount->id,
            'cheque_number' => 'PHYS-OUT-RETURN-CLEAR',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 175000,
        ], $this->user->id);

        $issued = $service->issue($cheque->id, $this->fiscalYear->id, $this->period->id, $date, $this->user->id);
        $cleared = $service->clear($issued->id, $this->fiscalYear->id, $this->period->id, $date, $this->user->id);
        $returned = $service->returnBeforeClear($cleared->id, $this->fiscalYear->id, $this->period->id, $date, 'Supplier cheque returned after clearing', $this->user->id);

        $this->assertSame('returned', $returned->status);
        $this->assertNotNull($returned->return_payable_entry_id);

        $clearJournal = JournalEntry::query()->findOrFail($cleared->clear_journal_entry_id);
        $this->assertSame('reversed', $clearJournal->status);
        $reversal = JournalEntry::query()->with('lines')->findOrFail($clearJournal->reversal_entry_id);
        $this->assertSame($clearJournal->id, $reversal->reverses_entry_id);
        $this->assertSame(175000, (int) $reversal->lines->firstWhere('account_id', $this->bankGlAccount->id)?->debit_minor);
        $this->assertSame(175000, (int) $reversal->lines->firstWhere('account_id', $this->chequesPayableAccount->id)?->credit_minor);

        $returnJournal = JournalEntry::query()->with('lines')->findOrFail($returned->return_journal_entry_id);
        $this->assertSame(175000, (int) $returnJournal->lines->firstWhere('account_id', $this->chequesPayableAccount->id)?->debit_minor);
        $this->assertSame(175000, (int) $returnJournal->lines->firstWhere('account_id', $this->apControlAccount->id)?->credit_minor);
        $this->assertSame(175000, (int) PayableEntry::query()->findOrFail($returned->return_payable_entry_id)->credit_minor);
    }

    public function test_outgoing_cheque_post_clear_cancel_reverses_clearing(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUPP-CANCEL-CLEAR', 'name' => ['en' => 'Cancel Cleared Supplier']]);
        $service = app(OutgoingChequeService::class);
        $date = (string) $this->period->start_date;

        $cheque = $service->createDraft([
            'supplier_id' => $supplier->id,
            'bank_account_id' => $this->bankAccount->id,
            'cheque_number' => 'PHYS-OUT-CANCEL-CLEAR',
            'due_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 225000,
        ], $this->user->id);

        $issued = $service->issue($cheque->id, $this->fiscalYear->id, $this->period->id, $date, $this->user->id);
        $cleared = $service->clear($issued->id, $this->fiscalYear->id, $this->period->id, $date, $this->user->id);
        $cancelled = $service->cancelBeforeClear($cleared->id, $this->fiscalYear->id, $this->period->id, $date, 'Voided after bank clearing', $this->user->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancel_payable_entry_id);
        $this->assertSame('reversed', JournalEntry::query()->findOrFail($cleared->clear_journal_entry_id)->status);
        $this->assertSame(225000, (int) PayableEntry::query()->findOrFail($cancelled->cancel_payable_entry_id)->credit_minor);
    }

    public function test_attachment_registry_accepts_slice5_cheque_entities(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);
        $allowedTypes = $authorizer->allowedEntityTypes();

        $this->assertContains('incoming_cheque', $allowedTypes);
        $this->assertContains('outgoing_cheque', $allowedTypes);
    }
}
