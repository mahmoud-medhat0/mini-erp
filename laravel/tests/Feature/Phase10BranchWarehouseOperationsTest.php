<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Inventory\StockTransferService;
use App\Application\Reports\StockMovementReportService;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
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
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10BranchWarehouseOperationsTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    private $uom;

    private Product $product;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

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
            'inventory.create',
            'inventory.edit',
            'inventory.delete',
            'inventory.transfer',
            'inventory.approve',
            'inventory.post',
            'inventory.receive',
            'reports.view',
            'view_financials',
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
            'code' => 'PH10-STOCK-001',
            'name' => ['en' => 'Phase 10 Stock Item', 'ar' => 'صنف مخزون مرحلة 10'],
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

        $northBranch = Branch::query()->create([
            'code' => 'NORTH',
            'name' => ['en' => 'North Branch', 'ar' => 'فرع الشمال'],
            'is_active' => true,
        ]);

        $southBranch = Branch::query()->create([
            'code' => 'SOUTH',
            'name' => ['en' => 'South Branch', 'ar' => 'فرع الجنوب'],
            'is_active' => true,
        ]);

        $this->sourceWarehouse = Warehouse::query()->create([
            'code' => 'NORTH-MAIN',
            'name' => ['en' => 'North Main Warehouse', 'ar' => 'مخزن الشمال الرئيسي'],
            'branch_id' => $northBranch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->destinationWarehouse = Warehouse::query()->create([
            'code' => 'SOUTH-MAIN',
            'name' => ['en' => 'South Main Warehouse', 'ar' => 'مخزن الجنوب الرئيسي'],
            'branch_id' => $southBranch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }

    public function test_phase10_schema_supports_operational_warehouses_without_stock_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasTable('warehouse'));
        $this->assertTrue(Schema::hasTable('stock_location'));
        $this->assertTrue(Schema::hasTable('stock_transfer'));
        $this->assertTrue(Schema::hasTable('stock_transfer_line'));
        $this->assertTrue(Schema::hasTable('stock_transfer_receipt'));
        $this->assertTrue(Schema::hasTable('stock_transfer_receipt_line'));

        $companyId = 'company'.'_id';
        $branchId = 'branch'.'_id';
        $tenantId = 'tenant'.'_id';

        $this->assertTrue(Schema::hasColumn('stock_balance', 'warehouse_id'));
        $this->assertTrue(Schema::hasColumn('stock_movement_ledger', 'warehouse_id'));
        $this->assertFalse(Schema::hasColumn('stock_balance', $companyId));
        $this->assertFalse(Schema::hasColumn('stock_balance', $branchId));
        $this->assertFalse(Schema::hasColumn('stock_balance', $tenantId));
        $this->assertFalse(Schema::hasColumn('stock_movement_ledger', $companyId));
        $this->assertFalse(Schema::hasColumn('stock_movement_ledger', $branchId));
        $this->assertFalse(Schema::hasColumn('stock_movement_ledger', $tenantId));
    }

    public function test_receipt_can_target_an_explicit_warehouse(): void
    {
        $movement = $this->inventoryService()->recordReceipt(
            sourceType: 'manual_receipt',
            sourceId: (string) Str::uuid(),
            sourceLineId: (string) Str::uuid(),
            movementDate: '2026-01-05',
            productId: $this->product->id,
            unitOfMeasureId: $this->uom->id,
            currency: 'EGP',
            quantityE6: 10000000,
            unitCostMinor: 1000,
            fiscalYearId: $this->fiscalYear->id,
            financialPeriodId: $this->period->id,
            actorId: $this->user->id,
            warehouseId: $this->sourceWarehouse->id,
        );

        $this->assertSame($this->sourceWarehouse->id, $movement->warehouse_id);
        $this->assertSame('receipt', $movement->movement_type);

        $balance = StockBalance::query()
            ->where('warehouse_id', $this->sourceWarehouse->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();

        $this->assertSame(10000000, $balance->quantity_e6);
        $this->assertSame(10000, $balance->valuation_amount_minor);
        $this->assertSame(1000, $balance->avg_unit_cost_e6);

        $report = app(StockMovementReportService::class)->generate(productId: $this->product->id, warehouseId: $this->sourceWarehouse->id);

        $this->assertCount(1, $report['rows']);
        $this->assertSame($this->sourceWarehouse->id, $report['rows'][0]['warehouse_id']);
        $this->assertSame('NORTH-MAIN', $report['rows'][0]['warehouse_code']);
        $this->assertSame('NORTH', $report['rows'][0]['branch_code']);
    }

    public function test_stock_transfer_lifecycle_preserves_cost_without_gl_revenue_or_vat(): void
    {
        $this->seedWarehouseStock(10000000, 1000);
        $journalCountBeforeTransfer = JournalEntry::query()->count();

        $service = app(StockTransferService::class);
        $transfer = $service->create([
            'transfer_date' => '2026-01-10',
            'source_warehouse_id' => $this->sourceWarehouse->id,
            'destination_warehouse_id' => $this->destinationWarehouse->id,
            'reference' => 'TR-REF-001',
            'reason' => 'Operational branch replenishment',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 6000000,
                ],
            ],
        ], $this->user->id);

        $transfer = $service->submit($transfer->id, $this->user->id);
        $this->assertSame('submitted', $transfer->status);

        $transfer = $service->approve($transfer->id, $this->user->id);
        $this->assertSame('approved', $transfer->status);
        $this->assertStringStartsWith('ST-2026-', (string) $transfer->number);

        $transfer = $service->issue($transfer->id, $this->user->id);
        $this->assertSame('issued', $transfer->status);

        $sourceBalance = $this->stockBalance($this->sourceWarehouse);
        $this->assertSame(4000000, $sourceBalance->quantity_e6);
        $this->assertSame(4000, $sourceBalance->valuation_amount_minor);

        $transferOut = StockMovementLedger::query()
            ->where('source_type', 'stock_transfer')
            ->where('warehouse_id', $this->sourceWarehouse->id)
            ->firstOrFail();

        $this->assertSame('transfer_out', $transferOut->movement_type);
        $this->assertSame(-6000000, $transferOut->quantity_delta_e6);
        $this->assertSame(-6000, $transferOut->value_delta_minor);
        $this->assertNull($transferOut->journal_entry_id);

        $transfer = $service->receive($transfer->id, [
            'receipt_date' => '2026-01-11',
            'lines' => [
                [
                    'stock_transfer_line_id' => $transfer->lines->first()->id,
                    'quantity_e6' => 2000000,
                ],
            ],
        ], $this->user->id);

        $this->assertSame('partially_received', $transfer->status);
        $destinationBalance = $this->stockBalance($this->destinationWarehouse);
        $this->assertSame(2000000, $destinationBalance->quantity_e6);
        $this->assertSame(2000, $destinationBalance->valuation_amount_minor);

        $transfer = $service->receive($transfer->id, [
            'receipt_date' => '2026-01-12',
            'lines' => [],
        ], $this->user->id);

        $this->assertSame('received', $transfer->status);
        $destinationBalance->refresh();
        $this->assertSame(6000000, $destinationBalance->quantity_e6);
        $this->assertSame(6000, $destinationBalance->valuation_amount_minor);

        $transferInMovements = StockMovementLedger::query()
            ->where('source_type', 'stock_transfer_receipt')
            ->where('warehouse_id', $this->destinationWarehouse->id)
            ->orderBy('movement_date')
            ->get();

        $this->assertCount(2, $transferInMovements);
        $this->assertTrue($transferInMovements->every(fn (StockMovementLedger $movement): bool => $movement->movement_type === 'transfer_in'));
        $this->assertTrue($transferInMovements->every(fn (StockMovementLedger $movement): bool => $movement->journal_entry_id === null));
        $this->assertSame(6000, (int) $transferInMovements->sum('value_delta_minor'));
        $this->assertSame($journalCountBeforeTransfer, JournalEntry::query()->count());
    }

    public function test_incomplete_stock_transfer_blocks_period_close_readiness(): void
    {
        $transfer = app(StockTransferService::class)->create([
            'transfer_date' => '2026-01-20',
            'source_warehouse_id' => $this->sourceWarehouse->id,
            'destination_warehouse_id' => $this->destinationWarehouse->id,
            'reference' => 'CLOSE-BLOCKER-ST',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1000000,
            ]],
        ], $this->user->id);

        $readiness = app(PeriodService::class)->checkCloseReadiness($this->period);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue(collect($readiness['blockers'])->contains(
            fn (array $blocker): bool => $blocker['entity_type'] === 'stock_transfer'
                && $blocker['id'] === $transfer->id
                && $blocker['reason_code'] === 'incomplete_stock_transfer'
        ));
    }

    public function test_transfer_issue_is_blocked_when_source_warehouse_stock_is_insufficient(): void
    {
        $this->seedWarehouseStock(1000000, 1000);

        $service = app(StockTransferService::class);
        $transfer = $service->create([
            'transfer_date' => '2026-01-10',
            'source_warehouse_id' => $this->sourceWarehouse->id,
            'destination_warehouse_id' => $this->destinationWarehouse->id,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                ],
            ],
        ], $this->user->id);

        $transfer = $service->approve($transfer->id, $this->user->id);

        $this->expectException(ValidationException::class);
        $service->issue($transfer->id, $this->user->id);
    }

    public function test_inertia_pages_render_phase10_inventory_workspaces(): void
    {
        // Warehouses, StockTransfers, and StockBalances all stream their rows
        // from a ServerDataTable feed now; the index payload only carries the
        // option lists their filters need, not a client-side paginator.
        $this->get('/inventory/warehouses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Warehouses')
                ->has('warehouses')
                ->has('branches')
                ->has('warehouseTypes')
            );

        $this->get('/inventory/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/StockTransfers')
                ->has('transfers')
                ->has('warehouses')
                ->has('products')
            );

        $this->get('/inventory/stock-balances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/StockBalances')
                ->has('balances')
                ->has('warehouses')
            );

        $this->get('/reports/stock-movements')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/StockMovementsReport')
                ->has('reportData.rows')
                ->has('warehouses')
            );
    }

    private function inventoryService(): MovingWeightedAverageInventoryService
    {
        return app(MovingWeightedAverageInventoryService::class);
    }

    private function seedWarehouseStock(int $quantityE6, int $unitCostMinor): void
    {
        $this->inventoryService()->recordReceipt(
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
            warehouseId: $this->sourceWarehouse->id,
        );
    }

    private function stockBalance(Warehouse $warehouse): StockBalance
    {
        return StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $this->product->id)
            ->where('currency', 'EGP')
            ->firstOrFail();
    }
}
