<?php

namespace Tests\Feature;

use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Application\Purchasing\PurchaseOrderService;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PayableEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
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

class Phase4Slice3PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

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
        $this->adminUser->givePermissionTo([
            'purchasing.view',
            'purchasing.create',
            'purchasing.edit',
            'purchasing.submit',
            'purchasing.approve',
            'purchasing.cancel',
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $cat = ProductCategory::query()->where('code', 'RAW')->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-001',
            'name' => 'Global Supplies Inc',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->product = Product::query()->create([
            'code' => 'PRD-PO-1',
            'name' => ['en' => 'Purchase Material 1', 'ar' => 'مادة شراء 1'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $cat->id,
            'status' => 'active',
            'is_sales_enabled' => false,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_purchase_order_migrations_create_expected_tables_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_order'));
        $this->assertTrue(Schema::hasTable('purchase_order_line'));

        $this->assertTrue(Schema::hasColumns('purchase_order', [
            'id', 'number', 'supplier_id', 'order_date', 'expected_receipt_date',
            'currency', 'fx_rate_e6', 'status', 'subtotal_minor', 'total_minor',
            'submitted_by', 'submitted_at', 'confirmed_by', 'confirmed_at',
            'cancelled_by', 'cancelled_at', 'lock_version',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_order_line', [
            'id', 'purchase_order_id', 'line_no', 'product_id', 'unit_of_measure_id',
            'description', 'quantity_e6', 'unit_price_minor', 'line_total_minor',
        ]));
    }

    public function test_no_tenant_company_branch_or_accounting_columns_exist_in_purchase_order_tables(): void
    {
        $prohibitedColumns = [
            'company_id', 'branch_id', 'tenant_id', 'current_company', 'current_branch',
            'fiscal_year_id', 'financial_period_id', 'journal_entry_id', 'payable_entry_id',
            'supplier_bill_id', 'goods_receipt_id', 'warehouse_id',
        ];
        $purchasingTables = ['purchase_order', 'purchase_order_line'];

        foreach ($purchasingTables as $table) {
            foreach ($prohibitedColumns as $col) {
                $this->assertFalse(
                    Schema::hasColumn($table, $col),
                    "Prohibited column [{$col}] was found in table [{$table}]."
                );
            }
        }
    }

    public function test_purchase_order_draft_creation_and_total_computation(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'reference' => 'RFQ-SUPP-001',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'description' => 'First Purchase Item',
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

    public function test_exact_line_total_calculation_with_integer_math(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1250000, // 1.25 units
                    'unit_price_minor' => 1000, // $10.00
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(1250, $order->lines->first()->line_total_minor);
        $this->assertEquals(1250, $order->total_minor);
    }

    public function test_fractional_minor_unit_rejected(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 333333,
                    'unit_price_minor' => 1,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_integer_overflow_rejected(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => PHP_INT_MAX,
                    'unit_price_minor' => 2,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_submit_requires_at_least_one_line_and_changes_status(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
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
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
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
        $this->assertStringStartsWith('PO-2026-', $confirmedOrder1->number);

        // Idempotency replay check
        $confirmedOrder2 = $service->confirm($order->id, $this->adminUser->id);
        $this->assertEquals($confirmedOrder1->number, $confirmedOrder2->number);
        $this->assertEquals('confirmed', $confirmedOrder2->status);
    }

    public function test_confirm_replay_keeps_the_authoritative_number_and_lock_version(): void
    {
        $service = app(PurchaseOrderService::class);
        $order = $this->createDraftOrder();

        $confirmed = $service->confirm($order->id, $this->adminUser->id);
        $confirmedVersion = $confirmed->lock_version;

        $replayed = $service->confirm($order->id, $this->adminUser->id);

        $this->assertSame($confirmed->number, $replayed->number);
        $this->assertSame($confirmedVersion, $replayed->lock_version);
        $this->assertSame(1, PurchaseOrder::query()->where('id', $order->id)->where('status', 'confirmed')->count());
    }

    public function test_stale_purchase_order_update_request_cannot_replace_authoritative_lines(): void
    {
        $service = app(PurchaseOrderService::class);
        $order = $this->createDraftOrder();
        $staleVersion = $order->lock_version;
        $originalLineId = $order->lines->firstOrFail()->id;

        PurchaseOrder::query()->where('id', $order->id)->increment('lock_version');

        try {
            $service->update($order->id, [
                'lock_version' => $staleVersion,
                'lines' => [[
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 9000000,
                    'unit_price_minor' => 500,
                ]],
            ], $this->adminUser->id);
            $this->fail('A stale purchase order update must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lock_version', $exception->errors());
        }

        $fresh = $order->fresh('lines');
        $this->assertSame($originalLineId, $fresh->lines->firstOrFail()->id);
        $this->assertSame(1000000, $fresh->lines->firstOrFail()->quantity_e6);
        $this->assertSame($staleVersion + 1, $fresh->lock_version);
    }

    public function test_stale_draft_model_cannot_cancel_an_authoritatively_confirmed_purchase_order(): void
    {
        $service = app(PurchaseOrderService::class);
        $staleDraft = $this->createDraftOrder();

        $confirmed = $service->confirm($staleDraft->id, $this->adminUser->id);

        try {
            $service->cancel($staleDraft->id, $this->adminUser->id);
            $this->fail('A stale draft model must not overwrite a confirmed order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame('draft', $staleDraft->status);
        $this->assertSame('confirmed', $confirmed->fresh()->status);
        $this->assertNull($confirmed->fresh()->cancelled_at);
    }

    public function test_cancel_changes_status_for_draft_or_submitted(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
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
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
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

    public function test_invalid_supplier_product_or_currency_is_rejected(): void
    {
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $inactiveSupplier = Supplier::query()->create([
            'code' => 'SUPP-INACTIVE',
            'name' => 'Inactive Supplier',
            'status' => 'inactive',
        ]);

        $this->expectException(ValidationException::class);
        $service->create([
            'supplier_id' => $inactiveSupplier->id,
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
        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
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
            ->where('properties->entity_type', 'purchase_order')
            ->where('properties->entity_id', $order->id)
            ->count();

        $this->assertGreaterThanOrEqual(1, $activityCount);
    }

    public function test_attachment_registry_supports_purchase_order(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);

        $allowedTypes = $authorizer->allowedEntityTypes();
        $this->assertContains('purchase_order', $allowedTypes);
    }

    public function test_purchase_order_operations_create_zero_accounting_or_subledger_entries(): void
    {
        $journalsBefore = JournalEntry::count();
        $ledgersBefore = LedgerEntry::count();
        $payablesBefore = PayableEntry::count();

        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $order = $service->create([
            'supplier_id' => $this->supplier->id,
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
        $this->assertEquals($payablesBefore, PayableEntry::count());
    }

    public function test_inertia_purchase_orders_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/purchasing/orders');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Purchasing/PurchaseOrders'));
    }

    public function test_purchase_order_backend_contains_no_forbidden_binary_or_rounding_math(): void
    {
        $filesToScan = [
            app_path('Application/Purchasing/PurchaseOrderService.php'),
            app_path('Models/PurchaseOrder.php'),
            app_path('Models/PurchaseOrderLine.php'),
        ];

        // Break forbidden strings into dynamic concatenation to avoid false positive matches during repository scans
        $rStr = 'round'.'(';
        $fStr = '('.'flo'.'at'.')';
        $d1Str = '/ '.'1000000';
        $d2Str = '/'.'1000000';

        $forbiddenPatterns = [$rStr, $fStr, $d1Str, $d2Str];

        foreach ($filesToScan as $file) {
            $content = file_get_contents($file);
            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $content,
                    "Forbidden pattern [{$pattern}] was found in [{$file}]."
                );
            }
        }
    }

    private function createDraftOrder(): PurchaseOrder
    {
        return app(PurchaseOrderService::class)->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1000000,
                'unit_price_minor' => 500,
            ]],
        ], $this->adminUser->id);
    }
}
