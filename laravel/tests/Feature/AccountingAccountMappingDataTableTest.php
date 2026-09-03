<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AccountingAccountMappingDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            RbacSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
            AccountingCoreSeeder::class,
        ]);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo('accounting.mappings');

        $this->branch = Branch::query()->create([
            'code' => 'BR-DT',
            'name' => ['en' => 'DataTable Branch', 'ar' => 'فرع الجدول'],
            'is_active' => true,
        ]);

        $account = Account::query()->create([
            'code' => 'DT9001',
            'name' => ['en' => 'Branch Override Account', 'ar' => 'حساب تجاوز الفرع'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        app(AccountingAccountMappingService::class)->setMapping(
            key: 'inventory_asset',
            accountId: $account->id,
            description: 'Branch override for the grid',
            actorId: $this->user->id,
            branchId: $this->branch->id,
        );
    }

    /**
     * Mirror the column payload the grid sends, so joined-column ambiguity and
     * cross-column search are exercised the way the browser does it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function gridQuery(array $overrides = []): array
    {
        $columns = [];

        foreach ([
            ['key', true, true],
            ['scope', false, true],
            ['account', false, true],
            ['description', false, true],
            ['is_system', false, false],
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
            'order' => [['column' => '0', 'dir' => 'asc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fetch(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->getJson('/accounting/account-mappings/data?'.http_build_query($this->gridQuery($overrides)));
    }

    public function test_datatable_keeps_nested_account_and_branch_for_the_delete_confirmation(): void
    {
        $response = $this->fetch(['scope' => 'branch']);
        $response->assertOk();

        $rows = $response->json('data');
        $this->assertNotEmpty($rows);

        $row = collect($rows)->firstWhere('key', 'inventory_asset');
        $this->assertNotNull($row);

        // The client builds its delete confirmation from these nested objects.
        $this->assertSame('DT9001', $row['account']['code']);
        $this->assertSame(['en' => 'Branch Override Account', 'ar' => 'حساب تجاوز الفرع'], $row['account']['name']);
        $this->assertSame('BR-DT', $row['branch']['code']);
        $this->assertSame(['en' => 'DataTable Branch', 'ar' => 'فرع الجدول'], $row['branch']['name']);
    }

    public function test_datatable_filters_by_scope(): void
    {
        $branchOnly = $this->fetch(['scope' => 'branch']);
        $branchOnly->assertOk();
        foreach ($branchOnly->json('data') as $row) {
            $this->assertNotNull($row['branch_id'], 'Branch scope must exclude global mappings.');
        }

        $globalOnly = $this->fetch(['scope' => 'global']);
        $globalOnly->assertOk();
        foreach ($globalOnly->json('data') as $row) {
            $this->assertNull($row['branch_id'], 'Global scope must exclude branch overrides.');
        }

        $this->assertGreaterThan(
            $branchOnly->json('recordsFiltered'),
            $this->fetch()->json('recordsFiltered'),
            'Unfiltered feed should return more rows than the branch-only slice.',
        );
    }

    public function test_datatable_filters_by_mapping_key(): void
    {
        $response = $this->fetch(['key' => 'inventory_asset']);
        $response->assertOk();

        foreach ($response->json('data') as $row) {
            $this->assertSame('inventory_asset', $row['key']);
        }

        $this->assertGreaterThan(0, $response->json('recordsFiltered'));
    }

    public function test_datatable_ignores_an_unknown_mapping_key_filter(): void
    {
        // An unrecognised key must not silently narrow the feed.
        $response = $this->fetch(['key' => 'not_a_real_key']);
        $response->assertOk();

        $this->assertSame($this->fetch()->json('recordsFiltered'), $response->json('recordsFiltered'));
    }

    public function test_datatable_searches_across_key_account_branch_and_description(): void
    {
        foreach (['inventory_asset', 'DT9001', 'Override Account', 'BR-DT', 'grid'] as $needle) {
            $response = $this->fetch(['search' => ['value' => $needle, 'regex' => 'false']]);
            $response->assertOk();

            $this->assertGreaterThan(
                0,
                $response->json('recordsFiltered'),
                "Search for [{$needle}] should match at least one row.",
            );
        }
    }

    public function test_datatable_can_order_by_columns_shared_with_joined_tables(): void
    {
        foreach (['0', '1', '2', '3'] as $columnIndex) {
            $this->fetch(['order' => [['column' => $columnIndex, 'dir' => 'desc']]])
                ->assertOk("Ordering by column {$columnIndex} must not break the query.");
        }
    }

    public function test_datatable_requires_the_mappings_permission(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/accounting/account-mappings/data?'.http_build_query($this->gridQuery()))
            ->assertForbidden();
    }
}
