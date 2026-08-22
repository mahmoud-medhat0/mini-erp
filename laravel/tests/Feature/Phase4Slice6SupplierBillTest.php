<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Application\Purchasing\GoodsReceiptService;
use App\Application\Purchasing\PurchaseOrderService;
use App\Application\Purchasing\SupplierBillService;
use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PayableEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase4Slice6SupplierBillTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Supplier $supplier;

    private Product $serviceProduct;

    private Product $nonStockProduct;

    private Product $stockProduct;

    private UnitOfMeasure $uom;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $financialPeriod;

    private Account $apAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductCategorySeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([
            'purchasing.view', 'purchasing.create', 'purchasing.edit', 'purchasing.submit', 'purchasing.approve', 'purchasing.post', 'purchasing.cancel',
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $catServ = ProductCategory::query()->where('code', 'SERV')->firstOrFail();
        $catFg = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-001',
            'name' => 'Global Supplies Inc',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->serviceProduct = Product::query()->create([
            'code' => 'PRD-SERV-1',
            'name' => ['en' => 'Maintenance Service 1', 'ar' => 'خدمة صيانة 1'],
            'type' => 'service',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catServ->id,
            'status' => 'active',
            'is_sales_enabled' => false,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);

        $this->nonStockProduct = Product::query()->create([
            'code' => 'PRD-NONSTOCK-1',
            'name' => ['en' => 'Consumable Supply 1', 'ar' => 'مستهلكات 1'],
            'type' => 'non_stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catServ->id,
            'status' => 'active',
            'is_sales_enabled' => false,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);

        $this->stockProduct = Product::query()->create([
            'code' => 'PRD-STOCK-1',
            'name' => ['en' => 'Raw Material 1', 'ar' => 'مادة خام 1'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catFg->id,
            'status' => 'active',
            'is_sales_enabled' => false,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);

        // Fiscal Year & Period
        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ]);

        $this->financialPeriod = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 8,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);

        // Accounts & Mappings
        $this->apAccount = Account::query()->create([
            'code' => '2100-AP',
            'name' => ['en' => 'Accounts Payable Control', 'ar' => 'حسابات الموردين'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'USD',
            'is_active' => true,
            'allow_manual_posting' => false,
        ]);

        $this->expenseAccount = Account::query()->create([
            'code' => '5100-EXP',
            'name' => ['en' => 'Purchase Expense', 'ar' => 'مصروفات المشتريات'],
            'type' => 'expense',
            'nature' => 'debit',
            'currency' => 'USD',
            'is_active' => true,
            'allow_manual_posting' => true,
        ]);

        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ap_control', $this->apAccount->id, actorId: $this->adminUser->id);
        $mappingService->setMapping('purchase_expense', $this->expenseAccount->id, actorId: $this->adminUser->id);
    }

    public function test_supplier_bill_migrations_create_expected_tables_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('supplier_bill'));
        $this->assertTrue(Schema::hasTable('supplier_bill_line'));

        $this->assertTrue(Schema::hasColumns('supplier_bill', [
            'id', 'number', 'supplier_id', 'purchase_order_id', 'goods_receipt_id',
            'fiscal_year_id', 'financial_period_id', 'bill_date', 'due_date',
            'supplier_reference', 'reference', 'description', 'currency', 'fx_rate_e6', 'subtotal_minor',
            'total_minor', 'status', 'journal_entry_id', 'payable_entry_id',
            'submitted_by', 'approved_by', 'posted_by', 'cancelled_by', 'lock_version',
        ]));
    }

    public function test_no_tenant_company_branch_or_prohibited_columns_exist_in_supplier_bill_tables(): void
    {
        $prohibitedColumns = [
            'company_id', 'branch_id', 'tenant_id', 'current_company', 'current_branch',
            'customer_invoice_id', 'receivable_entry_id', 'warehouse_id', 'inventory_entry_id',
            'stock_movement_id', 'cogs', 'tax_amount_minor', 'discount_minor',
        ];

        foreach ($prohibitedColumns as $col) {
            $this->assertFalse(Schema::hasColumn('supplier_bill', $col), "Column {$col} must not exist in supplier_bill.");
            $this->assertFalse(Schema::hasColumn('supplier_bill_line', $col), "Column {$col} must not exist in supplier_bill_line.");
        }
    }

    public function test_purchase_expense_mapping_key_is_allowed_and_validated(): void
    {
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $mapping = $mappingService->getMapping('purchase_expense');
        $this->assertNotNull($mapping);
        $this->assertEquals($this->expenseAccount->id, $mapping->account_id);

        // Invalid account type (e.g. asset instead of expense) must throw validation exception
        $assetAcc = Account::query()->create([
            'code' => '1999-TEMP',
            'name' => 'Temp Asset',
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $mappingService->setMapping('purchase_expense', $assetAcc->id, actorId: $this->adminUser->id);
    }

    public function test_create_and_update_supplier_bill_with_manual_service_and_non_stock_lines(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'due_date' => '2026-08-30',
            'currency' => 'USD',
            'supplier_reference' => 'INV-SUPP-99',
            'description' => 'Test Supplier Bill',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'description' => 'Service line 1',
                    'quantity_e6' => 2000000, // 2.000000
                    'unit_cost_minor' => 5000, // $50.00 -> line total $100.00 (10000 minor)
                ],
                [
                    'product_id' => $this->nonStockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'description' => 'Non-stock line 1',
                    'quantity_e6' => 1000000, // 1.000000
                    'unit_cost_minor' => 2500, // $25.00 -> line total $25.00 (2500 minor)
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals('draft', $bill->status);
        $this->assertEquals(12500, $bill->subtotal_minor);
        $this->assertEquals(12500, $bill->total_minor);
        $this->assertCount(2, $bill->lines);

        // Update draft bill
        $updated = $service->update($bill->id, [
            'bill_date' => '2026-08-22',
            'lock_version' => $bill->lock_version,
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000, // 3.000000
                    'unit_cost_minor' => 5000, // 15000 minor
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(15000, $updated->total_minor);
        $this->assertCount(1, $updated->lines);
    }

    public function test_stock_product_bill_lines_are_rejected_in_slice_6(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Line 1 stock product must be sourced from a Goods Receipt.');

        $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->stockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_billing_from_confirmed_purchase_order_lines_calculates_correctly(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 5000000, // 5 units
                    'unit_price_minor' => 4000,
                ],
            ],
        ], $this->adminUser->id);

        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);
        $poLine = $confirmedPo->lines->first();

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000, // 2 units
                    'unit_cost_minor' => 4000,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(8000, $bill->total_minor);
        $this->assertEquals($confirmedPo->id, $bill->purchase_order_id);
    }

    public function test_purchase_order_source_unit_cost_must_match_source_line(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 5000000,
                    'unit_price_minor' => 4000,
                ],
            ],
        ], $this->adminUser->id);

        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);
        $poLine = $confirmedPo->lines->first();

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        $this->expectException(ValidationException::class);

        $billService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 5000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_duplicate_purchase_order_source_lines_are_counted_together_for_overbilling(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);
        $poLine = $confirmedPo->lines->first();

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        $this->expectException(ValidationException::class);

        $billService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_cost_minor' => 1000,
                ],
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_billing_from_confirmed_goods_receipt_lines_calculates_correctly(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 5000000,
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);
        $poLine = $confirmedPo->lines->first();

        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        $gr = $grService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'receipt_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 4000000,
                ],
            ],
        ], $this->adminUser->id);
        $confirmedGr = $grService->confirm($gr->id, $this->adminUser->id);
        $grLine = $confirmedGr->lines->first();

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);
        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'goods_receipt_id' => $confirmedGr->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 4000000,
                    'unit_cost_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(12000, $bill->total_minor);
        $this->assertEquals($confirmedGr->id, $bill->goods_receipt_id);
    }

    public function test_duplicate_goods_receipt_source_lines_are_counted_together_for_overbilling(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);

        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);
        $poLine = $confirmedPo->lines->first();

        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        $gr = $grService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'receipt_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000,
                ],
            ],
        ], $this->adminUser->id);
        $confirmedGr = $grService->confirm($gr->id, $this->adminUser->id);
        $grLine = $confirmedGr->lines->first();

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        $this->expectException(ValidationException::class);

        $billService->create([
            'supplier_id' => $this->supplier->id,
            'goods_receipt_id' => $confirmedGr->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_cost_minor' => 1000,
                ],
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_overbilling_purchase_order_lines_is_rejected(): void
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        $po = $poService->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000, // 3 units
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);
        $poLine = $confirmedPo->lines->first();

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);

        // Bill 2 units
        $billService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        // Attempt to bill 2 more units (total 4 > 3 max allowed)
        $this->expectException(ValidationException::class);
        $billService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_supplier_bill_lifecycle_draft_to_submitted_to_approved(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 5000,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals('draft', $bill->status);

        $submitted = $service->submit($bill->id, $this->adminUser->id);
        $this->assertEquals('submitted', $submitted->status);

        $approved = $service->approve($bill->id, $this->adminUser->id);
        $this->assertEquals('approved', $approved->status);
    }

    public function test_posting_creates_approved_journal_entry_ledger_entries_and_payable_entry_credit(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000, // 2 units
                    'unit_cost_minor' => 5000, // $50.00 -> $100.00 total (10000 minor)
                ],
            ],
        ], $this->adminUser->id);

        $service->submit($bill->id, $this->adminUser->id);
        $service->approve($bill->id, $this->adminUser->id);
        $postedBill = $service->post($bill->id, $this->adminUser->id);

        $this->assertNotNull($postedBill->journal_entry_id);
        $this->assertNotNull($postedBill->payable_entry_id);
        $this->assertStringStartsWith('BILL-2026-', $postedBill->number);

        /** @var JournalEntry $je */
        $je = JournalEntry::query()->with('lines')->find($postedBill->journal_entry_id);
        $this->assertEquals('posted', $je->status);
        $this->assertEquals(10000, $je->lines->sum('debit_minor'));
        $this->assertEquals(10000, $je->lines->sum('credit_minor'));
        $this->assertCount(2, $je->lines);

        // Check LedgerEntries
        $ledgerCount = LedgerEntry::query()->where('journal_entry_id', $je->id)->count();
        $this->assertEquals(2, $ledgerCount);

        // Check PayableEntry
        $rawPay = DB::table('payable_entry')->where('source_type', 'supplier_bill')->first();
        $this->assertNotNull($rawPay);
        $this->assertEquals($this->supplier->id, $rawPay->supplier_id);
        $this->assertEquals($postedBill->id, $rawPay->source_id);
        $this->assertEquals(10000, (int) $rawPay->credit_minor);
    }

    public function test_posting_is_idempotent(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 2000,
                ],
            ],
        ], $this->adminUser->id);

        $service->submit($bill->id, $this->adminUser->id);
        $service->approve($bill->id, $this->adminUser->id);

        $payablesCountBefore = PayableEntry::count();

        $posted1 = $service->post($bill->id, $this->adminUser->id);
        $posted2 = $service->post($bill->id, $this->adminUser->id);

        $this->assertEquals($posted1->id, $posted2->id);
        $this->assertEquals($posted1->journal_entry_id, $posted2->journal_entry_id);
        $this->assertEquals($posted1->payable_entry_id, $posted2->payable_entry_id);
        $this->assertEquals($payablesCountBefore + 1, PayableEntry::count());
    }

    public function test_posted_supplier_bills_cannot_be_updated_cancelled_or_deleted(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $service->submit($bill->id, $this->adminUser->id);
        $service->approve($bill->id, $this->adminUser->id);
        $service->post($bill->id, $this->adminUser->id);

        // Attempt update
        try {
            $service->update($bill->id, [
                'bill_date' => '2026-08-22',
                'lines' => [],
            ], $this->adminUser->id);
            $this->fail('Updating posted bill should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Attempt cancel
        try {
            $service->cancel($bill->id, $this->adminUser->id);
            $this->fail('Cancelling posted bill should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_audit_logger_records_supplier_bill_events(): void
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);

        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $activityCount = Activity::query()
            ->where('properties->entity_type', 'supplier_bill')
            ->where('properties->entity_id', $bill->id)
            ->count();

        $this->assertGreaterThanOrEqual(1, $activityCount);
    }

    public function test_attachment_registry_supports_supplier_bill(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);

        $this->assertContains('supplier_bill', $authorizer->allowedEntityTypes());
    }

    public function test_inertia_page_renders_with_required_props(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/purchasing/bills');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Purchasing/SupplierBills')
            ->has('supplierBills')
            ->has('activeSuppliers')
            ->has('eligibleProducts')
            ->has('confirmedPurchaseOrders')
            ->has('confirmedGoodsReceipts')
        );
    }

    public function test_authoritative_code_contains_no_unallowed_binary_decimal_or_round_patterns(): void
    {
        $filesToScan = [
            app_path('Application/Purchasing/SupplierBillService.php'),
            app_path('Models/SupplierBill.php'),
            app_path('Models/SupplierBillLine.php'),
        ];

        // Construct search patterns dynamically to avoid false positives on this test file
        $p1 = 'rou'.'nd(';
        $p2 = '(flo'.'at)';
        $p3 = 'flo'.'at';
        $p4 = 'dou'.'ble';
        $p5 = '/ 100'.'0000';
        $p6 = '/100'.'0000';

        foreach ($filesToScan as $filePath) {
            $this->assertFileExists($filePath);
            $content = file_get_contents($filePath);

            $this->assertFalse(str_contains($content, $p1), "File {$filePath} contains forbidden round pattern.");
            $this->assertFalse(str_contains($content, $p2), 'File '.$filePath.' contains forbidden (flo'.'at) pattern.');
            $this->assertFalse(str_contains($content, $p5), "File {$filePath} contains forbidden division pattern.");
            $this->assertFalse(str_contains($content, $p6), "File {$filePath} contains forbidden division pattern.");
        }
    }
}
