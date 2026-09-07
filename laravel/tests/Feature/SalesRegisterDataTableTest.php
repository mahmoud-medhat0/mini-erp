<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceRevision;
use App\Models\DeliveryNote;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesRegisterDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $needleCustomer;

    private Warehouse $needleWarehouse;

    /** @var array<string, array{endpoint: string, columns: list<array{0: string, 1: string, 2: bool, 3: bool}>, order: int, field: string, number: string, filters: array<string, string>}> */
    private array $registers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, PermissionSeeder::class]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'sales.view',
            'sales.returns',
            'sales.credit_notes',
            'sales.invoice_revisions',
        ]);

        $this->createFixtures();
        $this->registers = $this->registerDefinitions();
    }

    public function test_index_payloads_do_not_embed_paginated_register_rows(): void
    {
        foreach ([
            ['/sales/orders', 'Sales/SalesOrders', 'salesOrders'],
            ['/sales/delivery-notes', 'Sales/DeliveryNotes', 'deliveryNotes'],
            ['/sales/invoices', 'Sales/CustomerInvoices', 'customerInvoices'],
            ['/sales/credit-notes', 'Sales/CustomerCreditNotes', 'customerCreditNotes'],
            ['/sales/returns', 'Sales/SalesReturns', 'salesReturns'],
            ['/sales/invoice-revisions', 'Sales/InvoiceRevisions', 'customerInvoiceRevisions'],
        ] as [$url, $component, $prop]) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where($prop, []));
        }
    }

    public function test_datatable_endpoints_require_the_matching_register_permission(): void
    {
        $stranger = User::factory()->create();

        foreach ($this->registers as $register) {
            $this->actingAs($stranger)
                ->getJson($register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'])))
                ->assertForbidden();
        }
    }

    public function test_datatable_endpoints_page_order_and_include_action_relations(): void
    {
        foreach ($this->registers as $name => $register) {
            $response = $this->actingAs($this->user)->getJson(
                $register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'], [
                    'length' => '1',
                    'order' => [['column' => (string) $register['order'], 'dir' => 'desc']],
                ])),
            );

            $response->assertOk()
                ->assertJsonPath('draw', 1)
                ->assertJsonPath('recordsTotal', 2)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.'.$register['field'], $register['number']);

            $this->assertActionRelations($name, $response->json('data.0'));
        }
    }

    public function test_datatable_endpoints_search_and_apply_register_filters(): void
    {
        foreach ($this->registers as $register) {
            $searched = $this->actingAs($this->user)->getJson(
                $register['endpoint'].'?'.http_build_query($this->gridQuery($register['columns'], [
                    'search' => ['value' => 'NEEDLE', 'regex' => 'false'],
                ])),
            );

            $searched->assertOk();
            $this->assertSame(
                1,
                $searched->json('recordsFiltered'),
                $register['endpoint'].' did not apply global search: '.$searched->getContent(),
            );
            $searched->assertJsonPath('data.0.'.$register['field'], $register['number']);

            if ($register['filters'] === []) {
                continue;
            }

            $filtered = $this->actingAs($this->user)->getJson(
                $register['endpoint'].'?'.http_build_query($this->gridQuery(
                    $register['columns'],
                    $register['filters'],
                )),
            );

            $filtered->assertOk()
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath('data.0.'.$register['field'], $register['number']);
        }
    }

    private function createFixtures(): void
    {
        $otherCustomer = Customer::query()->create([
            'code' => 'CUST-OTHER',
            'name' => ['en' => 'Other Customer', 'ar' => 'عميل آخر'],
            'status' => 'active',
        ]);
        $this->needleCustomer = Customer::query()->create([
            'code' => 'CUST-NEEDLE',
            'name' => ['en' => 'Needle Customer', 'ar' => 'عميل مستهدف'],
            'status' => 'active',
        ]);

        $otherWarehouse = Warehouse::query()->create([
            'code' => 'WH-OTHER',
            'name' => ['en' => 'Other Warehouse', 'ar' => 'مخزن آخر'],
            'is_active' => true,
        ]);
        $this->needleWarehouse = Warehouse::query()->create([
            'code' => 'WH-NEEDLE',
            'name' => ['en' => 'Needle Warehouse', 'ar' => 'مخزن مستهدف'],
            'is_active' => true,
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 2,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'open',
        ]);

        $otherOrder = $this->salesOrder('SO-OTHER', $otherCustomer, '2026-01-01', 'draft');
        $needleOrder = $this->salesOrder('SO-NEEDLE', $this->needleCustomer, '2026-02-02', 'confirmed');

        $otherDelivery = $this->deliveryNote('DN-OTHER', $otherOrder, $otherWarehouse, '2026-01-02', 'draft');
        $needleDelivery = $this->deliveryNote('DN-NEEDLE', $needleOrder, $this->needleWarehouse, '2026-02-03', 'confirmed');

        $otherInvoice = $this->customerInvoice('INV-OTHER', $otherCustomer, $otherOrder, $otherDelivery, $fiscalYear, $period, '2026-01-03', 'draft');
        $needleInvoice = $this->customerInvoice('INV-NEEDLE', $this->needleCustomer, $needleOrder, $needleDelivery, $fiscalYear, $period, '2026-02-04', 'posted');

        $otherReturn = $this->salesReturn('RET-OTHER', $otherCustomer, $otherDelivery, $otherInvoice, $otherWarehouse, $fiscalYear, $period, '2026-01-04', 'draft');
        $needleReturn = $this->salesReturn('RET-NEEDLE', $this->needleCustomer, $needleDelivery, $needleInvoice, $this->needleWarehouse, $fiscalYear, $period, '2026-02-05', 'posted');

        $otherCredit = $this->creditNote('CN-OTHER', $otherCustomer, $otherInvoice, $otherReturn, $fiscalYear, $period, '2026-01-05', 'draft');
        $needleCredit = $this->creditNote('CN-NEEDLE', $this->needleCustomer, $needleInvoice, $needleReturn, $fiscalYear, $period, '2026-02-06', 'posted');

        $this->revision('INV-OTHER-R1', $otherInvoice, $otherCredit, $otherReturn, '2026-01-06');
        $this->revision('INV-NEEDLE-R1', $needleInvoice, $needleCredit, $needleReturn, '2026-02-07');
    }

    private function salesOrder(string $number, Customer $customer, string $date, string $status): SalesOrder
    {
        return SalesOrder::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'order_date' => $date,
            'currency' => 'EGP',
            'status' => $status,
            'reference' => $number.' reference',
            'subtotal_minor' => 10000,
            'total_minor' => 10000,
        ]);
    }

    private function deliveryNote(string $number, SalesOrder $order, Warehouse $warehouse, string $date, string $status): DeliveryNote
    {
        return DeliveryNote::query()->create([
            'number' => $number,
            'sales_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'delivery_date' => $date,
            'status' => $status,
            'reference' => $number.' reference',
        ]);
    }

    private function customerInvoice(string $number, Customer $customer, SalesOrder $order, DeliveryNote $delivery, FiscalYear $year, FinancialPeriod $period, string $date, string $status): CustomerInvoice
    {
        return CustomerInvoice::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'sales_order_id' => $order->id,
            'delivery_note_id' => $delivery->id,
            'fiscal_year_id' => $year->id,
            'financial_period_id' => $period->id,
            'invoice_date' => $date,
            'currency' => 'EGP',
            'subtotal_minor' => 10000,
            'total_minor' => 10000,
            'status' => $status,
            'reference' => $number.' reference',
        ]);
    }

    private function salesReturn(string $number, Customer $customer, DeliveryNote $delivery, CustomerInvoice $invoice, Warehouse $warehouse, FiscalYear $year, FinancialPeriod $period, string $date, string $status): SalesReturn
    {
        return SalesReturn::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'delivery_note_id' => $delivery->id,
            'customer_invoice_id' => $invoice->id,
            'warehouse_id' => $warehouse->id,
            'fiscal_year_id' => $year->id,
            'financial_period_id' => $period->id,
            'return_date' => $date,
            'status' => $status,
            'currency' => 'EGP',
            'reason' => $number.' reason',
        ]);
    }

    private function creditNote(string $number, Customer $customer, CustomerInvoice $invoice, SalesReturn $return, FiscalYear $year, FinancialPeriod $period, string $date, string $status): CustomerCreditNote
    {
        return CustomerCreditNote::query()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'customer_invoice_id' => $invoice->id,
            'sales_return_id' => $return->id,
            'fiscal_year_id' => $year->id,
            'financial_period_id' => $period->id,
            'credit_date' => $date,
            'status' => $status,
            'currency' => 'EGP',
            'subtotal_minor' => 2000,
            'total_minor' => 2000,
            'reason' => $number.' reason',
        ]);
    }

    private function revision(string $display, CustomerInvoice $invoice, CustomerCreditNote $credit, SalesReturn $return, string $date): CustomerInvoiceRevision
    {
        return CustomerInvoiceRevision::query()->create([
            'customer_invoice_id' => $invoice->id,
            'customer_credit_note_id' => $credit->id,
            'sales_return_id' => $return->id,
            'revision_no' => 1,
            'display_string' => $display,
            'revision_date' => $date,
            'currency' => 'EGP',
            'original_total_minor' => 10000,
            'credited_total_minor' => 2000,
            'net_total_minor' => 8000,
        ]);
    }

    /**
     * @return array<string, array{endpoint: string, columns: list<array{0: string, 1: string, 2: bool, 3: bool}>, order: int, field: string, number: string, filters: array<string, string>}>
     */
    private function registerDefinitions(): array
    {
        return [
            'orders' => [
                'endpoint' => '/sales/orders/data',
                'columns' => $this->columns([
                    ['number', 'sales_order.number'], ['customer_name', 'customer_name'], ['order_date', 'sales_order.order_date'],
                    ['total_minor', 'sales_order.total_minor'], ['status', 'sales_order.status'], ['actions', 'actions', false, false],
                ]),
                'order' => 2,
                'field' => 'number',
                'number' => 'SO-NEEDLE',
                'filters' => ['status' => 'confirmed', 'customer_id' => $this->needleCustomer->id],
            ],
            'delivery-notes' => [
                'endpoint' => '/sales/delivery-notes/data',
                'columns' => $this->columns([
                    ['number', 'delivery_note.number'], ['sales_order_number', 'sales_order_number'], ['customer_name', 'customer_name'],
                    ['warehouse_name', 'warehouse_name'], ['delivery_date', 'delivery_note.delivery_date'], ['status', 'delivery_note.status'],
                    ['actions', 'actions', false, false],
                ]),
                'order' => 4,
                'field' => 'number',
                'number' => 'DN-NEEDLE',
                'filters' => ['status' => 'confirmed', 'warehouse_id' => $this->needleWarehouse->id],
            ],
            'invoices' => [
                'endpoint' => '/sales/invoices/data',
                'columns' => $this->columns([
                    ['number', 'customer_invoice.number'], ['customer_name', 'customer_name'], ['invoice_date', 'customer_invoice.invoice_date'],
                    ['total_minor', 'customer_invoice.total_minor'], ['status', 'customer_invoice.status'], ['actions', 'actions', false, false],
                ]),
                'order' => 2,
                'field' => 'number',
                'number' => 'INV-NEEDLE',
                'filters' => ['status' => 'posted'],
            ],
            'credit-notes' => [
                'endpoint' => '/sales/credit-notes/data',
                'columns' => $this->columns([
                    ['number', 'customer_credit_note.number'], ['customer_name', 'customer_name'], ['invoice_number', 'invoice_number'],
                    ['sales_return_number', 'sales_return_number'], ['credit_date', 'customer_credit_note.credit_date'],
                    ['total_minor', 'customer_credit_note.total_minor'], ['status', 'customer_credit_note.status'], ['actions', 'actions', false, false],
                ]),
                'order' => 4,
                'field' => 'number',
                'number' => 'CN-NEEDLE',
                'filters' => ['status' => 'posted', 'customer_id' => $this->needleCustomer->id],
            ],
            'returns' => [
                'endpoint' => '/sales/returns/data',
                'columns' => $this->columns([
                    ['number', 'sales_return.number'], ['customer_name', 'customer_name'], ['delivery_note_number', 'delivery_note_number'],
                    ['invoice_number', 'invoice_number'], ['warehouse_name', 'warehouse_name'], ['return_date', 'sales_return.return_date'],
                    ['status', 'sales_return.status'], ['actions', 'actions', false, false],
                ]),
                'order' => 5,
                'field' => 'number',
                'number' => 'RET-NEEDLE',
                'filters' => ['status' => 'posted', 'customer_id' => $this->needleCustomer->id, 'warehouse_id' => $this->needleWarehouse->id],
            ],
            'revisions' => [
                'endpoint' => '/sales/invoice-revisions/data',
                'columns' => $this->columns([
                    ['display_string', 'customer_invoice_revision.display_string'], ['invoice_number', 'invoice_number'], ['customer_name', 'customer_name'],
                    ['revision_date', 'customer_invoice_revision.revision_date'], ['original_total_minor', 'customer_invoice_revision.original_total_minor'],
                    ['credited_total_minor', 'customer_invoice_revision.credited_total_minor'], ['net_total_minor', 'customer_invoice_revision.net_total_minor'],
                    ['actions', 'actions', false, false],
                ]),
                'order' => 3,
                'field' => 'display_string',
                'number' => 'INV-NEEDLE-R1',
                'filters' => [],
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
        $this->assertArrayHasKey('lines', $row);

        switch ($register) {
            case 'orders':
            case 'invoices':
                $this->assertSame('CUST-NEEDLE', $row['customer']['code']);
                break;
            case 'delivery-notes':
                $this->assertSame('SO-NEEDLE', $row['sales_order']['number']);
                break;
            case 'credit-notes':
                $this->assertSame('INV-NEEDLE', $row['customer_invoice']['number']);
                break;
            case 'returns':
                $this->assertSame('DN-NEEDLE', $row['delivery_note']['number']);
                break;
            case 'revisions':
                $this->assertSame('CUST-NEEDLE', $row['customer_invoice']['customer']['code']);
                break;
        }
    }
}
