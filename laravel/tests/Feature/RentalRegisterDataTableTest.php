<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\RentableItem;
use App\Models\RentalContract;
use App\Models\RentalContractLine;
use App\Models\RentalHandover;
use App\Models\RentalHandoverLine;
use App\Models\RentalInvoice;
use App\Models\RentalReturn;
use App\Models\RentalReturnLine;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RentalRegisterDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $needleBranch;

    private Warehouse $needleWarehouse;

    private Customer $needleCustomer;

    /** @var array<string, array{endpoint: string, columns: list<array{0: string, 1: string, 2: bool, 3: bool}>, field: string, value: string, filters: array<string, string>}> */
    private array $registers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, PermissionSeeder::class]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('rentals.view');

        $this->createFixtures();
        $this->registers = $this->registerDefinitions();
    }

    public function test_index_payloads_keep_lookups_but_do_not_embed_register_rows(): void
    {
        $this->withoutVite();

        foreach ([
            ['/rentals/items', 'Rentals/RentableItems', 'items', 'branches'],
            ['/rentals/contracts', 'Rentals/Contracts', 'contracts', 'customers'],
            ['/rentals/handovers', 'Rentals/Handovers', 'handovers', 'contracts'],
            ['/rentals/returns', 'Rentals/Returns', 'returns', 'contracts'],
            ['/rentals/invoices', 'Rentals/Invoices', 'invoices', 'currencies'],
        ] as [$url, $component, $rowProp, $lookupProp]) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where($rowProp, [])
                    ->has($lookupProp));
        }

        $this->actingAs($this->user)
            ->get('/rentals/invoices')
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceSummary.total_count', 2)
                ->where('invoiceSummary.posted_count', 1)
                ->where('invoiceSummary.open_count', 1)
                ->where('invoiceSummary.currency', 'EGP')
                ->where('invoiceSummary.total_minor', 30000));
    }

    public function test_datatable_endpoints_require_rentals_view_permission(): void
    {
        $stranger = User::factory()->create();

        foreach ($this->registers as $register) {
            $this->actingAs($stranger)
                ->getJson($register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'])))
                ->assertForbidden();
        }
    }

    public function test_datatable_endpoints_page_search_filter_and_include_action_relations(): void
    {
        foreach ($this->registers as $name => $register) {
            $paged = $this->actingAs($this->user)->getJson(
                $register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'], ['length' => '1'])),
            );

            $paged->assertOk()
                ->assertJsonPath('draw', 1)
                ->assertJsonPath('recordsTotal', 2)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.'.$register['field'], $register['value']);
            $this->assertActionRelations($name, $paged->json('data.0'));

            $searched = $this->actingAs($this->user)->getJson(
                $register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'], [
                    'search' => ['value' => 'NEEDLE', 'regex' => 'false'],
                ])),
            );

            $searched->assertOk()
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath('data.0.'.$register['field'], $register['value']);

            $filtered = $this->actingAs($this->user)->getJson(
                $register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'], $register['filters'])),
            );

            $filtered->assertOk()
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath('data.0.'.$register['field'], $register['value']);
        }
    }

    private function createFixtures(): void
    {
        $otherBranch = $this->branch('RENT-BR-OTHER', 'Other Branch');
        $this->needleBranch = $this->branch('RENT-BR-NEEDLE', 'Needle Branch');
        $otherWarehouse = $this->warehouse('RENT-WH-OTHER', 'Other Warehouse', $otherBranch);
        $this->needleWarehouse = $this->warehouse('RENT-WH-NEEDLE', 'Needle Warehouse', $this->needleBranch);
        $otherCustomer = $this->customer('RENT-CUST-OTHER', 'Other Customer');
        $this->needleCustomer = $this->customer('RENT-CUST-NEEDLE', 'Needle Customer');

        $otherItem = $this->item('RENT-ITEM-OTHER', $otherBranch, $otherWarehouse, 'available');
        $needleItem = $this->item('RENT-ITEM-NEEDLE', $this->needleBranch, $this->needleWarehouse, 'rented');
        [$otherContract, $otherLine] = $this->contract('RENT-CONTRACT-OTHER', $otherCustomer, $otherBranch, $otherItem, 'draft');
        [$needleContract, $needleLine] = $this->contract('RENT-CONTRACT-NEEDLE', $this->needleCustomer, $this->needleBranch, $needleItem, 'active');

        $this->handover('RENT-HANDOVER-OTHER', $otherContract, $otherLine, $otherItem, 'draft');
        $this->handover('RENT-HANDOVER-NEEDLE', $needleContract, $needleLine, $needleItem, 'confirmed');
        $this->rentalReturn('RENT-RETURN-OTHER', $otherContract, $otherLine, $otherItem, 'draft', 5000);
        $this->rentalReturn('RENT-RETURN-NEEDLE', $needleContract, $needleLine, $needleItem, 'completed', 7500);

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
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

        $this->invoice('RENT-INVOICE-OTHER', $otherContract, $fiscalYear, $period, 'periodic_rent', 'draft', 10000);
        $this->invoice('RENT-INVOICE-NEEDLE', $needleContract, $fiscalYear, $period, 'final_charges', 'posted', 20000);
    }

    private function branch(string $code, string $name): Branch
    {
        return Branch::query()->create(['code' => $code, 'name' => ['en' => $name], 'is_active' => true]);
    }

    private function warehouse(string $code, string $name, Branch $branch): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => ['en' => $name],
            'branch_id' => $branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    private function customer(string $code, string $name): Customer
    {
        return Customer::query()->create(['code' => $code, 'name' => ['en' => $name], 'status' => 'active']);
    }

    private function item(string $code, Branch $branch, Warehouse $warehouse, string $status): RentableItem
    {
        return RentableItem::query()->create([
            'code' => $code,
            'name' => ['en' => $code],
            'item_source' => 'standalone',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => $status,
            'condition_status' => 'good',
            'currency' => 'EGP',
            'serial_number' => $code.'-SERIAL',
            'replacement_value_minor' => 100000,
            'daily_rate_minor' => 1000,
            'monthly_rate_minor' => 20000,
            'deposit_minor' => 5000,
            'is_active' => true,
        ]);
    }

    /** @return array{RentalContract, RentalContractLine} */
    private function contract(string $number, Customer $customer, Branch $branch, RentableItem $item, string $status): array
    {
        $contract = RentalContract::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => $status,
            'contract_date' => '2026-01-01',
            'start_date' => '2026-01-02',
            'expected_end_date' => '2026-02-02',
            'currency' => 'EGP',
            'billing_cycle' => 'monthly',
            'estimated_rent_minor' => 20000,
            'deposit_minor' => 5000,
            'total_estimated_minor' => 25000,
            'reference' => $number.' reference',
        ]);
        $line = RentalContractLine::query()->create([
            'rental_contract_id' => $contract->id,
            'line_no' => 1,
            'rentable_item_id' => $item->id,
            'description' => ['en' => $number.' line'],
            'start_date' => '2026-01-02',
            'end_date' => '2026-02-02',
            'rate_type' => 'monthly',
            'rate_minor' => 20000,
            'estimated_units' => 1,
            'estimated_amount_minor' => 20000,
            'deposit_minor' => 5000,
        ]);

        return [$contract, $line];
    }

    private function handover(string $number, RentalContract $contract, RentalContractLine $line, RentableItem $item, string $status): void
    {
        $handover = RentalHandover::query()->create([
            'number' => $number,
            'rental_contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'branch_id' => $contract->branch_id,
            'status' => $status,
            'handover_date' => '2026-01-03',
        ]);
        RentalHandoverLine::query()->create([
            'rental_handover_id' => $handover->id,
            'rental_contract_line_id' => $line->id,
            'rentable_item_id' => $item->id,
            'condition_out' => 'good',
        ]);
    }

    private function rentalReturn(string $number, RentalContract $contract, RentalContractLine $line, RentableItem $item, string $status, int $damage): void
    {
        $return = RentalReturn::query()->create([
            'number' => $number,
            'rental_contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'branch_id' => $contract->branch_id,
            'status' => $status,
            'return_date' => '2026-02-02',
        ]);
        RentalReturnLine::query()->create([
            'rental_return_id' => $return->id,
            'rental_contract_line_id' => $line->id,
            'rentable_item_id' => $item->id,
            'condition_in' => 'good',
            'outcome' => 'returned',
            'estimated_damage_charge_minor' => $damage,
        ]);
    }

    private function invoice(string $number, RentalContract $contract, FiscalYear $year, FinancialPeriod $period, string $type, string $status, int $total): void
    {
        RentalInvoice::query()->create([
            'number' => $number,
            'rental_contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'branch_id' => $contract->branch_id,
            'fiscal_year_id' => $year->id,
            'financial_period_id' => $period->id,
            'invoice_type' => $type,
            'status' => $status,
            'invoice_date' => '2026-01-10',
            'currency' => 'EGP',
            'subtotal_minor' => $total,
            'tax_amount_minor' => 0,
            'total_minor' => $total,
            'reference' => $number.' reference',
        ]);
    }

    /**
     * @return array<string, array{endpoint: string, columns: list<array{0: string, 1: string, 2: bool, 3: bool}>, field: string, value: string, filters: array<string, string>}>
     */
    private function registerDefinitions(): array
    {
        return [
            'items' => [
                'endpoint' => '/rentals/items/data',
                'columns' => $this->columns([
                    ['code', 'rentable_item.code'], ['name', 'rentable_item.name'], ['item_source', 'rentable_item.item_source'],
                    ['location', 'location'], ['status', 'rentable_item.status'], ['condition_status', 'rentable_item.condition_status'],
                    ['rates', 'rates', false, false], ['actions', 'actions', false, false],
                ]),
                'field' => 'code',
                'value' => 'RENT-ITEM-NEEDLE',
                'filters' => ['status' => 'rented', 'item_source' => 'standalone', 'branch_id' => $this->needleBranch->id, 'warehouse_id' => $this->needleWarehouse->id],
            ],
            'contracts' => [
                'endpoint' => '/rentals/contracts/data',
                'columns' => $this->columns([
                    ['number', 'rental_contract.number'], ['customer_name', 'customer_name'], ['period', 'rental_contract.start_date'],
                    ['status', 'rental_contract.status'], ['items', 'items', false, false], ['totals', 'totals', false, false],
                    ['actions', 'actions', false, false], ['created_at', 'rental_contract.created_at', false],
                ]),
                'field' => 'number',
                'value' => 'RENT-CONTRACT-NEEDLE',
                'filters' => ['status' => 'active', 'customer_id' => $this->needleCustomer->id, 'branch_id' => $this->needleBranch->id],
            ],
            'handovers' => [
                'endpoint' => '/rentals/handovers/data',
                'columns' => $this->columns([
                    ['number', 'rental_handover.number'], ['contract_number', 'contract_number'], ['handover_date', 'rental_handover.handover_date'],
                    ['status', 'rental_handover.status'], ['items', 'items', false, false], ['actions', 'actions', false, false],
                    ['created_at', 'rental_handover.created_at', false],
                ]),
                'field' => 'number',
                'value' => 'RENT-HANDOVER-NEEDLE',
                'filters' => ['status' => 'confirmed'],
            ],
            'returns' => [
                'endpoint' => '/rentals/returns/data',
                'columns' => $this->columns([
                    ['number', 'rental_return.number'], ['contract_number', 'contract_number'], ['return_date', 'rental_return.return_date'],
                    ['status', 'rental_return.status'], ['items', 'items', false, false], ['damage_total_minor', 'damage_total_minor', false],
                    ['actions', 'actions', false, false], ['created_at', 'rental_return.created_at', false],
                ]),
                'field' => 'number',
                'value' => 'RENT-RETURN-NEEDLE',
                'filters' => ['status' => 'completed'],
            ],
            'invoices' => [
                'endpoint' => '/rentals/invoices/data',
                'columns' => $this->columns([
                    ['number', 'rental_invoice.number'], ['contract_number', 'contract_number'], ['customer_name', 'customer_name'],
                    ['invoice_date', 'rental_invoice.invoice_date'], ['invoice_type', 'rental_invoice.invoice_type'],
                    ['total_minor', 'rental_invoice.total_minor'], ['status', 'rental_invoice.status'], ['actions', 'actions', false, false],
                    ['created_at', 'rental_invoice.created_at', false],
                ]),
                'field' => 'number',
                'value' => 'RENT-INVOICE-NEEDLE',
                'filters' => ['status' => 'posted', 'invoice_type' => 'final_charges'],
            ],
        ];
    }

    /** @param list<array{0: string, 1: string, 2?: bool, 3?: bool}> $columns */
    private function columns(array $columns): array
    {
        return array_map(
            fn (array $column) => [$column[0], $column[1], $column[2] ?? true, $column[3] ?? true],
            $columns,
        );
    }

    /**
     * @param  list<array{0: string, 1: string, 2: bool, 3: bool}>  $definitions
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function gridQuery(array $definitions, array $overrides = []): array
    {
        $columns = [];
        foreach ($definitions as $index => [$data, $name, $searchable, $orderable]) {
            $columns[$index] = [
                'data' => $data,
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

    /** @param array<string, mixed> $row */
    private function assertActionRelations(string $register, array $row): void
    {
        $this->assertArrayHasKey('actions', $row);

        if ($register === 'items') {
            $this->assertSame('RENT-BR-NEEDLE', $row['branch']['code']);
            $this->assertSame('RENT-WH-NEEDLE', $row['warehouse']['code']);

            return;
        }

        $this->assertArrayHasKey('lines', $row);
        $this->assertSame('RENT-CUST-NEEDLE', $row['customer']['code']);

        if ($register !== 'contracts') {
            $this->assertSame('RENT-CONTRACT-NEEDLE', $row['contract']['number']);
        }

        if (in_array($register, ['contracts', 'handovers', 'returns'], true)) {
            $this->assertSame('RENT-ITEM-NEEDLE', $row['lines'][0]['rentable_item']['code']);
        }

        if ($register === 'returns') {
            $this->assertSame(7500, $row['damage_total_minor']);
        }
    }
}
