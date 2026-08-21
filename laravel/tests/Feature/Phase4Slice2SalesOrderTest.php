<?php

namespace Tests\Feature;

use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Application\Sales\SalesOrderService;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ReceivableEntry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase4Slice2SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Customer $customer;

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
        $this->adminUser->givePermissionTo([
            'sales.view',
            'sales.create',
            'sales.edit',
            'sales.submit',
            'sales.approve',
            'sales.cancel',
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $cat = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->customer = Customer::query()->create([
            'code' => 'CUST-001',
            'name' => 'Acme Corporation',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->product = Product::query()->create([
            'code' => 'PRD-SO-1',
            'name' => ['en' => 'Sales Item 1', 'ar' => 'منتج مبيعات 1'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $cat->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_sales_order_migrations_create_expected_tables_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('sales_order'));
        $this->assertTrue(Schema::hasTable('sales_order_line'));

        $this->assertTrue(Schema::hasColumns('sales_order', [
            'id', 'number', 'customer_id', 'order_date', 'expected_delivery_date',
            'currency', 'fx_rate_e6', 'status', 'subtotal_minor', 'total_minor',
            'submitted_by', 'submitted_at', 'confirmed_by', 'confirmed_at',
            'cancelled_by', 'cancelled_at', 'lock_version',
        ]));

        $this->assertTrue(Schema::hasColumns('sales_order_line', [
            'id', 'sales_order_id', 'line_no', 'product_id', 'unit_of_measure_id',
            'description', 'quantity_e6', 'unit_price_minor', 'line_total_minor',
        ]));
    }

    public function test_no_tenant_company_branch_or_accounting_columns_exist_in_sales_order_tables(): void
    {
        $prohibitedColumns = [
            'company_id', 'branch_id', 'tenant_id', 'current_company', 'current_branch',
            'fiscal_year_id', 'financial_period_id', 'journal_entry_id', 'receivable_entry_id',
        ];
        $salesTables = ['sales_order', 'sales_order_line'];

        foreach ($salesTables as $table) {
            foreach ($prohibitedColumns as $col) {
                $this->assertFalse(
                    Schema::hasColumn($table, $col),
                    "Prohibited column [{$col}] was found in table [{$table}]."
                );
            }
        }
    }

    public function test_sales_order_draft_creation_and_total_computation(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'reference' => 'REF-001',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'description' => 'First Line Item',
                    'quantity_e6' => 2500000, // 2.5 units
                    'unit_price_minor' => 1000, // $10.00
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals('draft', $order->status);
        $this->assertEquals('USD', $order->currency);
        $this->assertEquals(2500, $order->subtotal_minor); // 2.5 * 1000 = 2500 cents ($25.00)
        $this->assertEquals(2500, $order->total_minor);
        $this->assertCount(1, $order->lines);
    }

    public function test_submit_requires_at_least_one_line_and_changes_status(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);

        $submittedOrder = $service->submit($order->id, $this->adminUser->id);

        $this->assertEquals('submitted', $submittedOrder->status);
        $this->assertEquals($this->adminUser->id, $submittedOrder->submitted_by);
        $this->assertNotNull($submittedOrder->submitted_at);
    }

    public function test_confirm_allocates_number_sequence_and_is_idempotent(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 1500,
                ],
            ],
        ], $this->adminUser->id);

        $service->submit($order->id, $this->adminUser->id);
        $confirmedOrder1 = $service->confirm($order->id, $this->adminUser->id);

        $this->assertEquals('confirmed', $confirmedOrder1->status);
        $this->assertNotNull($confirmedOrder1->number);
        $this->assertStringStartsWith('SO-2026-', $confirmedOrder1->number);

        // Idempotency replay check
        $confirmedOrder2 = $service->confirm($order->id, $this->adminUser->id);
        $this->assertEquals($confirmedOrder1->number, $confirmedOrder2->number);
        $this->assertEquals('confirmed', $confirmedOrder2->status);
    }

    public function test_cancel_changes_status_for_draft_or_submitted(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);

        $cancelledOrder = $service->cancel($order->id, $this->adminUser->id);
        $this->assertEquals('cancelled', $cancelledOrder->status);
    }

    public function test_confirmed_and_cancelled_orders_are_immutable_against_normal_updates(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);

        $service->confirm($order->id, $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $service->update($order->id, [
            'notes' => 'Attempting to edit confirmed order',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_invalid_customer_product_or_currency_is_rejected(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $inactiveCustomer = Customer::query()->create([
            'code' => 'CUST-INACTIVE',
            'name' => 'Inactive Customer',
            'status' => 'inactive',
        ]);

        $this->expectException(ValidationException::class);
        $service->create([
            'customer_id' => $inactiveCustomer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_audit_entries_are_recorded_through_spatie_activitylog(): void
    {
        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);

        $activityCount = Activity::query()
            ->where('properties->entity_type', 'sales_order')
            ->where('properties->entity_id', $order->id)
            ->count();

        $this->assertGreaterThanOrEqual(1, $activityCount);
    }

    public function test_attachment_registry_supports_sales_order(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);

        $allowedTypes = $authorizer->allowedEntityTypes();
        $this->assertContains('sales_order', $allowedTypes);
    }

    public function test_sales_order_operations_create_zero_accounting_or_subledger_entries(): void
    {
        $journalsBefore = JournalEntry::count();
        $ledgersBefore = LedgerEntry::count();
        $receivablesBefore = ReceivableEntry::count();

        /** @var SalesOrderService $service */
        $service = app(SalesOrderService::class);

        $order = $service->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 500,
                ],
            ],
        ], $this->adminUser->id);

        $service->submit($order->id, $this->adminUser->id);
        $service->confirm($order->id, $this->adminUser->id);

        $this->assertEquals($journalsBefore, JournalEntry::count());
        $this->assertEquals($ledgersBefore, LedgerEntry::count());
        $this->assertEquals($receivablesBefore, ReceivableEntry::count());
    }

    public function test_inertia_sales_orders_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/sales/orders');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sales/SalesOrders'));
    }
}
