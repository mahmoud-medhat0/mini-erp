<?php

namespace Tests\Feature;

use App\Application\Accounting\BankBookQueryService;
use App\Application\Accounting\BankReconciliationService;
use App\Application\Accounting\CashBookQueryService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase3Slice6BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private BankReconciliationService $service;

    private BankBookQueryService $bankBookService;

    private CashBookQueryService $cashBookService;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $bankGlAcc;

    private Account $cashGlAcc;

    private BankAccount $bankAccount;

    private CashAccount $cashAccount;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create();

        $this->service = app(BankReconciliationService::class);
        $this->bankBookService = app(BankBookQueryService::class);
        $this->cashBookService = app(CashBookQueryService::class);

        Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name' => 'Egyptian Pound',
            'symbol' => 'EGP',
            'precision' => 2,
            'is_active' => true,
        ]);

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'FP 2026-01',
            'period_number' => 1,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_closed' => false,
        ]);

        $this->bankGlAcc = Account::query()->create([
            'code' => '1020-BANK',
            'name' => 'Bank GL Account',
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $this->cashGlAcc = Account::query()->create([
            'code' => '1010-CASH',
            'name' => 'Cash GL Account',
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'code' => 'BANK-001',
            'name' => 'National Bank Main',
            'account_number' => 'ACC-12345',
            'bank_name' => 'National Bank',
            'currency' => 'EGP',
            'gl_account_id' => $this->bankGlAcc->id,
            'is_active' => true,
        ]);

        $this->cashAccount = CashAccount::query()->create([
            'code' => 'CASH-MAIN',
            'name' => 'Main Cash Drawer',
            'currency' => 'EGP',
            'gl_account_id' => $this->cashGlAcc->id,
            'is_active' => true,
        ]);
    }

    public function test_spatie_teams_remains_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
    }

    public function test_slice6_tables_exist_without_tenant_company_or_branch_id(): void
    {
        $tables = ['bank_reconciliation', 'bank_reconciliation_line'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'));
            $this->assertFalse(Schema::hasColumn($table, 'company_id'));
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'));
        }
    }

    public function test_bank_reconciliation_draft_creation_validates_active_bank_account_and_period_date_range(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 100000,
            'statement_closing_balance_minor' => 300000,
            'statement_reference' => 'STMT-2026-01',
        ], $this->user->id);

        $this->assertEquals('draft', $recon->status);
        $this->assertEquals('EGP', $recon->currency);
        $this->assertEquals(100000, $recon->statement_opening_balance_minor);
        $this->assertEquals(300000, $recon->statement_closing_balance_minor);
        $this->assertEquals(200000, $recon->statement_movement_minor);

        // Validation failure: inactive bank account
        $this->bankAccount->update(['is_active' => false]);
        $this->expectException(ValidationException::class);

        $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ], $this->user->id);
    }

    public function test_bank_and_cash_book_services_derive_balances_only_from_posted_ledger_entries(): void
    {
        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-00001',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'description' => 'Test Receipt',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-01-10');
        $this->createLedgerEntry($journal, $this->cashGlAcc, 0, 200000, '2026-01-10');

        $bankStmt = $this->bankBookService->getStatement($this->bankAccount->id, '2026-01-01', '2026-01-31');
        $this->assertEquals(0, $bankStmt['opening_balance_minor']);
        $this->assertEquals(500000, $bankStmt['period_debit_minor']);
        $this->assertEquals(0, $bankStmt['period_credit_minor']);
        $this->assertEquals(500000, $bankStmt['closing_balance_minor']);
        $this->assertCount(1, $bankStmt['entries']);

        $cashStmt = $this->cashBookService->getStatement($this->cashAccount->id, '2026-01-01', '2026-01-31');
        $this->assertEquals(0, $cashStmt['opening_balance_minor']);
        $this->assertEquals(0, $cashStmt['period_debit_minor']);
        $this->assertEquals(200000, $cashStmt['period_credit_minor']);
        $this->assertEquals(-200000, $cashStmt['closing_balance_minor']);
        $this->assertCount(1, $cashStmt['entries']);
    }

    public function test_statement_line_creation_and_matching_validation_rules(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $this->user->id);

        $line = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
            'description' => 'Deposit Statement Line',
        ], $this->user->id);

        $this->assertEquals(1, $line->line_no);
        $this->assertEquals('unmatched', $line->status);
        $this->assertEquals(500000, $line->debit_minor);

        // Create matching ledger entry
        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-00002',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
        ]);

        $ledgerEntry = $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-01-15');

        $matchedLine = $this->service->matchLine($line->id, $ledgerEntry->id, $this->user->id);

        $this->assertEquals('matched', $matchedLine->status);
        $this->assertEquals($ledgerEntry->id, $matchedLine->matched_ledger_entry_id);

        $freshRecon = $recon->fresh();
        $this->assertEquals('in_progress', $freshRecon->status);
        $this->assertEquals(500000, $freshRecon->matched_system_movement_minor);
        $this->assertEquals(0, $freshRecon->difference_minor);
    }

    public function test_matching_rejects_ledger_entry_outside_reconciliation_date_range(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $this->user->id);

        $line = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
        ], $this->user->id);

        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-OUTSIDE-DATE',
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-02-01',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
        ]);

        $ledgerEntry = $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-02-01');

        $this->expectException(ValidationException::class);
        $this->service->matchLine($line->id, $ledgerEntry->id, $this->user->id);
    }

    public function test_matching_is_idempotent_and_rejects_duplicate_ledger_entry_matches(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $this->user->id);

        $line1 = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
        ], $this->user->id);

        $line2 = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
        ], $this->user->id);

        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-00003',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
        ]);

        $ledgerEntry = $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-01-15');

        // Match line 1
        $this->service->matchLine($line1->id, $ledgerEntry->id, $this->user->id);

        // Replay match line 1 with same key -> idempotent success
        $replayed = $this->service->matchLine($line1->id, $ledgerEntry->id, $this->user->id);
        $this->assertEquals('matched', $replayed->status);

        // Match line 2 with same ledger entry -> rejected!
        $this->expectException(ValidationException::class);
        $this->service->matchLine($line2->id, $ledgerEntry->id, $this->user->id);
    }

    public function test_finalization_validation_rules_and_successful_reconciliation(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $this->user->id);

        $line = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
        ], $this->user->id);

        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-00004',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
        ]);

        $ledgerEntry = $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-01-15');

        // Attempt finalize before match -> fails (unmatched statement lines)
        try {
            $this->service->finalize($recon->id, $this->user->id, 'finalize-attempt-1');
            $this->fail('Expected finalize to fail before matching statement line');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }

        // Match statement line
        $this->service->matchLine($line->id, $ledgerEntry->id, $this->user->id);

        // Finalize successfully
        $finalized = $this->service->finalize($recon->id, $this->user->id, 'finalize-attempt-2');

        $this->assertEquals('reconciled', $finalized->status);
        $this->assertNotNull($finalized->reconciled_at);

        // Activity log recorded
        $activity = Activity::query()
            ->where('event', 'finalize')
            ->where('properties->entity_type', 'bank_reconciliation')
            ->where('properties->entity_id', $recon->id)
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_finalized_reconciliation_and_lines_are_immutable(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $this->user->id);

        $line = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
        ], $this->user->id);

        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-00005',
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
        ]);

        $ledgerEntry = $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-01-15');

        $this->service->matchLine($line->id, $ledgerEntry->id, $this->user->id);
        $this->service->finalize($recon->id, $this->user->id);

        // Attempting to add line on finalized recon fails
        $this->expectException(ValidationException::class);
        $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-16',
            'debit_minor' => 10000,
            'credit_minor' => 0,
        ], $this->user->id);
    }

    public function test_database_blocks_mutation_of_finalized_reconciliation_records(): void
    {
        $recon = $this->service->createDraft([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $this->user->id);

        $line = $this->service->addLine($recon->id, [
            'statement_date' => '2026-01-15',
            'debit_minor' => 500000,
            'credit_minor' => 0,
        ], $this->user->id);

        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-DB-IMMUTABLE',
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
        ]);

        $ledgerEntry = $this->createLedgerEntry($journal, $this->bankGlAcc, 500000, 0, '2026-01-15');

        $this->service->matchLine($line->id, $ledgerEntry->id, $this->user->id);
        $this->service->finalize($recon->id, $this->user->id);

        try {
            DB::table('bank_reconciliation')
                ->where('id', $recon->id)
                ->update(['statement_reference' => 'MUTATED']);

            $this->fail('Expected database trigger to block finalized bank reconciliation update.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        try {
            DB::table('bank_reconciliation_line')
                ->where('id', $line->id)
                ->delete();

            $this->fail('Expected database trigger to block finalized bank reconciliation line delete.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
    }

    public function test_attachment_registry_accepts_bank_reconciliation(): void
    {
        $registry = config('erp_attachments.entities');

        $this->assertArrayHasKey('bank_reconciliation', $registry);
        $this->assertEquals('bank_reconciliation', $registry['bank_reconciliation']['table']);
        $this->assertContains('banks.reconcile', $registry['bank_reconciliation']['permissions']['attach']);
    }

    private function createLedgerEntry(
        JournalEntry $journal,
        Account $account,
        int $debit,
        int $credit,
        string $date
    ): LedgerEntry {
        $lineNo = (JournalLine::query()->where('journal_entry_id', $journal->id)->max('line_no') ?? 0) + 1;
        $jLine = JournalLine::query()->create([
            'journal_entry_id' => $journal->id,
            'line_no' => $lineNo,
            'account_id' => $account->id,
            'debit_minor' => $debit,
            'credit_minor' => $credit,
            'currency' => $account->currency,
            'fx_rate_e6' => 1000000,
            'debit_txn_minor' => $debit,
            'credit_txn_minor' => $credit,
        ]);

        return LedgerEntry::query()->create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $jLine->id,
            'account_id' => $account->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => $date,
            'currency' => $account->currency,
            'debit_minor' => $debit,
            'credit_minor' => $credit,
            'fx_rate_e6' => 1000000,
            'debit_txn_minor' => $debit,
            'credit_txn_minor' => $credit,
            'description' => 'Ledger Entry',
        ]);
    }
}
