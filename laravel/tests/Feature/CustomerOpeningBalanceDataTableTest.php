<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOpeningBalanceDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['customers.view']);

        Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'symbol' => 'EGP', 'decimals' => 2, 'is_active' => true],
        );

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        // One opening balance per customer per fiscal year is a DB-level rule,
        // so each row below needs its own customer.
        $this->acme = Customer::query()->create([
            'code' => 'CUST-001',
            'name' => ['en' => 'Acme Trading Co', 'ar' => 'شركة أكمي للتجارة'],
            'status' => 'active',
            'lock_version' => 0,
        ]);

        $nile = Customer::query()->create([
            'code' => 'CUST-002',
            'name' => ['en' => 'Nile Distribution', 'ar' => 'النيل للتوزيع'],
            'status' => 'active',
            'lock_version' => 0,
        ]);

        foreach ([[$this->acme, 'AR-OB-001', 'draft'], [$nile, 'AR-OB-002', 'posted']] as [$customer, $reference, $status]) {
            CustomerOpeningBalance::query()->create([
                'customer_id' => $customer->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'entry_date' => '2026-01-01',
                'reference' => $reference,
                'currency' => 'EGP',
                'amount_minor' => 100000,
                'status' => $status,
            ]);
        }
    }

    /**
     * Mirror the column payload the grid actually sends, so ambiguous joined
     * columns and cross-column search are exercised the way the browser does it.
     *
     * @return array<string, mixed>
     */
    private function gridQuery(array $overrides = []): array
    {
        $columns = [];

        foreach ([
            ['customer_name', true, true],
            ['entry_date', true, true],
            ['reference', true, true],
            ['currency', true, true],
            ['amount_minor', false, true],
            ['status', false, true],
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

    public function test_datatable_returns_customer_name_as_translations_object(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/customer-opening-balances/data?'.http_build_query($this->gridQuery()));

        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('customer_code');
        $this->assertCount(2, $rows);

        // The client localizes the name, so the feed must carry every translation.
        $this->assertSame(
            ['en' => 'Acme Trading Co', 'ar' => 'شركة أكمي للتجارة'],
            $rows['CUST-001']['customer_name'],
        );
        $this->assertSame(
            ['en' => 'Nile Distribution', 'ar' => 'النيل للتوزيع'],
            $rows['CUST-002']['customer_name'],
        );
    }

    public function test_datatable_filters_by_status(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/customer-opening-balances/data?'.http_build_query($this->gridQuery(['status' => 'draft'])));

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('AR-OB-001', $rows[0]['reference']);
        $this->assertSame(1, $response->json('recordsFiltered'));
    }

    public function test_datatable_search_matches_arabic_and_english_customer_names(): void
    {
        foreach (['Acme', 'أكمي', 'CUST-001'] as $needle) {
            $response = $this->actingAs($this->user)->getJson(
                '/customer-opening-balances/data?'.http_build_query(
                    $this->gridQuery(['search' => ['value' => $needle, 'regex' => 'false']]),
                ),
            );

            $response->assertOk();
            $this->assertSame(1, $response->json('recordsFiltered'), "Search for [{$needle}] should match the Acme row.");
            $this->assertSame('AR-OB-001', $response->json('data.0.reference'));
        }
    }

    public function test_datatable_requires_the_customers_view_permission(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/customer-opening-balances/data?'.http_build_query($this->gridQuery()))
            ->assertForbidden();
    }

    public function test_datatable_can_order_by_columns_present_on_both_joined_tables(): void
    {
        foreach ([['5', 'status'], ['0', 'customer_name'], ['1', 'entry_date']] as [$columnIndex, $label]) {
            $response = $this->actingAs($this->user)->getJson(
                '/customer-opening-balances/data?'.http_build_query(
                    $this->gridQuery(['order' => [['column' => $columnIndex, 'dir' => 'asc']]]),
                ),
            );

            $response->assertOk();
            $this->assertCount(2, $response->json('data'), "Ordering by [{$label}] should not break the query.");
        }
    }

    public function test_datatable_search_does_not_break_on_non_text_columns(): void
    {
        // entry_date and amount_minor are not text; a naive LIKE would explode.
        $response = $this->actingAs($this->user)->getJson(
            '/customer-opening-balances/data?'.http_build_query(
                $this->gridQuery(['search' => ['value' => '2026', 'regex' => 'false']]),
            ),
        );

        $response->assertOk();
        $this->assertSame(2, $response->json('recordsFiltered'));
    }
}
