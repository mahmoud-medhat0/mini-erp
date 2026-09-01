<?php

namespace Tests\Feature;

use App\Application\Reports\CustomerInvoiceReportService;
use App\Application\Reports\OperationalReportDataTableService;
use App\Application\Reports\PurchaseOrderReportService;
use App\Application\Reports\SalesOrderReportService;
use App\Application\Reports\StockMovementReportService;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\ReceivableEntry;
use App\Models\SalesOrder;
use App\Models\StockMovementLedger;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4Slice9OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private Customer $customer;

    private Supplier $supplier;

    private Product $product;

    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductCategorySeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo(['reports.view', 'view_financials']);

        $this->unauthorizedUser = User::factory()->create();

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $cat = ProductCategory::query()->where('code', 'RAW')->firstOrFail();

        $this->customer = Customer::query()->create([
            'code' => 'CUST-REP-1',
            'name' => 'Report Customer',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-REP-1',
            'name' => 'Report Supplier',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->product = Product::query()->create([
            'code' => 'PRD-REP-1',
            'name' => ['en' => 'Report Item'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $cat->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_unauthorized_users_are_denied_access_to_all_report_routes(): void
    {
        $routes = [
            '/reports/sales-orders',
            '/reports/purchase-orders',
            '/reports/delivery-notes',
            '/reports/goods-receipts',
            '/reports/customer-invoices',
            '/reports/supplier-bills',
            '/reports/stock-movements',
            '/reports/sales-orders/data?draw=1&start=0&length=10',
            '/reports/purchase-orders/data?draw=1&start=0&length=10',
            '/reports/delivery-notes/data?draw=1&start=0&length=10',
            '/reports/goods-receipts/data?draw=1&start=0&length=10',
            '/reports/customer-invoices/data?draw=1&start=0&length=10',
            '/reports/supplier-bills/data?draw=1&start=0&length=10',
            '/reports/stock-movements/data?draw=1&start=0&length=10',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->unauthorizedUser)->get($route);
            $response->assertStatus(403);
        }
    }

    public function test_all_seven_operational_report_pages_render_successfully(): void
    {
        $routes = [
            '/reports/sales-orders' => 'Reports/SalesOrdersReport',
            '/reports/purchase-orders' => 'Reports/PurchaseOrdersReport',
            '/reports/delivery-notes' => 'Reports/DeliveryNotesReport',
            '/reports/goods-receipts' => 'Reports/GoodsReceiptsReport',
            '/reports/customer-invoices' => 'Reports/CustomerInvoicesReport',
            '/reports/supplier-bills' => 'Reports/SupplierBillsReport',
            '/reports/stock-movements' => 'Reports/StockMovementsReport',
        ];

        foreach ($routes as $route => $component) {
            $response = $this->actingAs($this->adminUser)->get($route);
            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_all_seven_operational_report_datatable_endpoints_return_server_side_payloads(): void
    {
        $routes = [
            '/reports/sales-orders/data',
            '/reports/purchase-orders/data',
            '/reports/delivery-notes/data',
            '/reports/goods-receipts/data',
            '/reports/customer-invoices/data',
            '/reports/supplier-bills/data',
            '/reports/stock-movements/data',
        ];

        foreach ($routes as $route) {
            $this->actingAs($this->adminUser)
                ->getJson($route.'?draw=1&start=0&length=10')
                ->assertOk()
                ->assertJsonStructure([
                    'draw',
                    'recordsTotal',
                    'recordsFiltered',
                    'data',
                ]);
        }
    }

    public function test_operational_datatable_validates_filters_and_page_size(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson('/reports/stock-movements/data?product_id=invalid&date_from=31-08-2026&length=-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'date_from', 'length']);
    }

    public function test_operational_datatable_rejects_cross_report_and_malformed_order_columns(): void
    {
        $crossReportColumn = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => [[
                'data' => 'movement_date',
                'name' => 'movement_date',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ]],
            'order' => [['column' => 0, 'dir' => 'asc']],
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/reports/sales-orders/data?'.$crossReportColumn)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns.0.data', 'columns.0.name']);

        $outOfRangeOrder = http_build_query([
            'draw' => 2,
            'start' => 0,
            'length' => 10,
            'columns' => [[
                'data' => 'order_number',
                'name' => 'number',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ]],
            'order' => [['column' => 11, 'dir' => 'desc']],
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/reports/sales-orders/data?'.$outOfRangeOrder)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order.0.column']);

        $tamperedCapability = http_build_query([
            'draw' => 3,
            'start' => 0,
            'length' => 10,
            'columns' => [[
                'data' => 'customer_name',
                'name' => 'customer_name',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ]],
            'order' => [['column' => 0, 'dir' => 'asc']],
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/reports/sales-orders/data?'.$tamperedCapability)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns.0.orderable', 'order.0.column']);
    }

    public function test_sales_order_datatable_searches_and_paginates_on_the_server(): void
    {
        foreach (range(1, 12) as $index) {
            SalesOrder::query()->create([
                'number' => $index === 12 ? 'SO-SEARCH-TARGET' : sprintf('SO-PAGE-%03d', $index),
                'customer_id' => $this->customer->id,
                'order_date' => '2026-08-22',
                'status' => 'confirmed',
                'currency' => 'EGP',
                'subtotal_minor' => 1000,
                'total_minor' => 1000,
                'created_by' => $this->adminUser->id,
                'updated_by' => $this->adminUser->id,
                'lock_version' => 1,
            ]);
        }

        $dataTableQuery = http_build_query([
            'draw' => 4,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'columns' => [
                [
                    'data' => 'order_number',
                    'name' => 'number',
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => '', 'regex' => 'false'],
                ],
                [
                    'data' => 'order_date',
                    'name' => 'order_date',
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => '', 'regex' => 'false'],
                ],
            ],
            'order' => [['column' => 1, 'dir' => 'desc']],
        ]);

        $page = $this->actingAs($this->adminUser)
            ->getJson('/reports/sales-orders/data?'.$dataTableQuery);

        $page->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonCount(10, 'data');

        $secondPageQuery = http_build_query(array_replace_recursive(
            [
                'draw' => 5,
                'start' => 10,
                'length' => 10,
            ],
            [
                'search' => ['value' => '', 'regex' => 'false'],
                'columns' => [
                    [
                        'data' => 'order_number',
                        'name' => 'number',
                        'searchable' => 'true',
                        'orderable' => 'true',
                        'search' => ['value' => '', 'regex' => 'false'],
                    ],
                    [
                        'data' => 'order_date',
                        'name' => 'order_date',
                        'searchable' => 'true',
                        'orderable' => 'true',
                        'search' => ['value' => '', 'regex' => 'false'],
                    ],
                ],
                'order' => [['column' => 1, 'dir' => 'desc']],
            ],
        ));

        $secondPage = $this->actingAs($this->adminUser)
            ->getJson('/reports/sales-orders/data?'.$secondPageQuery)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $actualIds = collect($page->json('data'))
            ->concat($secondPage->json('data'))
            ->pluck('id')
            ->all();
        $expectedIds = SalesOrder::query()
            ->orderByDesc('order_date')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame($expectedIds, $actualIds, 'Server-side pagination must use a stable ID tie-breaker.');
        $this->assertCount(12, array_unique($actualIds));

        $search = $this->actingAs($this->adminUser)
            ->getJson('/reports/sales-orders/data?draw=6&start=0&length=10&search%5Bvalue%5D=so-search-target');

        $search->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.order_number', 'SO-SEARCH-TARGET');

        foreach (['confirmed', 'egp', '2026-08-22', 'report customer'] as $index => $visibleSearch) {
            $query = http_build_query([
                'draw' => 10 + $index,
                'start' => 0,
                'length' => 25,
                'search' => ['value' => $visibleSearch, 'regex' => 'false'],
            ]);

            $this->actingAs($this->adminUser)
                ->getJson('/reports/sales-orders/data?'.$query)
                ->assertOk()
                ->assertJsonPath('recordsFiltered', 12);
        }
    }

    public function test_sales_order_report_service_calculates_totals_and_filters(): void
    {
        $so = SalesOrder::query()->create([
            'number' => 'SO-2026-00001',
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'status' => 'confirmed',
            'currency' => 'EGP',
            'subtotal_minor' => 10000,
            'tax_minor' => 0,
            'total_minor' => 10000,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $so->lines()->create([
            'sales_order_id' => $so->id,
            'line_no' => 1,
            'product_id' => $this->product->id,
            'unit_of_measure_id' => $this->uom->id,
            'quantity_e6' => 2000000,
            'unit_price_minor' => 5000,
            'line_total_minor' => 10000,
        ]);

        /** @var SalesOrderReportService $service */
        $service = app(SalesOrderReportService::class);
        $result = $service->generate(customerId: $this->customer->id);

        $this->assertEquals(1, $result['summary']['total_orders_count']);
        $this->assertEquals(2000000, $result['summary']['total_quantity_e6']);
        $this->assertEquals(10000, $result['summary']['total_amount_minor']);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals('SO-2026-00001', $result['rows'][0]['order_number']);

        $summary = app(OperationalReportDataTableService::class)->salesOrderSummary(
            $this->operationalFilters(['customer_id' => $this->customer->id]),
        );
        $this->assertSame(1, $summary['total_orders_count']);
        $this->assertSame(2000000, $summary['total_quantity_e6']);
        $this->assertSame(10000, $summary['total_amount_minor']);
    }

    public function test_purchase_order_report_service_calculates_totals_and_filters(): void
    {
        $po = PurchaseOrder::query()->create([
            'number' => 'PO-2026-00001',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'status' => 'confirmed',
            'currency' => 'EGP',
            'subtotal_minor' => 15000,
            'tax_minor' => 0,
            'total_minor' => 15000,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $po->lines()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => $this->product->id,
            'unit_of_measure_id' => $this->uom->id,
            'quantity_e6' => 3000000,
            'unit_price_minor' => 5000,
            'line_total_minor' => 15000,
        ]);

        /** @var PurchaseOrderReportService $service */
        $service = app(PurchaseOrderReportService::class);
        $result = $service->generate(supplierId: $this->supplier->id);

        $this->assertEquals(1, $result['summary']['total_orders_count']);
        $this->assertEquals(3000000, $result['summary']['total_quantity_e6']);
        $this->assertEquals(15000, $result['summary']['total_amount_minor']);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals('PO-2026-00001', $result['rows'][0]['order_number']);

        $summary = app(OperationalReportDataTableService::class)->purchaseOrderSummary(
            $this->operationalFilters(['supplier_id' => $this->supplier->id]),
        );
        $this->assertSame(1, $summary['total_orders_count']);
        $this->assertSame(3000000, $summary['total_quantity_e6']);
        $this->assertSame(15000, $summary['total_amount_minor']);
    }

    public function test_customer_invoice_report_includes_journal_and_receivable_links(): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $inv = CustomerInvoice::query()->create([
            'number' => 'INV-2026-00001',
            'customer_id' => $this->customer->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $period->id,
            'invoice_date' => '2026-08-22',
            'due_date' => '2026-09-22',
            'status' => 'posted',
            'currency' => 'EGP',
            'subtotal_minor' => 20000,
            'tax_minor' => 0,
            'total_minor' => 20000,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
            'lock_version' => 1,
        ]);

        $journal = JournalEntry::query()->create([
            'number' => 'JV-2026-00001',
            'financial_period_id' => $period->id,
            'entry_date' => '2026-08-22',
            'source_type' => 'customer_invoice',
            'source_id' => $inv->id,
            'description' => 'Customer invoice report fixture',
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'status' => 'posted',
            'created_by' => $this->adminUser->id,
            'posted_by' => $this->adminUser->id,
            'posted_at' => now(),
            'lock_version' => 1,
        ]);

        $inv->update(['journal_entry_id' => $journal->id]);

        $receivable = ReceivableEntry::query()->create([
            'customer_id' => $this->customer->id,
            'source_type' => 'customer_invoice',
            'source_id' => $inv->id,
            'journal_entry_id' => $journal->id,
            'financial_period_id' => $period->id,
            'entry_date' => '2026-08-22',
            'due_date' => '2026-09-22',
            'description' => 'Customer invoice report fixture',
            'currency' => 'EGP',
            'debit_minor' => 20000,
            'credit_minor' => 0,
            'debit_txn_minor' => 20000,
            'credit_txn_minor' => 0,
            'fx_rate_e6' => 1000000,
            'created_by' => $this->adminUser->id,
        ]);

        /** @var CustomerInvoiceReportService $service */
        $service = app(CustomerInvoiceReportService::class);
        $result = $service->generate(customerId: $this->customer->id);

        $this->assertEquals(1, $result['summary']['total_invoices_count']);
        $this->assertEquals(20000, $result['summary']['total_amount_minor']);
        $this->assertEquals('JV-2026-00001', $result['rows'][0]['journal_entry_number']);
        $this->assertEquals($receivable->id, $result['rows'][0]['receivable_entry_id']);

        $summary = app(OperationalReportDataTableService::class)->customerInvoiceSummary(
            $this->operationalFilters(['customer_id' => $this->customer->id]),
        );
        $this->assertSame(1, $summary['total_invoices_count']);
        $this->assertSame(20000, $summary['total_amount_minor']);
    }

    public function test_stock_movement_report_service_reads_immutable_ledger(): void
    {
        $sm = StockMovementLedger::query()->create([
            'movement_date' => '2026-08-22',
            'source_type' => 'goods_receipt',
            'source_id' => '01a028a4-c203-71fb-83f5-906b76dcdd41',
            'movement_type' => 'receipt',
            'product_id' => $this->product->id,
            'unit_of_measure_id' => $this->uom->id,
            'currency' => 'EGP',
            'quantity_delta_e6' => 5000000,
            'value_delta_minor' => 25000,
            'unit_cost_e6' => 5000,
            'balance_quantity_e6' => 5000000,
            'balance_valuation_amount_minor' => 25000,
        ]);

        /** @var StockMovementReportService $service */
        $service = app(StockMovementReportService::class);
        $result = $service->generate(productId: $this->product->id);

        $this->assertEquals(1, $result['summary']['total_movements_count']);
        $this->assertEquals(5000000, $result['summary']['total_quantity_delta_e6']);
        $this->assertEquals(25000, $result['summary']['total_value_delta_minor']);
        $this->assertEquals($sm->id, $result['rows'][0]['id']);

        $summary = app(OperationalReportDataTableService::class)->stockMovementSummary(
            $this->operationalFilters(['product_id' => $this->product->id]),
        );
        $this->assertSame(1, $summary['total_movements_count']);
        $this->assertSame(5000000, $summary['total_quantity_delta_e6']);
        $this->assertSame(25000, $summary['total_value_delta_minor']);

        $searchQuery = http_build_query([
            'draw' => 21,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'prd-rep-1', 'regex' => 'false'],
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/reports/stock-movements/data?'.$searchQuery)
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $sm->id);
    }

    public function test_operational_reports_perform_zero_database_mutations(): void
    {
        $soCountBefore = SalesOrder::count();
        $poCountBefore = PurchaseOrder::count();
        $journalCountBefore = JournalEntry::count();
        $smCountBefore = StockMovementLedger::count();

        $this->actingAs($this->adminUser)->get('/reports/sales-orders');
        $this->actingAs($this->adminUser)->get('/reports/purchase-orders');
        $this->actingAs($this->adminUser)->get('/reports/delivery-notes');
        $this->actingAs($this->adminUser)->get('/reports/goods-receipts');
        $this->actingAs($this->adminUser)->get('/reports/customer-invoices');
        $this->actingAs($this->adminUser)->get('/reports/supplier-bills');
        $this->actingAs($this->adminUser)->get('/reports/stock-movements');

        $this->assertEquals($soCountBefore, SalesOrder::count());
        $this->assertEquals($poCountBefore, PurchaseOrder::count());
        $this->assertEquals($journalCountBefore, JournalEntry::count());
        $this->assertEquals($smCountBefore, StockMovementLedger::count());
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, string|null>
     */
    private function operationalFilters(array $overrides = []): array
    {
        return array_replace([
            'date_from' => null,
            'date_to' => null,
            'status' => null,
            'customer_id' => null,
            'supplier_id' => null,
            'product_id' => null,
            'warehouse_id' => null,
            'currency' => null,
            'movement_type' => null,
            'search' => null,
        ], $overrides);
    }
}
