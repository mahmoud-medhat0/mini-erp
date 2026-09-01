<?php

namespace Tests\Feature;

use App\Application\Rentals\RentableItemService;
use App\Application\Rentals\RentalContractService;
use App\Application\Rentals\RentalFulfillmentService;
use App\Application\Rentals\RentalInvoiceService;
use App\Application\Reports\RentalOperationsDataTableService;
use App\Application\Reports\RentalOperationsReportService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\RentableItem;
use App\Models\RentalContract;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalOperationsDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $unauthorizedUser;

    private Branch $branch;

    private Warehouse $warehouse;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'reports.view',
            'reports.export',
            'view_financials',
            'rentals.view',
            'rentals.create',
            'rentals.edit',
            'rentals.submit',
            'rentals.approve',
            'rentals.post',
            'rentals.invoice',
            'rentals.deliver',
            'rentals.return',
            'rentals.inspect',
            'rentals.cancel',
        ]);
        $this->unauthorizedUser = User::factory()->create();
        $this->actingAs($this->user);

        $this->branch = Branch::query()->firstOrCreate(
            ['code' => 'RENT-DT-BR'],
            ['name' => ['en' => 'Rental DT Branch', 'ar' => 'فرع'], 'is_active' => true]
        );

        $this->warehouse = Warehouse::query()->create([
            'code' => 'RENT-DT-WH',
            'name' => ['en' => 'Rental DT Warehouse', 'ar' => 'مخزن'],
            'branch_id' => $this->branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    /**
     * The acceptance criterion for this slice: the SQL-backed DataTable service must
     * reproduce the legacy PHP service field-for-field on a fixture that exercises
     * posted invoices, open invoices, cancelled invoices, completed returns, and
     * pending damage.
     */
    public function test_datatable_rows_and_summary_match_the_legacy_report_service_exactly(): void
    {
        $billedContract = $this->activeContract();
        $this->postRentInvoice($billedContract);

        $damagedContract = $this->activeContract();
        $this->completedReturn($damagedContract, 25000);

        $cancelledInvoiceContract = $this->activeContract();
        $this->cancelledRentInvoice($cancelledInvoiceContract);

        $this->activeContract();

        $filters = ['as_of_date' => '2026-02-15'];

        $legacy = app(RentalOperationsReportService::class)->generate($filters);
        $legacyRows = collect($legacy['rows'])->keyBy('contract_id');

        $response = $this->getJson($this->dataTableUrl($filters, '', 0, 100));
        $response->assertOk();

        $this->assertSame(count($legacy['rows']), $response->json('recordsTotal'));
        $this->assertCount(count($legacy['rows']), $response->json('data'));

        $comparedFields = [
            'contract_number',
            'customer_code',
            'branch_code',
            'status',
            'due_state',
            'currency',
            'line_count',
            'confirmed_handover_count',
            'returned_line_count',
            'open_item_count',
            'invoice_count',
            'posted_invoice_count',
            'open_invoice_count',
            'estimated_rent_minor',
            'deposit_minor',
            'rent_billed_minor',
            'deposit_billed_minor',
            'charge_billed_minor',
            'tax_billed_minor',
            'total_billed_minor',
            'open_invoice_total_minor',
            'unbilled_line_count',
            'pending_damage_minor',
            'has_unposted_invoice',
            'latest_journal_number',
            'active_invoice_line_count',
        ];

        foreach ($response->json('data') as $row) {
            $expected = $legacyRows->get($row['contract_id']);
            $this->assertNotNull($expected, "Contract {$row['contract_id']} missing from legacy report.");

            foreach ($comparedFields as $field) {
                $this->assertSame(
                    $expected[$field],
                    $row[$field],
                    "Field {$field} diverged for contract {$expected['contract_number']}."
                );
            }
        }

        $summary = app(RentalOperationsDataTableService::class)->summary($filters);

        $this->assertSame($legacy['summary'], $summary['summary']);
        $this->assertSame($legacy['readiness'], $summary['readiness']);
        $this->assertSame($legacy['currency_codes'], $summary['currency_codes']);
        $this->assertSame($legacy['single_currency'], $summary['single_currency']);
        $this->assertSame($legacy['display_currency'], $summary['display_currency']);
        $this->assertSame($legacy['as_of_date'], $summary['as_of_date']);
        $this->assertSame($legacy['ending_soon_date'], $summary['ending_soon_date']);
    }

    public function test_cancelled_invoices_are_excluded_from_billed_aggregates(): void
    {
        $contract = $this->activeContract();
        $this->cancelledRentInvoice($contract);

        $row = $this->firstRowFor($contract);

        $this->assertSame(0, $row['posted_invoice_count']);
        $this->assertSame(0, $row['invoice_count']);
        $this->assertSame(0, $row['rent_billed_minor']);
        $this->assertSame(0, $row['total_billed_minor']);
        $this->assertSame(0, $row['open_invoice_count']);
        $this->assertSame(0, $row['active_invoice_line_count']);
        $this->assertSame(1, $row['unbilled_line_count']);
    }

    public function test_posted_invoice_populates_billed_amounts_and_clears_unbilled_lines(): void
    {
        $contract = $this->activeContract();
        $this->postRentInvoice($contract);

        $row = $this->firstRowFor($contract);

        $this->assertSame(1, $row['posted_invoice_count']);
        $this->assertSame(50000, $row['rent_billed_minor']);
        $this->assertSame(7000, $row['tax_billed_minor']);
        $this->assertSame(57000, $row['total_billed_minor']);
        $this->assertSame(0, $row['unbilled_line_count']);
        $this->assertSame(0, $row['open_invoice_count']);
        $this->assertFalse($row['has_unposted_invoice']);
    }

    public function test_pending_damage_is_reported_per_return_line_and_never_negative(): void
    {
        $contract = $this->activeContract();
        $this->completedReturn($contract, 25000);

        $row = $this->firstRowFor($contract);

        $this->assertSame(25000, $row['pending_damage_minor']);
        $this->assertSame(1, $row['returned_line_count']);
        $this->assertSame(0, $row['open_item_count']);
    }

    public function test_due_state_reflects_overdue_ending_soon_and_on_track_contracts(): void
    {
        $contract = $this->activeContract();

        $overdue = $this->rowFor($contract, ['as_of_date' => '2026-03-01']);
        $this->assertSame('overdue', $overdue['due_state']);

        $endingSoon = $this->rowFor($contract, ['as_of_date' => '2026-02-01']);
        $this->assertSame('ending_soon', $endingSoon['due_state']);

        $onTrack = $this->rowFor($contract, ['as_of_date' => '2026-01-10']);
        $this->assertSame('on_track', $onTrack['due_state']);
    }

    public function test_filters_narrow_the_result_set(): void
    {
        $contract = $this->activeContract();
        $this->activeContract();

        $filters = ['as_of_date' => '2026-02-15'];

        $all = $this->getJson($this->dataTableUrl($filters, '', 0, 100));
        $this->assertSame(2, $all->json('recordsTotal'));

        $byCustomer = $this->getJson($this->dataTableUrl(
            [...$filters, 'customer_id' => $contract->customer_id],
            '',
            0,
            100,
        ));
        $this->assertSame(1, $byCustomer->json('recordsTotal'));
        $this->assertSame($contract->id, $byCustomer->json('data.0.contract_id'));

        $byStatus = $this->getJson($this->dataTableUrl([...$filters, 'status' => 'draft'], '', 0, 100));
        $this->assertSame(0, $byStatus->json('recordsTotal'));

        $byCurrency = $this->getJson($this->dataTableUrl([...$filters, 'currency' => 'EGP'], '', 0, 100));
        $this->assertSame(2, $byCurrency->json('recordsTotal'));

        $outOfRange = $this->getJson($this->dataTableUrl(
            [...$filters, 'date_from' => '2027-01-01'],
            '',
            0,
            100,
        ));
        $this->assertSame(0, $outOfRange->json('recordsTotal'));
    }

    public function test_search_is_case_insensitive_across_contract_and_customer_columns(): void
    {
        $contract = $this->activeContract();
        $this->activeContract();

        $response = $this->getJson($this->dataTableUrl(
            ['as_of_date' => '2026-02-15'],
            mb_strtolower((string) $contract->number),
            0,
            100,
        ));

        $response->assertOk();
        $this->assertSame(2, $response->json('recordsTotal'));
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertSame($contract->number, $response->json('data.0.contract_number'));
    }

    public function test_server_side_pagination_limits_returned_rows(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->activeContract();
        }

        $response = $this->getJson($this->dataTableUrl(['as_of_date' => '2026-02-15'], '', 0, 10));

        $response->assertOk();
        $this->assertSame(3, $response->json('recordsTotal'));

        $paged = $this->getJson($this->dataTableUrl(['as_of_date' => '2026-02-15'], '', 2, 10));
        $this->assertSame(3, $paged->json('recordsTotal'));
        $this->assertCount(1, $paged->json('data'));
    }

    public function test_report_page_no_longer_ships_an_unbounded_row_array(): void
    {
        $this->activeContract();

        $this->get('/reports/rentals?as_of_date=2026-02-15')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/RentalOperationsReport')
                ->has('reportData.summary')
                ->has('reportData.readiness')
                ->missing('reportData.rows'));
    }

    public function test_endpoint_enforces_permissions_and_rejects_malformed_payload(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/rentals/data?as_of_date=2026-02-15')
            ->assertForbidden();

        $payload = $this->dataTablePayload();
        $payload['as_of_date'] = 'not-a-date';
        $payload['branch_id'] = 'not-a-uuid';
        $payload['customer_id'] = 'not-a-uuid';
        $payload['status'] = 'sideways';
        $payload['date_from'] = '2026-03-01';
        $payload['date_to'] = '2026-01-01';
        $payload['length'] = 999;
        $payload['search']['value'] = str_repeat('x', 151);
        $payload['columns'][0]['name'] = 'contract_number; DROP TABLE rental_contract';
        $payload['order'][0]['column'] = 99;

        $this->actingAs($this->user)
            ->getJson('/reports/rentals/data?'.http_build_query($payload))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'as_of_date',
                'branch_id',
                'customer_id',
                'status',
                'date_to',
                'length',
                'search.value',
                'columns.0.name',
                'order.0.column',
            ]);
    }

    public function test_csv_export_remains_complete_and_is_not_limited_to_a_datatable_page(): void
    {
        $contracts = [];
        foreach (range(1, 3) as $ignored) {
            $contracts[] = $this->activeContract();
        }

        $response = $this->get('/reports/rentals/export?as_of_date=2026-02-15');
        $response->assertOk();
        $csv = $response->streamedContent();

        foreach ($contracts as $contract) {
            $this->assertStringContainsString((string) $contract->number, $csv);
        }
    }

    /** @param array<string, string> $filters */
    private function rowFor(RentalContract $contract, array $filters): array
    {
        $response = $this->getJson($this->dataTableUrl($filters, (string) $contract->number, 0, 100));
        if ($response->getStatusCode() !== 200) {
            $this->fail('DataTable endpoint returned '.$response->getStatusCode().': '.$response->getContent());
        }
        $response->assertOk();

        $rows = $response->json('data');
        $this->assertNotEmpty(
            $rows,
            'Expected contract '.$contract->number.' in response. Raw: '.$response->getContent()
        );

        return $rows[0];
    }

    private function firstRowFor(RentalContract $contract): array
    {
        return $this->rowFor($contract, ['as_of_date' => '2026-02-15']);
    }

    private function activeContract(): RentalContract
    {
        $service = app(RentalContractService::class);
        $contract = $this->createRentalContract();
        $approved = $service->approve($service->submit($contract->id, $this->user->id)->id, $this->user->id);

        return $service->activate($approved->id, $this->user->id);
    }

    private function postRentInvoice(RentalContract $contract)
    {
        $invoiceService = app(RentalInvoiceService::class);
        $invoice = $this->draftRentInvoice($contract);

        return $invoiceService->post(
            $invoiceService->approve($invoiceService->submit($invoice->id, $this->user->id)->id, $this->user->id)->id,
            $this->user->id
        );
    }

    private function cancelledRentInvoice(RentalContract $contract): void
    {
        $invoiceService = app(RentalInvoiceService::class);
        $invoice = $this->draftRentInvoice($contract);
        $invoiceService->cancel($invoice->id, $this->user->id);
    }

    private function draftRentInvoice(RentalContract $contract)
    {
        $vat14 = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();

        return app(RentalInvoiceService::class)->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'due_date' => '2026-01-31',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $contract->fresh('lines')->lines->first()->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
                'tax_code_id' => $vat14->id,
            ]],
        ], $this->user->id);
    }

    private function completedReturn(RentalContract $contract, int $damageMinor)
    {
        $fulfillment = app(RentalFulfillmentService::class);
        $line = $contract->fresh('lines')->lines->first();
        $return = $fulfillment->createReturn([
            'rental_contract_id' => $contract->id,
            'return_date' => '2026-02-12',
            'lines' => [[
                'rental_contract_line_id' => $line->id,
                'condition_in' => 'damaged',
                'outcome' => 'damaged',
                'estimated_damage_charge_minor' => $damageMinor,
            ]],
        ], $this->user->id);

        return $fulfillment->completeReturn($fulfillment->submitReturn($return->id, $this->user->id)->id, $this->user->id);
    }

    private function createRentalContract(): RentalContract
    {
        $item = $this->createRentableItem();
        $customer = $this->createCustomer();

        return app(RentalContractService::class)->create([
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'contract_date' => '2026-01-05',
            'start_date' => '2026-01-10',
            'expected_end_date' => '2026-02-10',
            'currency' => 'EGP',
            'billing_cycle' => 'monthly',
            'reference' => 'RENT-DT-REF',
            'notes' => 'Rental datatable fixture.',
            'lines' => [[
                'rentable_item_id' => $item->id,
                'description' => ['en' => 'Rental datatable line', 'ar' => 'بند'],
                'start_date' => '2026-01-10',
                'end_date' => '2026-02-10',
                'rate_type' => 'monthly',
                'rate_minor' => 50000,
                'estimated_units' => 1,
                'deposit_minor' => 10000,
            ]],
        ], $this->user->id);
    }

    private function createRentableItem(): RentableItem
    {
        $suffix = str_pad((string) $this->sequence++, 3, '0', STR_PAD_LEFT);

        return app(RentableItemService::class)->create([
            'code' => "RENT-DT-{$suffix}",
            'name' => ['en' => "Rental DT Item {$suffix}", 'ar' => "عنصر {$suffix}"],
            'description' => ['en' => 'Standalone rentable item', 'ar' => 'عنصر إيجار مستقل'],
            'item_source' => 'standalone',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'available',
            'condition_status' => 'good',
            'currency' => 'EGP',
            'serial_number' => "RENT-DT-SN-{$suffix}",
            'replacement_value_minor' => 500000,
            'daily_rate_minor' => 25000,
            'monthly_rate_minor' => 50000,
            'deposit_minor' => 10000,
            'is_active' => true,
        ], $this->user->id);
    }

    private function createCustomer(): Customer
    {
        $suffix = str_pad((string) $this->sequence++, 3, '0', STR_PAD_LEFT);

        return Customer::query()->create([
            'code' => "CUST-RENT-DT-{$suffix}",
            'name' => ['en' => "Rental DT Customer {$suffix}", 'ar' => "عميل {$suffix}"],
            'status' => 'active',
            'lock_version' => 1,
        ]);
    }

    /** @param array<string, string> $filters */
    private function dataTableUrl(
        array $filters = [],
        string $search = '',
        int $start = 0,
        int $length = 25,
    ): string {
        return '/reports/rentals/data?'.http_build_query([
            ...$this->dataTablePayload($search, $start, $length),
            ...$filters,
        ]);
    }

    /** @return array<string, mixed> */
    private function dataTablePayload(string $search = '', int $start = 0, int $length = 25): array
    {
        $columns = [
            'contract_number',
            'customer_name',
            'branch_name',
            'status',
            'due_state',
            'start_date',
            'expected_end_date',
            'currency',
            'line_count',
            'open_item_count',
            'unbilled_line_count',
            'open_invoice_count',
            'total_billed_minor',
            'open_invoice_total_minor',
            'pending_damage_minor',
        ];

        return [
            'as_of_date' => '2026-02-15',
            'draw' => 5,
            'start' => $start,
            'length' => $length,
            'search' => ['value' => $search, 'regex' => 'false'],
            'columns' => array_map(static fn (string $column): array => [
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => 6, 'dir' => 'asc']],
        ];
    }
}
