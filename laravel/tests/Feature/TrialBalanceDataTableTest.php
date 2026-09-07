<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\FinancialPeriod;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialBalanceDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('accounting.view', 'accounting.create', 'accounting.post', 'view_financials');

        $fiscalYear = app(PeriodService::class)->createFiscalYear(2026, '2026-01-01', '2026-12-31');
        $this->period = $fiscalYear->periods()->firstOrFail();

        $group = AccountGroup::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'TB-GROUP',
            'name' => ['en' => 'Trial Balance Group', 'ar' => 'مجموعة ميزان المراجعة'],
            'type' => 'asset',
        ]);

        $cash = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'TB-1100',
            'name' => ['en' => 'Searchable Cash', 'ar' => 'النقدية القابلة للبحث'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'currency' => 'EGP',
        ]);

        $revenue = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'TB-4100',
            'name' => ['en' => 'Trial Revenue', 'ar' => 'إيراد المراجعة'],
            'type' => 'revenue',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'currency' => 'EGP',
        ]);

        $entry = app(JournalDraftService::class)->createDraft([
            'entry_date' => '2026-01-15',
            'financial_period_id' => $this->period->id,
            'currency' => 'EGP',
            'description' => 'Trial balance DataTable fixture',
        ], [
            ['account_id' => $cash->id, 'debit_minor' => 12500, 'credit_minor' => 0],
            ['account_id' => $revenue->id, 'debit_minor' => 0, 'credit_minor' => 12500],
        ], $this->user->id);

        app(PostingEngine::class)->post($entry, $this->user->id);
    }

    public function test_index_payload_contains_summary_but_not_trial_balance_rows(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/trial-balance?period_id='.$this->period->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/TrialBalance')
                ->missing('rows')
                ->where('totals.debit', 12500)
                ->where('totals.credit', 12500)
                ->where('totals.is_balanced', true));
    }

    public function test_datatable_pages_searches_and_preserves_trial_balance_figures(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/accounting/trial-balance/data?'.http_build_query($this->gridQuery([
                'period_id' => $this->period->id,
            ])),
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'));
        $this->assertSame(12500, $rows->sum('debit_balance'));
        $this->assertSame(12500, $rows->sum('credit_balance'));

        $searched = $this->actingAs($this->user)->getJson(
            '/accounting/trial-balance/data?'.http_build_query($this->gridQuery([
                'period_id' => $this->period->id,
                'search' => ['value' => 'Searchable Cash', 'regex' => 'false'],
            ])),
        );

        $searched->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.account_code', 'TB-1100')
            ->assertJsonPath('data.0.account_name.en', 'Searchable Cash');
    }

    public function test_datatable_requires_accounting_view_permission(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/accounting/trial-balance/data?'.http_build_query($this->gridQuery()))
            ->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function gridQuery(array $overrides = []): array
    {
        $columns = [];

        foreach ([
            ['account_code', true, true],
            ['account_name', true, false],
            ['type', true, true],
            ['debit_balance', false, true],
            ['credit_balance', false, true],
        ] as $index => [$name, $searchable, $orderable]) {
            $columns[$index] = [
                'data' => $name,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => $orderable ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return array_replace_recursive([
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => $columns,
            'order' => [['column' => '0', 'dir' => 'asc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);
    }
}
