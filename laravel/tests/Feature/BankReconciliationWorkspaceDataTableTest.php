<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BankReconciliationWorkspaceDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BankReconciliation $reconciliation;

    private FinancialPeriod $period;

    private Account $bankGlAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('banks.view', 'web');
        Permission::findOrCreate('banks.reconcile', 'web');
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['banks.view', 'banks.reconcile']);

        $year = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $year->id,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $type = AccountType::query()->create([
            'code' => 'ASSET-BR-WORKSPACE',
            'name' => ['en' => 'Assets', 'ar' => 'الأصول'],
            'category' => 'asset',
            'statement_type' => 'balance_sheet',
            'normal_balance' => 'debit',
        ]);
        $this->bankGlAccount = Account::query()->create([
            'code' => '102-BR-WORKSPACE',
            'name' => ['en' => 'Workspace Bank GL', 'ar' => 'حساب بنك المطابقة'],
            'account_type_id' => $type->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => true,
        ]);
        $bank = BankAccount::query()->create([
            'code' => 'BANK-WORKSPACE-DT',
            'name' => ['en' => 'Workspace Bank', 'ar' => 'بنك المطابقة'],
            'gl_account_id' => $this->bankGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
        $this->reconciliation = BankReconciliation::query()->create([
            'bank_account_id' => $bank->id,
            'financial_period_id' => $this->period->id,
            'statement_reference' => 'STMT-WORKSPACE-DT',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'currency' => 'EGP',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 7000,
            'status' => 'draft',
        ]);
    }

    public function test_workspace_header_does_not_eager_load_unbounded_rows(): void
    {
        BankReconciliationLine::query()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
            'line_no' => 1,
            'statement_date' => '2026-09-10',
            'reference' => 'HEADER-LINE',
            'debit_minor' => 1000,
            'credit_minor' => 0,
            'status' => 'unmatched',
        ]);

        $this->actingAs($this->user)
            ->get('/bank-reconciliations/'.$this->reconciliation->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BankReconciliations/Show')
                ->missing('reconciliation.lines')
                ->where('candidates', []));
    }

    public function test_statement_line_feed_searches_and_exposes_matched_entry_summary(): void
    {
        $matched = $this->ledgerEntry('MATCHED-JNL-001', 'Matched bank movement', 4500);
        BankReconciliationLine::query()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
            'line_no' => 1,
            'statement_date' => '2026-09-11',
            'reference' => 'MATCHED-LINE-001',
            'description' => 'Matched statement row',
            'debit_minor' => 4500,
            'credit_minor' => 0,
            'matched_ledger_entry_id' => $matched->id,
            'status' => 'matched',
        ]);

        $response = $this->actingAs($this->user)->getJson(
            '/bank-reconciliations/'.$this->reconciliation->id.'/lines/data?'.http_build_query(
                $this->dataTableQuery($this->lineColumns(), 'MATCHED-LINE-001'),
            ),
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.reference', 'MATCHED-LINE-001')
            ->assertJsonPath('data.0.matched_entry.id', $matched->id)
            ->assertJsonPath('data.0.matched_entry.journal_number', 'MATCHED-JNL-001');
    }

    public function test_candidate_feed_pages_only_unmatched_bank_ledger_entries(): void
    {
        $matched = $this->ledgerEntry('USED-JNL-001', 'Already matched movement', 2000);
        $candidate = $this->ledgerEntry('CANDIDATE-JNL-001', 'Available candidate movement', 2500);
        BankReconciliationLine::query()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
            'line_no' => 1,
            'statement_date' => '2026-09-12',
            'reference' => 'USED-LINE-001',
            'debit_minor' => 2000,
            'credit_minor' => 0,
            'matched_ledger_entry_id' => $matched->id,
            'status' => 'matched',
        ]);

        $response = $this->actingAs($this->user)->getJson(
            '/bank-reconciliations/'.$this->reconciliation->id.'/candidates/data?'.http_build_query(
                $this->dataTableQuery($this->candidateColumns(), 'CANDIDATE-JNL-001'),
            ),
        );

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $candidate->id)
            ->assertJsonPath('data.0.journal_number', 'CANDIDATE-JNL-001')
            ->assertJsonPath('data.0.description', 'Available candidate movement')
            ->assertJsonPath('data.0.amount_minor', 2500);
    }

    public function test_workspace_feeds_enforce_their_distinct_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('banks.view');
        $stranger = User::factory()->create();

        $linesUrl = '/bank-reconciliations/'.$this->reconciliation->id.'/lines/data?'.http_build_query(
            $this->dataTableQuery($this->lineColumns()),
        );
        $candidatesUrl = '/bank-reconciliations/'.$this->reconciliation->id.'/candidates/data?'.http_build_query(
            $this->dataTableQuery($this->candidateColumns()),
        );

        $this->actingAs($viewer)->getJson($linesUrl)->assertOk();
        $this->actingAs($viewer)->getJson($candidatesUrl)->assertForbidden();
        $this->actingAs($stranger)->getJson($linesUrl)->assertForbidden();
        $this->actingAs($stranger)->getJson($candidatesUrl)->assertForbidden();
    }

    private function ledgerEntry(string $number, string $memo, int $debitMinor): LedgerEntry
    {
        $journal = JournalEntry::query()->create([
            'number' => $number,
            'entry_date' => '2026-09-12',
            'financial_period_id' => $this->period->id,
            'source_type' => 'manual_journal',
            'source_id' => 'SRC-'.$number,
            'description' => $memo,
            'reference' => 'REF-'.$number,
            'currency' => 'EGP',
            'status' => 'posted',
        ]);
        $line = JournalLine::query()->create([
            'journal_entry_id' => $journal->id,
            'line_no' => 1,
            'account_id' => $this->bankGlAccount->id,
            'memo' => $memo,
            'debit_minor' => $debitMinor,
            'credit_minor' => 0,
            'currency' => 'EGP',
        ]);

        return LedgerEntry::query()->create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $line->id,
            'account_id' => $this->bankGlAccount->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-09-12',
            'debit_minor' => $debitMinor,
            'credit_minor' => 0,
            'currency' => 'EGP',
            'created_at' => '2026-09-12 10:00:00',
        ]);
    }

    /** @return list<array{0: string, 1: bool, 2: bool}> */
    private function lineColumns(): array
    {
        return [
            ['statement_date', true, true],
            ['reference', true, true],
            ['description', true, true],
            ['debit_minor', false, true],
            ['credit_minor', false, true],
            ['matched_entry', false, false],
            ['actions', false, false],
        ];
    }

    /** @return list<array{0: string, 1: bool, 2: bool}> */
    private function candidateColumns(): array
    {
        return [
            ['entry_date', true, true],
            ['journal_number', true, true],
            ['description', true, true],
            ['amount_minor', false, true],
            ['actions', false, false],
        ];
    }

    /** @param list<array{0: string, 1: bool, 2: bool}> $columns */
    private function dataTableQuery(array $columns, string $search = ''): array
    {
        return [
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => array_map(fn (array $column): array => [
                'data' => $column[0],
                'name' => $column[0],
                'searchable' => $column[1] ? 'true' : 'false',
                'orderable' => $column[2] ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => '0', 'dir' => 'asc']],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
