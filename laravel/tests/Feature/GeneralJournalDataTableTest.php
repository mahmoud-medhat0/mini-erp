<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PeriodService;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\FinancialPeriod;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GeneralJournalDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FinancialPeriod $period;

    private Account $cashAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('accounting.view', 'accounting.create');

        $fiscalYear = app(PeriodService::class)->createFiscalYear(2026, '2026-01-01', '2026-12-31');
        $this->period = $fiscalYear->periods()->first();

        $group = AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => '1000',
            'name' => ['en' => 'Current Assets', 'ar' => 'الأصول المتداولة'],
            'type' => 'asset',
        ]);

        $this->cashAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1100',
            'name' => ['en' => 'Cash', 'ar' => 'الصندوق'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'currency' => 'EGP',
        ]);

        $this->revenueAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '4100',
            'name' => ['en' => 'Sales Revenue', 'ar' => 'إيراد المبيعات'],
            'type' => 'revenue',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'currency' => 'EGP',
        ]);

        $draftService = app(JournalDraftService::class);

        foreach ([
            ['reference' => 'JV-SEARCH-001', 'description' => 'Findable rent payment', 'entry_date' => '2026-01-10'],
            ['reference' => 'JV-OTHER-002', 'description' => 'Unrelated entry', 'entry_date' => '2026-01-15'],
        ] as $draft) {
            $draftService->createDraft(
                [
                    'entry_date' => $draft['entry_date'],
                    'financial_period_id' => $this->period->id,
                    'currency' => 'EGP',
                    'description' => $draft['description'],
                    'reference' => $draft['reference'],
                ],
                [
                    ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                    ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
                ],
                $this->user->id,
            );
        }
    }

    /**
     * Mirror the column payload the grid sends.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function gridQuery(array $overrides = []): array
    {
        $columns = [];

        foreach ([
            ['number', true, true],
            ['entry_date', false, true],
            ['description', false, true],
            ['reference', false, true],
            ['status', false, true],
            ['createdBy', false, false],
            ['id', false, false],
        ] as $index => [$name, $searchable, $orderable]) {
            $columns[$index] = [
                'data' => $name,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => $orderable ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return array_merge([
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => $columns,
            'order' => [['column' => '1', 'dir' => 'desc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fetch(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->getJson('/accounting/journal/data?'.http_build_query($this->gridQuery($overrides)));
    }

    public function test_index_page_still_exposes_a_valid_paginator_shape_with_no_relations(): void
    {
        $response = $this->actingAs($this->user)->get('/accounting/journal');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/GeneralJournal')
            ->has('journals.data')
            ->has('journals.links')
            ->has('journals.current_page')
            ->has('journals.total')
        );
    }

    public function test_datatable_returns_rows_with_the_created_by_relation(): void
    {
        $response = $this->fetch();
        $response->assertOk();

        $rows = $response->json('data');
        $this->assertCount(2, $rows);

        $row = collect($rows)->firstWhere('reference', 'JV-SEARCH-001');
        $this->assertNotNull($row);
        $this->assertSame($this->user->name, $row['createdBy']['name']);
        $this->assertSame('draft', $row['status']);
    }

    public function test_datatable_filters_by_status(): void
    {
        $response = $this->fetch(['status' => 'draft']);
        $response->assertOk();
        $this->assertSame(2, $response->json('recordsFiltered'));

        $response = $this->fetch(['status' => 'posted']);
        $response->assertOk();
        $this->assertSame(0, $response->json('recordsFiltered'));
    }

    public function test_datatable_filters_by_period(): void
    {
        $response = $this->fetch(['period_id' => $this->period->id]);
        $response->assertOk();
        $this->assertSame(2, $response->json('recordsFiltered'));

        $response = $this->fetch(['period_id' => (string) Str::uuid()]);
        $response->assertOk();
        $this->assertSame(0, $response->json('recordsFiltered'));
    }

    public function test_datatable_searches_across_number_description_and_reference(): void
    {
        foreach (['JV-SEARCH-001', 'Findable rent', 'rent payment'] as $needle) {
            $response = $this->fetch(['search' => ['value' => $needle, 'regex' => 'false']]);
            $response->assertOk();
            $this->assertSame(1, $response->json('recordsFiltered'), "Search for [{$needle}] should match exactly one row.");
            $this->assertSame('JV-SEARCH-001', $response->json('data.0.reference'));
        }
    }

    public function test_datatable_can_order_by_every_orderable_column(): void
    {
        foreach (['0', '1', '2', '3', '4'] as $columnIndex) {
            $this->fetch(['order' => [['column' => $columnIndex, 'dir' => 'asc']]])
                ->assertOk("Ordering by column {$columnIndex} must not break the query.");
        }
    }

    public function test_datatable_requires_the_accounting_view_permission(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/accounting/journal/data?'.http_build_query($this->gridQuery()))
            ->assertForbidden();
    }
}
