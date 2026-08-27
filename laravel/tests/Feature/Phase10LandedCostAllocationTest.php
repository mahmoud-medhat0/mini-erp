<?php

namespace Tests\Feature;

use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Purchasing\GoodsReceiptService;
use App\Application\Purchasing\LandedCostAllocationService;
use App\Application\Purchasing\PurchaseOrderService;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\LandedCostAllocation;
use App\Models\PayableEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10LandedCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Supplier $freightSupplier;

    private Product $product;

    private UnitOfMeasure $uom;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            RbacSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
            AccountingCoreSeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
            WarehouseSeeder::class,
        ]);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'purchasing.view',
            'purchasing.create',
            'purchasing.edit',
            'purchasing.submit',
            'purchasing.approve',
            'purchasing.post',
            'purchasing.landed_costs',
            'view_financials',
        ]);
        $this->actingAs($this->user);

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $category = ProductCategory::query()->where('code', 'RAW')->firstOrFail();
        $this->warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-LC-001',
            'name' => 'Inventory Supplier',
            'status' => 'active',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->freightSupplier = Supplier::query()->create([
            'code' => 'FRT-LC-001',
            'name' => 'Freight Vendor',
            'status' => 'active',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->product = Product::query()->create([
            'code' => 'LC-STOCK-001',
            'name' => ['en' => 'Landed Cost Stock Item', 'ar' => 'صنف تكلفة وصول'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $category->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }

    public function test_landed_cost_schema_has_no_company_branch_or_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasTable('landed_cost_allocation'));
        $this->assertTrue(Schema::hasTable('landed_cost_allocation_line'));

        foreach (['company_id', 'branch_id', 'tenant_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('landed_cost_allocation', $column));
            $this->assertFalse(Schema::hasColumn('landed_cost_allocation_line', $column));
        }
    }

    public function test_landed_cost_posts_to_inventory_ap_gl_and_stock_valuation(): void
    {
        $goodsReceipt = $this->confirmedGoodsReceipt();

        $allocation = $this->createApprovedAllocation($goodsReceipt, 1000, 140);
        $posted = app(LandedCostAllocationService::class)->post($allocation->id, $this->user->id);

        $this->assertSame('posted', $posted->status);
        $this->assertStringStartsWith('LC-2026-', (string) $posted->number);

        $balance = $this->stockBalance();
        $this->assertSame(10000000, $balance->quantity_e6);
        $this->assertSame(11000, $balance->valuation_amount_minor);
        $this->assertSame(1100, $balance->avg_unit_cost_e6);

        $movement = StockMovementLedger::query()
            ->where('source_type', 'landed_cost_allocation')
            ->where('source_id', $posted->id)
            ->where('movement_type', 'landed_cost')
            ->firstOrFail();

        $this->assertSame(0, $movement->quantity_delta_e6);
        $this->assertSame(1000, $movement->value_delta_minor);

        $journal = JournalEntry::query()->with('lines')->where('id', $posted->journal_entry_id)->firstOrFail();
        $this->assertSame('posted', $journal->status);
        $this->assertSame(1140, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(1140, (int) $journal->lines->sum('credit_minor'));

        $payable = PayableEntry::query()->where('id', $posted->payable_entry_id)->firstOrFail();
        $this->assertSame('landed_cost_allocation', $payable->source_type);
        $this->assertSame(1140, $payable->credit_minor);
    }

    public function test_landed_cost_splits_between_remaining_stock_and_cogs_when_stock_was_issued(): void
    {
        $goodsReceipt = $this->confirmedGoodsReceipt();

        app(MovingWeightedAverageInventoryService::class)->recordIssue(
            sourceType: 'test_sale_issue',
            sourceId: (string) Str::uuid(),
            sourceLineId: (string) Str::uuid(),
            movementDate: '2026-01-12',
            productId: $this->product->id,
            unitOfMeasureId: $this->uom->id,
            currency: 'EGP',
            quantityE6: 6000000,
            fiscalYearId: (string) FinancialPeriod::query()->firstOrFail()->fiscal_year_id,
            financialPeriodId: (string) FinancialPeriod::query()->firstOrFail()->id,
            actorId: $this->user->id,
            warehouseId: $this->warehouse->id,
        );

        $allocation = $this->createApprovedAllocation($goodsReceipt, 1000);
        $posted = app(LandedCostAllocationService::class)->post($allocation->id, $this->user->id);

        $line = $posted->lines()->firstOrFail();
        $this->assertSame(400, $line->capitalized_amount_minor);
        $this->assertSame(600, $line->expensed_amount_minor);

        $balance = $this->stockBalance();
        $this->assertSame(4000000, $balance->quantity_e6);
        $this->assertSame(4400, $balance->valuation_amount_minor);
        $this->assertSame(1100, $balance->avg_unit_cost_e6);

        $journal = JournalEntry::query()->with('lines')->where('id', $posted->journal_entry_id)->firstOrFail();
        $debits = $journal->lines->where('debit_minor', '>', 0)->pluck('debit_minor')->sort()->values()->all();

        $this->assertSame([400, 600], $debits);
        $this->assertSame(1000, (int) $journal->lines->sum('credit_minor'));
    }

    public function test_manual_allocation_must_equal_header_cost_amount(): void
    {
        $goodsReceipt = $this->confirmedGoodsReceipt();
        $line = $goodsReceipt->lines->firstOrFail();

        $this->expectException(ValidationException::class);

        app(LandedCostAllocationService::class)->create([
            'goods_receipt_id' => $goodsReceipt->id,
            'supplier_id' => $this->freightSupplier->id,
            'allocation_date' => '2026-01-15',
            'currency' => 'EGP',
            'allocation_method' => 'manual',
            'cost_amount_minor' => 1000,
            'tax_amount_minor' => 0,
            'lines' => [
                ['goods_receipt_line_id' => $line->id, 'allocated_cost_minor' => 999],
            ],
        ], $this->user->id);
    }

    public function test_landed_cost_page_renders_with_confirmed_goods_receipt_options(): void
    {
        $this->confirmedGoodsReceipt();

        $this->get('/purchasing/landed-costs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Purchasing/LandedCosts')
                ->has('confirmedGoodsReceipts', 1)
                ->has('activeSuppliers', 2));
    }

    private function confirmedGoodsReceipt(): GoodsReceipt
    {
        /** @var PurchaseOrderService $purchaseOrderService */
        $purchaseOrderService = app(PurchaseOrderService::class);
        $purchaseOrder = $purchaseOrderService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'EGP',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 10000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->user->id);
        $purchaseOrderService->submit($purchaseOrder->id, $this->user->id);
        $purchaseOrderService->confirm($purchaseOrder->id, $this->user->id);

        /** @var GoodsReceiptService $goodsReceiptService */
        $goodsReceiptService = app(GoodsReceiptService::class);
        $goodsReceipt = $goodsReceiptService->create([
            'purchase_order_id' => $purchaseOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-01-11',
            'lines' => [
                [
                    'purchase_order_line_id' => $purchaseOrder->lines()->firstOrFail()->id,
                    'quantity_e6' => 10000000,
                ],
            ],
        ], $this->user->id);

        return $goodsReceiptService->confirm($goodsReceipt->id, $this->user->id);
    }

    private function createApprovedAllocation(GoodsReceipt $goodsReceipt, int $costMinor, int $taxMinor = 0): LandedCostAllocation
    {
        /** @var LandedCostAllocationService $service */
        $service = app(LandedCostAllocationService::class);

        $allocation = $service->create([
            'goods_receipt_id' => $goodsReceipt->id,
            'supplier_id' => $this->freightSupplier->id,
            'allocation_date' => '2026-01-15',
            'due_date' => '2026-01-30',
            'currency' => 'EGP',
            'allocation_method' => 'by_value',
            'cost_amount_minor' => $costMinor,
            'tax_amount_minor' => $taxMinor,
            'reference' => 'FRT-001',
            'description' => 'Inbound freight charge',
        ], $this->user->id);

        $service->submit($allocation->id, $this->user->id);

        return $service->approve($allocation->id, $this->user->id);
    }

    private function stockBalance(): StockBalance
    {
        return StockBalance::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->where('currency', 'EGP')
            ->firstOrFail();
    }
}
