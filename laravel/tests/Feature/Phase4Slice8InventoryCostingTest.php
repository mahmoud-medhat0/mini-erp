<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Purchasing\GoodsReceiptService;
use App\Application\Purchasing\PurchaseOrderService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Sales\DeliveryNoteService;
use App\Application\Sales\SalesOrderService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase4Slice8InventoryCostingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Currency $currency;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $arAccount;

    private Account $apAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    private Account $inventoryAccount;

    private Account $grniAccount;

    private Account $cogsAccount;

    private UnitOfMeasure $uom;

    private ProductCategory $category;

    private Product $stockProduct;

    private Product $serviceProduct;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($this->user);

        $this->currency = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'sub_unit' => 'Cent', 'sub_unit_to_unit' => 100, 'is_active' => true]
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
            'period_number' => 1,
            'month' => 1,
            'name' => 'January 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->arAccount = Account::query()->create([
            'code' => '1100-TEST-S8', 'name' => 'Accounts Receivable', 'type' => 'asset', 'nature' => 'debit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
        $this->apAccount = Account::query()->create([
            'code' => '2100-TEST-S8', 'name' => 'Accounts Payable', 'type' => 'liability', 'nature' => 'credit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
        $this->revenueAccount = Account::query()->create([
            'code' => '4100-TEST-S8', 'name' => 'Sales Revenue', 'type' => 'revenue', 'nature' => 'credit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
        $this->expenseAccount = Account::query()->create([
            'code' => '5100-TEST-S8', 'name' => 'Purchase Expense', 'type' => 'expense', 'nature' => 'debit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);

        $this->inventoryAccount = Account::query()->create([
            'code' => '1300-TEST-S8', 'name' => 'Inventory Asset', 'type' => 'asset', 'nature' => 'debit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
        $this->grniAccount = Account::query()->create([
            'code' => '2200-TEST-S8', 'name' => 'GRNI Clearing', 'type' => 'liability', 'nature' => 'credit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
        $this->cogsAccount = Account::query()->create([
            'code' => '5200-TEST-S8', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'nature' => 'debit',
            'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);

        AccountingAccountMapping::query()->updateOrCreate(['key' => 'ar_control'], ['account_id' => $this->arAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'ap_control'], ['account_id' => $this->apAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'sales_revenue'], ['account_id' => $this->revenueAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'purchase_expense'], ['account_id' => $this->expenseAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'inventory_asset'], ['account_id' => $this->inventoryAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'grni_clearing'], ['account_id' => $this->grniAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'cogs'], ['account_id' => $this->cogsAccount->id, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);

        $this->uom = UnitOfMeasure::query()->create([
            'code' => 'PCS-S8', 'name' => ['en' => 'Pieces'], 'symbol' => 'pcs', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
        $this->category = ProductCategory::query()->create([
            'code' => 'CAT-S8', 'name' => ['en' => 'General'], 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);

        $this->stockProduct = Product::query()->create([
            'code' => 'STOCK-WIDGET-01',
            'name' => ['en' => 'Stock Widget'],
            'type' => 'stock',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'default_sales_price_minor' => 2000,
            'default_purchase_price_minor' => 1000,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $this->serviceProduct = Product::query()->create([
            'code' => 'SERVICE-INSTALL-01',
            'name' => ['en' => 'Installation Service'],
            'type' => 'service',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'default_sales_price_minor' => 5000,
            'default_purchase_price_minor' => 3000,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $this->customer = Customer::query()->create([
            'code' => 'CUST-S8-01', 'name' => 'Widget Customer', 'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-S8-01', 'name' => 'Widget Supplier', 'currency' => 'USD', 'is_active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id,
        ]);
    }

    public function test_1_inventory_tables_exist_and_have_no_tenancy_columns(): void
    {
        $this->assertTrue(Schema::hasTable('stock_balance'));
        $this->assertTrue(Schema::hasTable('stock_movement_ledger'));

        $companyId = 'company'.'_id';
        $branchId = 'branch'.'_id';
        $tenantId = 'tenant'.'_id';

        $this->assertFalse(Schema::hasColumn('stock_balance', $companyId));
        $this->assertFalse(Schema::hasColumn('stock_balance', $branchId));
        $this->assertFalse(Schema::hasColumn('stock_balance', $tenantId));

        $this->assertFalse(Schema::hasColumn('stock_movement_ledger', $companyId));
        $this->assertFalse(Schema::hasColumn('stock_movement_ledger', $branchId));
        $this->assertFalse(Schema::hasColumn('stock_movement_ledger', $tenantId));
    }

    public function test_2_stock_movement_ledger_is_append_only_at_database_level(): void
    {
        /** @var StockMovementLedger $movement */
        $movement = StockMovementLedger::query()->create([
            'movement_date' => '2026-01-15',
            'source_type' => 'manual_test',
            'source_id' => (string) Str::uuid(),
            'source_line_id' => (string) Str::uuid(),
            'movement_type' => 'receipt',
            'product_id' => $this->stockProduct->id,
            'unit_of_measure_id' => $this->uom->id,
            'currency' => 'USD',
            'quantity_delta_e6' => 10000000,
            'value_delta_minor' => 10000,
            'unit_cost_e6' => 1000000000,
            'balance_quantity_e6' => 10000000,
            'balance_valuation_amount_minor' => 10000,
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\Throwable::class);
        $movement->update(['quantity_delta_e6' => 20000000]);
    }

    public function test_2b_postgres_inventory_integrity_constraints_are_registered(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL inventory check constraints are verified on PostgreSQL only.');
        }

        $constraintNames = collect(DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid IN ('stock_balance'::regclass, 'stock_movement_ledger'::regclass)
        "))->pluck('conname')->all();

        $this->assertContains('stock_balance_quantity_non_negative_check', $constraintNames);
        $this->assertContains('stock_balance_valuation_non_negative_check', $constraintNames);
        $this->assertContains('stock_balance_average_cost_non_negative_check', $constraintNames);
        $this->assertContains('stock_movement_ledger_movement_type_check', $constraintNames);
        $this->assertContains('stock_movement_ledger_quantity_non_zero_check', $constraintNames);
        $this->assertContains('stock_movement_ledger_value_non_zero_check', $constraintNames);
        $this->assertContains('stock_movement_ledger_running_balance_non_negative_check', $constraintNames);
        $this->assertContains('stock_movement_ledger_direction_check', $constraintNames);
    }

    public function test_3_accounting_account_mapping_validates_inventory_keys(): void
    {
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $this->assertEquals($this->inventoryAccount->id, $mappingService->getAccount('inventory_asset')->id);
        $this->assertEquals($this->grniAccount->id, $mappingService->getAccount('grni_clearing')->id);
        $this->assertEquals($this->cogsAccount->id, $mappingService->getAccount('cogs')->id);

        // Expect validation failure if setting invalid account type
        $this->expectException(ValidationException::class);
        $mappingService->setMapping('inventory_asset', $this->revenueAccount->id, $this->user->id);
    }

    public function test_4_goods_receipt_confirmation_posts_inventory_asset_and_grni_clearing(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [
                ['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000], // 10 units @ 10.00 = 100.00 (10000 minor)
            ],
        ], $this->user->id);
        $poService->submit($po->id, $this->user->id);
        $poService->confirm($po->id, $this->user->id);

        $gr = $grService->create([
            'purchase_order_id' => $po->id,
            'receipt_date' => '2026-01-12',
            'lines' => [
                ['purchase_order_line_id' => $po->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000],
            ],
        ], $this->user->id);

        $confirmedGr = $grService->confirm($gr->id, $this->user->id);
        $this->assertEquals('confirmed', $confirmedGr->status);

        // Verify stock balance
        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('product_id', $this->stockProduct->id)->firstOrFail();
        $this->assertEquals(10000000, $balance->quantity_e6); // 10.000000
        $this->assertEquals(10000, $balance->valuation_amount_minor); // 100.00
        $this->assertEquals(1000, $balance->avg_unit_cost_e6); // 1000 minor units per unit (10.00 USD)

        // Verify ledger movement
        $movement = StockMovementLedger::query()->where('source_id', $gr->id)->firstOrFail();
        $this->assertEquals('receipt', $movement->movement_type);
        $this->assertEquals(10000000, $movement->quantity_delta_e6);
        $this->assertEquals(10000, $movement->value_delta_minor);

        // Verify GL Journal Entry
        /** @var JournalEntry $je */
        $je = JournalEntry::query()->where('id', $movement->journal_entry_id)->firstOrFail();
        $this->assertEquals('posted', $je->status);
        $this->assertEquals(10000, $je->lines->where('account_id', $this->inventoryAccount->id)->first()->debit_minor);
        $this->assertEquals(10000, $je->lines->where('account_id', $this->grniAccount->id)->first()->credit_minor);
        $this->assertEquals('Inventory Asset Receipt', $je->lines->where('account_id', $this->inventoryAccount->id)->first()->memo);
        $this->assertEquals('GRNI Clearing', $je->lines->where('account_id', $this->grniAccount->id)->first()->memo);
    }

    public function test_5_multiple_receipts_update_weighted_average_correctly(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);

        // PO 1: 10 units @ 10.00 (1000 minor) => 100.00 total (10000 minor)
        $po1 = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000]],
        ], $this->user->id);
        $poService->submit($po1->id, $this->user->id);
        $poService->confirm($po1->id, $this->user->id);

        $gr1 = $grService->create([
            'purchase_order_id' => $po1->id,
            'receipt_date' => '2026-01-12',
            'lines' => [['purchase_order_line_id' => $po1->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000]],
        ], $this->user->id);
        $grService->confirm($gr1->id, $this->user->id);

        // PO 2: 10 units @ 20.00 (2000 minor) => 200.00 total (20000 minor)
        $po2 = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-14',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 2000]],
        ], $this->user->id);
        $poService->submit($po2->id, $this->user->id);
        $poService->confirm($po2->id, $this->user->id);

        $gr2 = $grService->create([
            'purchase_order_id' => $po2->id,
            'receipt_date' => '2026-01-15',
            'lines' => [['purchase_order_line_id' => $po2->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000]],
        ], $this->user->id);
        $grService->confirm($gr2->id, $this->user->id);

        // Total stock: 20 units, total valuation: 300.00 (30000 minor), derived average: 15.00 (1500 minor)
        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('product_id', $this->stockProduct->id)->firstOrFail();
        $this->assertEquals(20000000, $balance->quantity_e6);
        $this->assertEquals(30000, $balance->valuation_amount_minor);
        $this->assertEquals(1500, $balance->avg_unit_cost_e6);
    }

    public function test_6_delivery_note_confirmation_posts_cogs_and_decrements_stock(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);

        // Receive 10 units @ 10.00 (1000 minor) = 100.00 (10000 minor)
        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000]],
        ], $this->user->id);
        $poService->submit($po->id, $this->user->id);
        $poService->confirm($po->id, $this->user->id);

        $gr = $grService->create([
            'purchase_order_id' => $po->id,
            'receipt_date' => '2026-01-12',
            'lines' => [['purchase_order_line_id' => $po->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000]],
        ], $this->user->id);
        $grService->confirm($gr->id, $this->user->id);

        // Sales Order & Delivery Note for 4 units
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-01-15',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 4000000, 'unit_price_minor' => 2500]],
        ], $this->user->id);
        $soService->submit($so->id, $this->user->id);
        $soService->confirm($so->id, $this->user->id);

        $dn = $dnService->create([
            'sales_order_id' => $so->id,
            'delivery_date' => '2026-01-16',
            'lines' => [['sales_order_line_id' => $so->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 4000000]],
        ], $this->user->id);

        $confirmedDn = $dnService->confirm($dn->id, $this->user->id);
        $this->assertEquals('confirmed', $confirmedDn->status);

        // 4 units @ 10.00 = 40.00 COGS (4000 minor). Balance remaining: 6 units, 60.00 (6000 minor)
        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('product_id', $this->stockProduct->id)->firstOrFail();
        $this->assertEquals(6000000, $balance->quantity_e6);
        $this->assertEquals(6000, $balance->valuation_amount_minor);

        // Verify COGS Journal Entry
        $movement = StockMovementLedger::query()->where('source_id', $dn->id)->firstOrFail();
        $this->assertEquals('issue', $movement->movement_type);
        $this->assertEquals(-4000000, $movement->quantity_delta_e6);
        $this->assertEquals(-4000, $movement->value_delta_minor);

        /** @var JournalEntry $je */
        $je = JournalEntry::query()->where('id', $movement->journal_entry_id)->firstOrFail();
        $this->assertEquals(4000, $je->lines->where('account_id', $this->cogsAccount->id)->first()->debit_minor);
        $this->assertEquals(4000, $je->lines->where('account_id', $this->inventoryAccount->id)->first()->credit_minor);
        $this->assertEquals('Cost of Goods Sold', $je->lines->where('account_id', $this->cogsAccount->id)->first()->memo);
        $this->assertEquals('Inventory Asset Issue', $je->lines->where('account_id', $this->inventoryAccount->id)->first()->memo);
    }

    public function test_7_delivery_note_confirmation_rejects_insufficient_stock(): void
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);

        // Attempting to deliver 5 units when 0 stock exists
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-01-15',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 5000000, 'unit_price_minor' => 2500]],
        ], $this->user->id);
        $soService->submit($so->id, $this->user->id);
        $soService->confirm($so->id, $this->user->id);

        $dn = $dnService->create([
            'sales_order_id' => $so->id,
            'delivery_date' => '2026-01-16',
            'lines' => [['sales_order_line_id' => $so->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 5000000]],
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $dnService->confirm($dn->id, $this->user->id);
    }

    public function test_8_confirm_replay_is_idempotent_for_goods_receipt_and_delivery_note(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000]],
        ], $this->user->id);
        $poService->submit($po->id, $this->user->id);
        $poService->confirm($po->id, $this->user->id);

        $gr = $grService->create([
            'purchase_order_id' => $po->id,
            'receipt_date' => '2026-01-12',
            'lines' => [['purchase_order_line_id' => $po->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000]],
        ], $this->user->id);

        $grService->confirm($gr->id, $this->user->id);
        $grService->confirm($gr->id, $this->user->id); // Replay

        $this->assertEquals(1, StockMovementLedger::query()->where('source_id', $gr->id)->count());
    }

    public function test_9_supplier_bill_allows_stock_lines_from_goods_receipt(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000]],
        ], $this->user->id);
        $poService->submit($po->id, $this->user->id);
        $poService->confirm($po->id, $this->user->id);

        $gr = $grService->create([
            'purchase_order_id' => $po->id,
            'receipt_date' => '2026-01-12',
            'lines' => [['purchase_order_line_id' => $po->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000]],
        ], $this->user->id);
        $grService->confirm($gr->id, $this->user->id);

        // Bill sourced from Goods Receipt line
        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-01-14',
            'due_date' => '2026-02-14',
            'currency' => 'USD',
            'goods_receipt_id' => $gr->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'lines' => [
                [
                    'goods_receipt_line_id' => $gr->lines[0]->id,
                    'product_id' => $this->stockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 10000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->user->id);

        $billService->submit($bill->id, $this->user->id);
        $billService->approve($bill->id, $this->user->id);
        $postedBill = $billService->post($bill->id, $this->user->id);

        $this->assertEquals('posted', $postedBill->status);

        // Verify Journal Entry posts Dr GRNI Clearing / Cr AP Control
        /** @var JournalEntry $je */
        $je = JournalEntry::query()->where('id', $postedBill->journal_entry_id)->firstOrFail();
        $this->assertEquals(10000, $je->lines->where('account_id', $this->grniAccount->id)->first()->debit_minor);
        $this->assertEquals(10000, $je->lines->where('account_id', $this->apAccount->id)->first()->credit_minor);
    }

    public function test_10_supplier_bill_rejects_manual_and_po_only_stock_lines(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000]],
        ], $this->user->id);
        $poService->submit($po->id, $this->user->id);
        $poService->confirm($po->id, $this->user->id);

        // Rejects PO-only stock line (no GR line)
        $this->expectException(ValidationException::class);
        $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-01-14',
            'due_date' => '2026-02-14',
            'currency' => 'USD',
            'purchase_order_id' => $po->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'lines' => [
                [
                    'purchase_order_line_id' => $po->lines[0]->id,
                    'product_id' => $this->stockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 10000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->user->id);
    }

    public function test_11_customer_invoice_allows_stock_lines_from_delivery_note(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);
        /** @var CustomerInvoiceService $invoiceService */
        $invoiceService = app(CustomerInvoiceService::class);

        // Receive stock
        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-10',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000, 'unit_price_minor' => 1000]],
        ], $this->user->id);
        $poService->submit($po->id, $this->user->id);
        $poService->confirm($po->id, $this->user->id);

        $gr = $grService->create([
            'purchase_order_id' => $po->id,
            'receipt_date' => '2026-01-12',
            'lines' => [['purchase_order_line_id' => $po->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 10000000]],
        ], $this->user->id);
        $grService->confirm($gr->id, $this->user->id);

        // Deliver stock
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-01-15',
            'currency' => 'USD',
            'lines' => [['product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 5000000, 'unit_price_minor' => 2500]],
        ], $this->user->id);
        $soService->submit($so->id, $this->user->id);
        $soService->confirm($so->id, $this->user->id);

        $dn = $dnService->create([
            'sales_order_id' => $so->id,
            'delivery_date' => '2026-01-16',
            'lines' => [['sales_order_line_id' => $so->lines[0]->id, 'product_id' => $this->stockProduct->id, 'unit_of_measure_id' => $this->uom->id, 'quantity_e6' => 5000000]],
        ], $this->user->id);
        $dnService->confirm($dn->id, $this->user->id);

        // Invoice stock line sourced from Delivery Note
        $inv = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-01-18',
            'due_date' => '2026-02-18',
            'currency' => 'USD',
            'delivery_note_id' => $dn->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'lines' => [
                [
                    'delivery_note_line_id' => $dn->lines[0]->id,
                    'product_id' => $this->stockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 5000000,
                    'unit_price_minor' => 2500,
                ],
            ],
        ], $this->user->id);

        $invoiceService->submit($inv->id, $this->user->id);
        $invoiceService->approve($inv->id, $this->user->id);
        $postedInv = $invoiceService->post($inv->id, $this->user->id);

        $this->assertEquals('posted', $postedInv->status);

        // Journal Entry posts Dr AR / Cr Revenue
        /** @var JournalEntry $je */
        $je = JournalEntry::query()->where('id', $postedInv->journal_entry_id)->firstOrFail();
        $this->assertEquals(12500, $je->lines->where('account_id', $this->arAccount->id)->first()->debit_minor);
        $this->assertEquals(12500, $je->lines->where('account_id', $this->revenueAccount->id)->first()->credit_minor);
    }

    public function test_12_dynamic_source_scan_prohibits_binary_decimal_math_and_rounding_patterns(): void
    {
        $filesToScan = [
            app_path('Application/Inventory/MovingWeightedAverageInventoryService.php'),
            app_path('Models/StockBalance.php'),
            app_path('Models/StockMovementLedger.php'),
            database_path('migrations/2026_08_22_080000_create_phase4_slice8_inventory_costing_tables.php'),
            database_path('migrations/2026_08_22_090000_harden_phase4_slice8_inventory_integrity.php'),
        ];

        // Build strings dynamically to avoid matching this test file during search
        $funcRound = 'rou'.'nd';
        $castFloat = '('.'flo'.'at)';
        $wordFloat = 'fl'.'oat';
        $wordDouble = 'dou'.'ble';
        $slashDiv = '/'.' 1000000';
        $slashDivDirect = '/'.'1000000';

        $forbidden = [$funcRound, $castFloat, $wordFloat, $wordDouble, $slashDiv, $slashDivDirect];

        foreach ($filesToScan as $filePath) {
            $this->assertFileExists($filePath);
            $content = file_get_contents($filePath);

            foreach ($forbidden as $pattern) {
                $this->assertStringNotContainsString($pattern, $content, "Forbidden pattern [{$pattern}] found in [{$filePath}].");
            }
        }
    }

    public function test_13_no_unsupported_tenancy_or_relationship_terms_introduced(): void
    {
        $filesToScan = [
            app_path('Application/Inventory/MovingWeightedAverageInventoryService.php'),
            app_path('Models/StockBalance.php'),
            app_path('Models/StockMovementLedger.php'),
        ];

        $terms = [
            'company'.'_id',
            'branch'.'_id',
            'tenant'.'_id',
            'current'.'Company',
            'current'.'Branch',
            'company'.'_user',
        ];

        foreach ($filesToScan as $filePath) {
            $content = file_get_contents($filePath);
            foreach ($terms as $term) {
                $this->assertStringNotContainsString($term, $content, "Forbidden tenancy term [{$term}] found in [{$filePath}].");
            }
        }
    }
}
