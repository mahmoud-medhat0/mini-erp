<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BankReconciliationReportDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BankReconciliation $reconciliation;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('view_financials', 'web');
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['reports.view', 'view_financials']);

        $year = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $year->id,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $type = AccountType::query()->create([
            'code' => 'ASSET-BR-DT',
            'name' => ['en' => 'Assets', 'ar' => 'الأصول'],
            'category' => 'asset',
            'statement_type' => 'balance_sheet',
            'normal_balance' => 'debit',
        ]);
        $account = Account::query()->create([
            'code' => '102-BR-DT',
            'name' => ['en' => 'Bank GL', 'ar' => 'حساب البنك'],
            'account_type_id' => $type->id,
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);
        $bank = BankAccount::query()->create([
            'code' => 'BANK-REPORT-DT',
            'name' => ['en' => 'Report Bank', 'ar' => 'بنك التقرير'],
            'gl_account_id' => $account->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
        $this->reconciliation = BankReconciliation::query()->create([
            'bank_account_id' => $bank->id,
            'financial_period_id' => $period->id,
            'statement_reference' => 'STMT-REPORT-DT',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'currency' => 'EGP',
            'statement_opening_balance_minor' => 10000,
            'statement_closing_balance_minor' => 12500,
            'statement_movement_minor' => 2500,
            'system_movement_minor' => 2500,
            'matched_system_movement_minor' => 0,
            'difference_minor' => 2500,
            'status' => 'draft',
        ]);
        BankReconciliationLine::query()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
            'line_no' => 1,
            'statement_date' => '2026-09-10',
            'reference' => 'LINE-REPORT-DT',
            'description' => 'Server-side reconciliation row',
            'debit_minor' => 2500,
            'credit_minor' => 0,
            'status' => 'unmatched',
        ]);
    }

    public function test_report_index_feed_searches_and_returns_aggregate_counts(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/reports/bank-reconciliations/data?'.http_build_query($this->indexQuery()),
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.statement_reference', 'STMT-REPORT-DT')
            ->assertJsonPath('data.0.bank_account_code', 'BANK-REPORT-DT')
            ->assertJsonPath('data.0.total_statement_lines_count', 1)
            ->assertJsonPath('data.0.matched_statement_lines_count', 0)
            ->assertJsonPath('data.0.difference_minor', 2500);
    }

    public function test_report_detail_header_omits_lines_and_feed_pages_them(): void
    {
        $this->actingAs($this->user)
            ->get('/reports/bank-reconciliations/'.$this->reconciliation->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('detail.reconciliation.lines', []));

        $this->actingAs($this->user)
            ->getJson('/reports/bank-reconciliations/'.$this->reconciliation->id.'/data?'.http_build_query($this->detailQuery()))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.reference', 'LINE-REPORT-DT')
            ->assertJsonPath('data.0.statement_net_minor', 2500)
            ->assertJsonPath('data.0.matched_net_minor', null);
    }

    public function test_both_report_feeds_keep_report_permissions(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/reports/bank-reconciliations/data?'.http_build_query($this->indexQuery()))
            ->assertForbidden();
        $this->actingAs($stranger)
            ->getJson('/reports/bank-reconciliations/'.$this->reconciliation->id.'/data?'.http_build_query($this->detailQuery()))
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function indexQuery(): array
    {
        return $this->dataTableQuery([
            ['bank_account_name', true, true],
            ['statement_reference', true, true],
            ['date_from', false, true],
            ['status', true, true],
            ['matched_statement_lines_count', false, true],
            ['difference_minor', false, true],
            ['actions', false, false],
        ], 'STMT-REPORT-DT', 2);
    }

    /** @return array<string, mixed> */
    private function detailQuery(): array
    {
        return $this->dataTableQuery([
            ['statement_date', true, true],
            ['reference', true, true],
            ['statement_net_minor', false, true],
            ['journal_number', true, false],
            ['matched_net_minor', false, false],
        ], 'LINE-REPORT-DT', 0);
    }

    /** @param list<array{0: string, 1: bool, 2: bool}> $columns */
    private function dataTableQuery(array $columns, string $search, int $orderColumn): array
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
            'order' => [['column' => (string) $orderColumn, 'dir' => 'desc']],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
