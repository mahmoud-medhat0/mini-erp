<?php

namespace Tests\Feature;

use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\ReceivableAllocationService;
use App\Application\Reports\CustomerStatementReportService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\AccountType;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\ReceivableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase3Slice9StressIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private AccountType $assetType;

    private AccountType $liabilityType;

    private Account $arControlAccount;

    private Account $cashAccount;

    private CashAccount $cashAccountModel;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('reports.view', 'web');

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo('reports.view');

        $this->fiscalYear = FiscalYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ]);

        $this->period = FinancialPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 1,
            'month' => 1,
            'name' => 'January 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
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

        $this->arControlAccount = Account::create([
            'code' => '110001',
            'name' => 'AR Control',
            'account_type_id' => $this->assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $this->cashAccount = Account::create([
            'code' => '101001',
            'name' => 'Main Cash Desk GL',
            'account_type_id' => $this->assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        AccountingAccountMapping::create([
            'key' => 'ar_control',
            'account_id' => $this->arControlAccount->id,
        ]);

        $this->cashAccountModel = CashAccount::create([
            'code' => 'CASH-01',
            'name' => 'Main Cash Desk',
            'gl_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
    }

    public function test_customer_receipt_posting_idempotency_and_concurrency(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-STRESS-1',
            'name' => 'Stress Test Customer',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $receipt = CustomerReceipt::create([
            'customer_id' => $customer->id,
            'cash_account_id' => $this->cashAccountModel->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 50000,
            'allocated_minor' => 0,
            'unapplied_minor' => 50000,
            'payment_method' => 'cash',
            'status' => 'draft',
        ]);

        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);

        // First Post Call
        $posted1 = $receiptService->post($receipt->id, $this->adminUser->id);
        $this->assertEquals('posted', $posted1->status);

        // Replay Same Post Call (Idempotency Replay)
        $posted2 = $receiptService->post($receipt->id, $this->adminUser->id);
        $this->assertEquals('posted', $posted2->status);

        // Assert exactly ONE JournalEntry was created for this receipt
        $journalCount = JournalEntry::where('source_type', 'customer_receipt')->where('source_id', $receipt->id)->count();
        $this->assertEquals(1, $journalCount);
    }

    public function test_period_close_versus_phase3_posting(): void
    {
        // Close the period
        $this->period->update(['status' => 'closed']);

        $customer = Customer::create([
            'code' => 'CUST-STRESS-2',
            'name' => 'Closed Period Customer',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $receipt = CustomerReceipt::create([
            'customer_id' => $customer->id,
            'cash_account_id' => $this->cashAccountModel->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 20000,
            'allocated_minor' => 0,
            'unapplied_minor' => 20000,
            'payment_method' => 'cash',
            'status' => 'draft',
        ]);

        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);

        $this->expectException(ValidationException::class);
        $receiptService->post($receipt->id, $this->adminUser->id);
    }

    public function test_allocation_over_pressure_and_remaining_balance_invariants(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-STRESS-3',
            'name' => 'Allocation Target Customer',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $journalEntry = JournalEntry::create([
            'entry_number' => 'JE-STRESS-001',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-01',
            'source_type' => 'manual',
            'status' => 'posted',
        ]);

        $receivableEntry = ReceivableEntry::create([
            'customer_id' => $customer->id,
            'journal_entry_id' => $journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'opening_balance',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-01-01',
            'currency' => 'EGP',
            'debit_minor' => 10000,
            'credit_minor' => 0,
        ]);

        $receipt = CustomerReceipt::create([
            'number' => 'REC-STRESS-001',
            'customer_id' => $customer->id,
            'cash_account_id' => $this->cashAccountModel->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-15',
            'currency' => 'EGP',
            'amount_minor' => 10000,
            'allocated_minor' => 0,
            'unapplied_minor' => 10000,
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);

        /** @var ReceivableAllocationService $allocationService */
        $allocationService = app(ReceivableAllocationService::class);

        // Attempting to allocate more than 10,000 minor units must fail
        $this->expectException(ValidationException::class);
        $allocationService->allocateReceipt(
            $receipt->id,
            [
                ['receivable_entry_id' => $receivableEntry->id, 'amount_minor' => 15000],
            ],
            $this->adminUser->id
        );
    }

    public function test_reports_are_strictly_read_only(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-RO-1',
            'name' => 'Read Only Test Customer',
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $countsBefore = [
            'customer' => Customer::count(),
            'journal_entry' => JournalEntry::count(),
            'receivable_entry' => ReceivableEntry::count(),
            'customer_receipt' => CustomerReceipt::count(),
        ];

        /** @var CustomerStatementReportService $statementService */
        $statementService = app(CustomerStatementReportService::class);
        $statementService->generate($customer->id, '2026-01-01', '2026-01-31', 'EGP');

        $countsAfter = [
            'customer' => Customer::count(),
            'journal_entry' => JournalEntry::count(),
            'receivable_entry' => ReceivableEntry::count(),
            'customer_receipt' => CustomerReceipt::count(),
        ];

        $this->assertEquals($countsBefore, $countsAfter, 'Executing report services must not mutate any database records.');
    }

    public function test_phase3_integrity_check_artisan_command(): void
    {
        $exitCode = Artisan::call('accounting:phase3-integrity-check');
        $this->assertEquals(0, $exitCode, 'accounting:phase3-integrity-check artisan command must return 0 (SUCCESS).');
    }

    public function test_no_tenant_company_branch_scoping_introduced(): void
    {
        $prohibitedColumns = ['company_id', 'branch_id', 'tenant_id', 'current_company', 'current_branch'];
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();

        foreach ($tables as $table) {
            foreach ($prohibitedColumns as $col) {
                $this->assertFalse(
                    Schema::hasColumn($table, $col),
                    "Prohibited tenancy/company column [{$col}] was found in table [{$table}]. Owner decisions mandate no tenant/company scoping."
                );
            }
        }
    }
}
