<?php

namespace Tests\Feature;

use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryOperationalDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('inventory.view');

        $this->warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'DT-WH'],
            [
                'name' => ['en' => 'DataTable Warehouse', 'ar' => 'مخزن جدول البيانات'],
                'warehouse_type' => 'standard',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        StockCount::query()->create([
            'number' => 'SC-DT-001',
            'count_date' => '2026-09-01',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'status' => 'draft',
            'reference' => 'COUNT-SEARCH',
        ]);

        StockAdjustment::query()->create([
            'number' => 'SA-DT-001',
            'adjustment_date' => '2026-09-02',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'status' => 'submitted',
            'reference' => 'ADJUST-SEARCH',
            'total_value_delta_minor' => 2500,
        ]);
    }

    public function test_inventory_index_payloads_do_not_ship_the_operational_register(): void
    {
        $this->actingAs($this->user)
            ->get('/inventory/stock-counts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/StockCounts')
                ->where('stockCounts', []));

        $this->actingAs($this->user)
            ->get('/inventory/adjustments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/StockAdjustments')
                ->where('adjustments', []));
    }

    public function test_stock_count_datatable_searches_and_filters_server_side(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/inventory/stock-counts/data?'.http_build_query($this->gridQuery('count', [
                'status' => 'draft',
                'warehouse_id' => $this->warehouse->id,
                'search' => ['value' => 'COUNT-SEARCH', 'regex' => 'false'],
            ])),
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.number', 'SC-DT-001')
            ->assertJsonPath('data.0.warehouse.code', $this->warehouse->code)
            ->assertJsonPath('data.0.lines_data', '0')
            ->assertJsonPath('data.0.variance_lines', '0');
    }

    public function test_stock_adjustment_datatable_searches_and_filters_server_side(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/inventory/adjustments/data?'.http_build_query($this->gridQuery('adjustment', [
                'status' => 'submitted',
                'warehouse_id' => $this->warehouse->id,
                'search' => ['value' => 'ADJUST-SEARCH', 'regex' => 'false'],
            ])),
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.number', 'SA-DT-001')
            ->assertJsonPath('data.0.total_value_delta_minor', 2500)
            ->assertJsonPath('data.0.lines_data', '0');
    }

    public function test_inventory_datatables_require_inventory_view_permission(): void
    {
        $stranger = User::factory()->create();

        foreach ([
            ['/inventory/stock-counts/data', 'count'],
            ['/inventory/adjustments/data', 'adjustment'],
        ] as [$path, $kind]) {
            $this->actingAs($stranger)
                ->getJson($path.'?'.http_build_query($this->gridQuery($kind)))
                ->assertForbidden();
        }
    }

    /** @param array<string, mixed> $overrides */
    private function gridQuery(string $kind, array $overrides = []): array
    {
        $date = $kind === 'count' ? 'count_date' : 'adjustment_date';
        $columns = [
            ['number', true, true],
            [$date, false, true],
            ['warehouse_name', false, false],
            ['status', false, true],
        ];

        if ($kind === 'adjustment') {
            $columns[] = ['total_value_delta_minor', false, true];
        }

        $columns[] = ['lines_data', false, false];

        if ($kind === 'count') {
            $columns[] = ['variance_lines', false, false];
        }

        $columns[] = ['actions', false, false];

        return array_replace_recursive([
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
            'order' => [['column' => '1', 'dir' => 'desc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);
    }
}
