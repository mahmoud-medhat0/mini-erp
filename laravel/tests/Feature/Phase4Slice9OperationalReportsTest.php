<?php

namespace Tests\Feature;

use App\Application\Reports\CustomerInvoiceReportService;
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
}
