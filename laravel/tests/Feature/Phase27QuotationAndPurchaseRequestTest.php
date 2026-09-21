<?php

namespace Tests\Feature;

use App\Application\Purchasing\PurchaseRequestService;
use App\Application\Sales\SalesQuotationService;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\AccountantAcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 27 - Sales Quotations and Purchase Requests. Pre-commitment documents
 * ahead of the Sales Order and Purchase Order (PHASE_25_GAP_CLOSURE_DECISION_PACK.md
 * §6). Neither posts to the ledger itself; converting reuses SalesOrderService
 * / PurchaseOrderService so the resulting order goes through the exact same
 * validated path as one created directly.
 */
class Phase27QuotationAndPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplier;

    private Product $product;

    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountantAcceptanceSeeder::class);
        $this->withoutVite();

        $this->customer = Customer::query()->where('code', 'ACC-CUST-001')->firstOrFail();
        $this->supplier = Supplier::query()->where('code', 'ACC-SUPP-001')->firstOrFail();
        $this->product = Product::query()->where('code', 'ACC-PRD-STOCK-01')->firstOrFail();
        $this->uom = UnitOfMeasure::query()->findOrFail($this->product->unit_of_measure_id);
    }

    public function test_accepted_quotation_converts_into_a_sales_order_with_matching_lines(): void
    {
        $quotationService = app(SalesQuotationService::class);
        $actorId = User::query()->first()?->id;

        $quotation = $quotationService->create([
            'customer_id' => $this->customer->id,
            'quotation_date' => '2026-09-01',
            'valid_until' => '2026-09-30',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 5_000_000,
                'unit_price_minor' => 20_000,
            ]],
        ], $actorId);

        $this->assertSame('draft', $quotation->status);
        $this->assertSame(100_000, $quotation->total_minor);

        $quotation = $quotationService->submit($quotation->id, $actorId);
        $this->assertSame('submitted', $quotation->status);
        $this->assertNotNull($quotation->number);

        $quotation = $quotationService->accept($quotation->id, $actorId);
        $this->assertSame('accepted', $quotation->status);

        $quotation = $quotationService->convertToSalesOrder($quotation->id, [], $actorId);
        $this->assertSame('converted', $quotation->status);
        $this->assertNotNull($quotation->converted_sales_order_id);

        $salesOrder = SalesOrder::query()->findOrFail($quotation->converted_sales_order_id);
        $this->assertSame($this->customer->id, $salesOrder->customer_id);
        $this->assertSame(100_000, $salesOrder->total_minor);
        $this->assertSame(1, $salesOrder->lines()->count());
    }

    public function test_quotation_cannot_be_converted_before_being_accepted(): void
    {
        $quotationService = app(SalesQuotationService::class);
        $quotation = $quotationService->create([
            'customer_id' => $this->customer->id,
            'quotation_date' => '2026-09-01',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1_000_000,
                'unit_price_minor' => 5_000,
            ]],
        ]);

        $this->expectException(ValidationException::class);
        $quotationService->convertToSalesOrder($quotation->id, []);
    }

    public function test_rejected_quotation_cannot_be_converted(): void
    {
        $quotationService = app(SalesQuotationService::class);
        $quotation = $quotationService->create([
            'customer_id' => $this->customer->id,
            'quotation_date' => '2026-09-01',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1_000_000,
                'unit_price_minor' => 5_000,
            ]],
        ]);
        $quotationService->submit($quotation->id);
        $quotation = $quotationService->reject($quotation->id);
        $this->assertSame('rejected', $quotation->status);

        $this->expectException(ValidationException::class);
        $quotationService->convertToSalesOrder($quotation->id, []);
    }

    public function test_approved_purchase_request_converts_into_a_purchase_order_with_chosen_supplier_and_price(): void
    {
        $requestService = app(PurchaseRequestService::class);
        $actorId = User::query()->first()?->id;

        // Supplier is intentionally left blank at request time - the whole
        // point of a request is to ask before a supplier is settled.
        $request = $requestService->create([
            'requested_date' => '2026-09-01',
            'needed_by_date' => '2026-09-15',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 10_000_000,
                'estimated_unit_price_minor' => 0,
            ]],
        ], $actorId);

        $this->assertSame('draft', $request->status);
        $this->assertNull($request->supplier_id);

        $request = $requestService->submit($request->id, $actorId);
        $request = $requestService->approve($request->id, $actorId);
        $this->assertSame('approved', $request->status);

        $line = $request->lines->first();

        $request = $requestService->convertToPurchaseOrder($request->id, [
            'supplier_id' => $this->supplier->id,
            'unit_price_minor_by_line' => [$line->id => 15_000],
        ], $actorId);

        $this->assertSame('converted', $request->status);
        $purchaseOrder = PurchaseOrder::query()->findOrFail($request->converted_purchase_order_id);
        $this->assertSame($this->supplier->id, $purchaseOrder->supplier_id);
        $this->assertSame(150_000, $purchaseOrder->total_minor);
    }

    public function test_purchase_request_conversion_requires_a_supplier(): void
    {
        $requestService = app(PurchaseRequestService::class);
        $request = $requestService->create([
            'requested_date' => '2026-09-01',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1_000_000,
            ]],
        ]);
        $requestService->submit($request->id);
        $request = $requestService->approve($request->id);

        $this->expectException(ValidationException::class);
        $requestService->convertToPurchaseOrder($request->id, []);
    }

    public function test_purchase_request_conversion_requires_a_unit_price_for_every_line(): void
    {
        $requestService = app(PurchaseRequestService::class);
        $request = $requestService->create([
            'requested_date' => '2026-09-01',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1_000_000,
                'estimated_unit_price_minor' => 0,
            ]],
        ]);
        $requestService->submit($request->id);
        $request = $requestService->approve($request->id);

        $this->expectException(ValidationException::class);
        $requestService->convertToPurchaseOrder($request->id, ['supplier_id' => $this->supplier->id]);
    }

    public function test_quotation_routes_require_sales_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('dashboard.view');
        $this->actingAs($viewer)->get('/sales/quotations')->assertForbidden();

        $salesUser = User::factory()->create();
        $salesUser->givePermissionTo(['sales.view', 'sales.create']);

        $this->actingAs($salesUser)->get('/sales/quotations')->assertOk();

        $this->actingAs($salesUser)->post('/sales/quotations', [
            'customer_id' => $this->customer->id,
            'quotation_date' => '2026-09-01',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1_000_000,
                'unit_price_minor' => 5_000,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sales_quotation', ['customer_id' => $this->customer->id, 'status' => 'draft']);

        // Creating quotations does not implicitly grant approval.
        $quotation = SalesQuotation::query()->firstOrFail();
        $this->actingAs($salesUser)->post("/sales/quotations/{$quotation->id}/accept")->assertForbidden();
    }

    public function test_purchase_request_routes_require_purchasing_permissions(): void
    {
        $purchasingUser = User::factory()->create();
        $purchasingUser->givePermissionTo(['purchasing.view', 'purchasing.create']);

        $this->actingAs($purchasingUser)->get('/purchasing/requests')->assertOk();

        $this->actingAs($purchasingUser)->post('/purchasing/requests', [
            'requested_date' => '2026-09-01',
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1_000_000,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('purchase_request', ['status' => 'draft']);

        $request = PurchaseRequest::query()->firstOrFail();
        $this->actingAs($purchasingUser)->post("/purchasing/requests/{$request->id}/approve")->assertForbidden();
    }
}
