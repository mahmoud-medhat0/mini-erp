<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Inventory\StockAdjustmentService;
use App\Application\Inventory\StockCountService;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10StockCountAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private UnitOfMeasure $uom;

    private Warehouse $warehouse;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

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
            'inventory.view',
            'inventory.count',
            'inventory.adjust',
            'inventory.approve',
            'inventory.post',
            'reports.view',
        ]);
        $this->actingAs($this->user);

        Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            ['name' => ['en' => 'Egyptian Pound', 'ar' => 'جنيه مصري'], 'symbol' => 'E£', 'is_active' => true],
        );

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $category = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->product = Product::query()->create([
            'code' => 'PH10-COUNT-001',
            'name' => ['en' => 'Phase 10 Count Item', 'ar' => 'صنف جرد مرحلة 10'],
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

        $this->warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
    }

    public function test_schema_supports_stock_counts_and_adjustments_without_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasTable('stock_count'));
        $this->assertTrue(Schema::hasTable('stock_count_line'));
        $this->assertTrue(Schema::hasTable('stock_adjustment'));
        $this->assertTrue(Schema::hasTable('stock_adjustment_line'));

        foreach (['company_id', 'branch_id', 'tenant_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('stock_count', $column));
            $this->assertFalse(Schema::hasColumn('stock_count_line', $column));
            $this->assertFalse(Schema::hasColumn('stock_adjustment', $column));
            $this->assertFalse(Schema::hasColumn('stock_adjustment_line', $column));
        }
    }

    public function test_manual_positive_stock_adjustment_posts_inventory_and_gain(): void
    {
        $adjustment = app(StockAdjustmentService::class)->create([
            'adjustment_date' => '2026-01-08',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'reason' => 'Found stock',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_delta_e6' => 3000000,
                    'unit_cost_minor' => 1200,
                ],
            ],
        ], $this->user->id);

        $adjustment = app(StockAdjustmentService::class)->approve($adjustment->id, $this->user->id);
        $adjustment = app(StockAdjustmentService::class)->post($adjustment->id, $this->user->id);

        $this->assertSame('posted', $adjustment->status);
        $this->assertStringStartsWith('ADJ-2026-', (string) $adjustment->number);

        $balance = $this->stockBalance();
        $this->assertSame(3000000, $balance->quantity_e6);
        $this->assertSame(3600, $balance->valuation_amount_minor);

        $movement = StockMovementLedger::query()
            ->where('source_type', 'stock_adjustment')
            ->where('source_id', $adjustment->id)
            ->firstOrFail();

        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertSame(3000000, $movement->quantity_delta_e6);
        $this->assertSame(3600, $movement->value_delta_minor);
        $this->assertNotNull($movement->journal_entry_id);
    }

    public function test_manual_negative_stock_adjustment_uses_current_mwa_cost(): void
    {
        $this->seedWarehouseStock(10000000, 1000);

        $adjustment = app(StockAdjustmentService::class)->create([
            'adjustment_date' => '2026-01-09',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'reason' => 'Damaged stock correction',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_delta_e6' => -2000000,
                ],
            ],
        ], $this->user->id);

        app(StockAdjustmentService::class)->approve($adjustment->id, $this->user->id);
        app(StockAdjustmentService::class)->post($adjustment->id, $this->user->id);

        $balance = $this->stockBalance();
        $this->assertSame(8000000, $balance->quantity_e6);
        $this->assertSame(8000, $balance->valuation_amount_minor);

        $movement = StockMovementLedger::query()
            ->where('source_type', 'stock_adjustment')
            ->where('source_id', $adjustment->id)
            ->firstOrFail();

        $this->assertSame(-2000000, $movement->quantity_delta_e6);
        $this->assertSame(-2000, $movement->value_delta_minor);
        $this->assertTrue(JournalEntry::query()->where('id', $movement->journal_entry_id)->where('status', 'posted')->exists());
    }

    public function test_stock_count_posts_variance_through_stock_adjustment(): void
    {
        $this->seedWarehouseStock(10000000, 1000);

        $count = app(StockCountService::class)->create([
            'count_date' => '2026-01-10',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'notes' => 'Physical count',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'counted_quantity_e6' => 7000000,
                ],
            ],
        ], $this->user->id);

        app(StockCountService::class)->submit($count->id, $this->user->id);
        app(StockCountService::class)->approve($count->id, $this->user->id);
        $count = app(StockCountService::class)->post($count->id, $this->user->id);

        $this->assertSame('posted', $count->status);
        $this->assertNotNull($count->stock_adjustment_id);
        $this->assertTrue(StockAdjustment::query()->where('id', $count->stock_adjustment_id)->where('status', 'posted')->exists());

        $balance = $this->stockBalance();
        $this->assertSame(7000000, $balance->quantity_e6);
        $this->assertSame(7000, $balance->valuation_amount_minor);

        $movement = StockMovementLedger::query()
            ->where('source_type', 'stock_adjustment')
            ->where('quantity_delta_e6', -3000000)
            ->firstOrFail();

        $this->assertSame(-3000, $movement->value_delta_minor);
    }

    public function test_unposted_stock_count_and_adjustment_block_period_close_readiness(): void
    {
        $adjustment = app(StockAdjustmentService::class)->create([
            'adjustment_date' => '2026-01-13',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'reason' => 'Pending close review',
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity_delta_e6' => 1000000,
                'unit_cost_minor' => 1000,
            ]],
        ], $this->user->id);

        $count = app(StockCountService::class)->create([
            'count_date' => '2026-01-14',
            'warehouse_id' => $this->warehouse->id,
            'currency' => 'EGP',
            'notes' => 'Pending physical count',
            'lines' => [[
                'product_id' => $this->product->id,
                'counted_quantity_e6' => 0,
            ]],
        ], $this->user->id);

        $readiness = app(PeriodService::class)->checkCloseReadiness($this->period);
        $blockers = collect($readiness['blockers']);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue($blockers->contains(
            fn (array $blocker): bool => $blocker['entity_type'] === 'stock_adjustment'
                && $blocker['id'] === $adjustment->id
                && $blocker['reason_code'] === 'unposted_stock_adjustment'
        ));
        $this->assertTrue($blockers->contains(
            fn (array $blocker): bool => $blocker['entity_type'] === 'stock_count'
                && $blocker['id'] === $count->id
                && $blocker['reason_code'] === 'unposted_stock_count'
        ));
    }

    public function test_stock_count_and_adjustment_pages_render(): void
    {
        $this->get('/inventory/stock-counts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/StockCounts')
                ->has('stockCounts.data')
                ->has('warehouses')
                ->has('products')
            );

        $this->get('/inventory/adjustments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/StockAdjustments')
                ->has('adjustments.data')
                ->has('warehouses')
                ->has('products')
            );
    }

    private function seedWarehouseStock(int $quantityE6, int $unitCostMinor): void
    {
        app(MovingWeightedAverageInventoryService::class)->recordReceipt(
            sourceType: 'manual_receipt',
            sourceId: (string) Str::uuid(),
            sourceLineId: (string) Str::uuid(),
            movementDate: '2026-01-05',
            productId: $this->product->id,
            unitOfMeasureId: $this->uom->id,
            currency: 'EGP',
            quantityE6: $quantityE6,
            unitCostMinor: $unitCostMinor,
            fiscalYearId: $this->fiscalYear->id,
            financialPeriodId: $this->period->id,
            actorId: $this->user->id,
            warehouseId: $this->warehouse->id,
        );
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
