<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashBankBookDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $financialUser;

    private User $unauthorizedUser;

    private FinancialPeriod $period;

    private Account $cashGlAccount;

    private Account $bankGlAccount;

    private CashAccount $cashAccount;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('reports.export', 'web');
        Permission::findOrCreate('view_financials', 'web');

        $this->financialUser = User::factory()->create();
        $this->financialUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);
        $this->unauthorizedUser = User::factory()->create();

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);
        $this->cashGlAccount = Account::query()->create([
            'code' => 'DT-CASH-GL',
            'name' => ['en' => 'DataTable cash account', 'ar' => 'حساب الخزينة'],
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);
        $this->bankGlAccount = Account::query()->create([
            'code' => 'DT-BANK-GL',
            'name' => ['en' => 'DataTable bank account', 'ar' => 'حساب البنك'],
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'DT-CASH',
            'name' => ['en' => 'Main cash', 'ar' => 'الخزينة الرئيسية'],
            'gl_account_id' => $this->cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
        $this->bankAccount = BankAccount::query()->create([
            'code' => 'DT-BANK',
            'name' => ['en' => 'Main bank', 'ar' => 'البنك الرئيسي'],
            'bank_name' => ['en' => 'National Bank', 'ar' => 'البنك الوطني'],
            'account_number' => '100200300',
            'gl_account_id' => $this->bankGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
    }

    public function test_cash_book_endpoint_search_keeps_running_balance_from_every_prior_scoped_movement(): void
    {
        $this->ledgerEntry($this->cashGlAccount, '2025-12-31', 500, 0, 'CASH-OPEN', 'Opening');
        $this->ledgerEntry($this->cashGlAccount, '2026-01-02', 100, 0, 'CASH-001', 'First receipt');
        $this->ledgerEntry($this->cashGlAccount, '2026-01-03', 0, 25, 'CASH-002', 'Supplier payment');
        $expected = $this->ledgerEntry($this->cashGlAccount, '2026-01-04', 200, 0, 'CASH-NEEDLE', 'Needle receipt');

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl(
            '/reports/cash-book/data',
            ['cash_account_id' => $this->cashAccount->id],
            false,
            'needle',
        ));

        $response->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ledger_entry_id', $expected->id)
            ->assertJsonPath('data.0.journal_number', 'CASH-NEEDLE')
            ->assertJsonPath('data.0.debit_minor', 200)
            ->assertJsonPath('data.0.credit_minor', 0)
            ->assertJsonPath('data.0.signed_movement_minor', 200)
            ->assertJsonPath('data.0.balance_after_minor', 775);
    }

    public function test_cash_book_endpoint_paginates_without_resetting_running_balance(): void
    {
        for ($day = 1; $day <= 12; $day++) {
            $this->ledgerEntry(
                $this->cashGlAccount,
                sprintf('2026-01-%02d', $day),
                100,
                0,
                sprintf('CASH-%03d', $day),
                sprintf('Cash row %d', $day),
            );
        }

        $url = $this->dataTableUrl(
            '/reports/cash-book/data',
            ['cash_account_id' => $this->cashAccount->id],
            false,
            '',
            10,
        );
        $response = $this->actingAs($this->financialUser)->getJson($url);

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 12)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.journal_number', 'CASH-011')
            ->assertJsonPath('data.0.balance_after_minor', 1100)
            ->assertJsonPath('data.1.journal_number', 'CASH-012')
            ->assertJsonPath('data.1.balance_after_minor', 1200);
    }

    public function test_bank_book_endpoint_returns_reconciliation_state_with_bounded_query_count(): void
    {
        $matchedEntry = null;
        for ($day = 1; $day <= 12; $day++) {
            $entry = $this->ledgerEntry(
                $this->bankGlAccount,
                sprintf('2026-01-%02d', $day),
                250,
                0,
                sprintf('BANK-%03d', $day),
                sprintf('Bank row %d', $day),
            );

            if ($day === 6) {
                $matchedEntry = $entry;
            }
        }

        $reconciliation = BankReconciliation::query()->create([
            'bank_account_id' => $this->bankAccount->id,
            'financial_period_id' => $this->period->id,
            'statement_reference' => 'DT-RECON-001',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'currency' => 'EGP',
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 3000,
            'status' => 'draft',
        ]);
        $line = BankReconciliationLine::query()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'line_no' => 1,
            'statement_date' => '2026-01-06',
            'reference' => 'BANK-006',
            'debit_minor' => 250,
            'credit_minor' => 0,
            'matched_ledger_entry_id' => $matchedEntry?->id,
            'status' => 'matched',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl(
            '/reports/bank-book/data',
            ['bank_account_id' => $this->bankAccount->id],
            true,
            'BANK-006',
        ));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_reconciled', true)
            ->assertJsonPath('data.0.reconciliation_line_id', $line->id)
            ->assertJsonPath('data.0.reconciliation_id', $reconciliation->id)
            ->assertJsonPath('data.0.balance_after_minor', 1500);

        $this->assertLessThanOrEqual(10, count($queries), 'Bank book endpoint must keep a bounded query count.');
        $this->assertNotEmpty(array_filter(
            $queries,
            fn (array $query): bool => str_contains($query['query'], 'bank_reconciliation_line'),
        ));
    }

    public function test_book_pages_receive_sql_summaries_without_eager_loaded_entries(): void
    {
        $this->ledgerEntry($this->cashGlAccount, '2025-12-31', 500, 0, 'CASH-OPEN', 'Opening');
        $this->ledgerEntry($this->cashGlAccount, '2026-01-02', 100, 0, 'CASH-001', 'Receipt');
        $this->ledgerEntry($this->cashGlAccount, '2026-01-03', 0, 25, 'CASH-002', 'Payment');

        $response = $this->actingAs($this->financialUser)->get(
            '/reports/cash-book?cash_account_id='.$this->cashAccount->id.'&date_from=2026-01-01&date_to=2026-01-31',
        );

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Reports/CashBook')
            ->where('report.opening_balance_minor', 500)
            ->where('report.period_debit_minor', 100)
            ->where('report.period_credit_minor', 25)
            ->where('report.closing_balance_minor', 575)
            ->missing('report.entries'));
    }

    public function test_csv_export_still_streams_the_complete_book_independently_of_table_page_size(): void
    {
        for ($day = 1; $day <= 12; $day++) {
            $this->ledgerEntry(
                $this->cashGlAccount,
                sprintf('2026-01-%02d', $day),
                100,
                0,
                sprintf('CASH-%03d', $day),
                sprintf('Cash row %d', $day),
            );
        }

        $response = $this->actingAs($this->financialUser)->get(
            '/reports/cash-book/export?cash_account_id='.$this->cashAccount->id.'&date_from=2026-01-01&date_to=2026-01-31',
        );

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CASH-001', $csv);
        $this->assertStringContainsString('CASH-012', $csv);
        $this->assertSame(12, substr_count($csv, 'CASH-0'));
    }

    public function test_book_data_endpoints_enforce_permissions_and_reject_unsafe_payloads(): void
    {
        foreach (['/reports/cash-book/data', '/reports/bank-book/data'] as $route) {
            $this->actingAs($this->unauthorizedUser)->getJson($route)->assertForbidden();
        }

        $query = $this->dataTablePayload(['cash_account_id' => $this->cashAccount->id], false);
        $query['length'] = 999;
        $query['date_to'] = '2025-12-31';
        $query['columns'][0]['name'] = 'entry_date; DROP TABLE ledger_entry';

        $this->actingAs($this->financialUser)
            ->getJson('/reports/cash-book/data?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['length', 'date_to', 'columns.0.name']);
    }

    private function ledgerEntry(
        Account $account,
        string $date,
        int $debitMinor,
        int $creditMinor,
        string $number,
        string $memo,
    ): LedgerEntry {
        $journal = JournalEntry::query()->create([
            'number' => $number,
            'entry_date' => $date,
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
            'account_id' => $account->id,
            'memo' => $memo,
            'debit_minor' => $debitMinor,
            'credit_minor' => $creditMinor,
            'currency' => 'EGP',
        ]);

        return LedgerEntry::query()->create([
            'journal_entry_id' => $journal->id,
            'journal_line_id' => $line->id,
            'account_id' => $account->id,
            'financial_period_id' => $this->period->id,
            'entry_date' => $date,
            'debit_minor' => $debitMinor,
            'credit_minor' => $creditMinor,
            'currency' => 'EGP',
            'created_at' => $date.' 10:00:00',
        ]);
    }

    /** @param array<string, string> $accountFilter */
    private function dataTableUrl(
        string $path,
        array $accountFilter,
        bool $bank,
        string $search = '',
        int $start = 0,
    ): string {
        return $path.'?'.http_build_query($this->dataTablePayload($accountFilter, $bank, $search, $start));
    }

    /** @param array<string, string> $accountFilter */
    private function dataTablePayload(
        array $accountFilter,
        bool $bank,
        string $search = '',
        int $start = 0,
    ): array {
        $columnNames = ['entry_date', 'journal_number', 'description'];

        if ($bank) {
            $columnNames[] = 'is_reconciled';
        }

        array_push($columnNames, 'debit_minor', 'credit_minor', 'balance_after_minor');

        return [
            ...$accountFilter,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'draw' => 7,
            'start' => $start,
            'length' => 10,
            'search' => ['value' => $search, 'regex' => 'false'],
            'columns' => array_map(fn (string $name): array => [
                'data' => $name,
                'name' => $name,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columnNames),
            'order' => [['column' => 0, 'dir' => 'asc']],
        ];
    }
}
