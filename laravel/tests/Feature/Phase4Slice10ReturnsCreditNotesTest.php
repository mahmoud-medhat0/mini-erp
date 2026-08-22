<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PayableEntrySettlementService;
use App\Application\Accounting\ReceivableEntrySettlementService;
use App\Application\Purchasing\GoodsReceiptService;
use App\Application\Purchasing\PurchaseOrderService;
use App\Application\Purchasing\PurchaseReturnService;
use App\Application\Purchasing\SupplierAdjustmentNoteService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ArAgingReportService;
use App\Application\Reports\ArToGlReconciliationReportService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceRevisionService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Sales\DeliveryNoteService;
use App\Application\Sales\SalesOrderService;
use App\Application\Sales\SalesReturnService;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\CustomerInvoiceRevision;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseReturn;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
use App\Models\Supplier;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase4Slice10ReturnsCreditNotesTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-08-22';

    private const CURRENCY = 'EGP';

    private User $adminUser;

    private User $plainUser;

    private Customer $customer;

    private Supplier $supplier;

    private UnitOfMeasure $uom;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $financialPeriod;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductCategorySeeder::class);
        $this->seed(AccountingCoreSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([
            'sales.view', 'sales.create', 'sales.edit', 'sales.submit', 'sales.approve', 'sales.post', 'sales.cancel',
            'sales.returns', 'sales.credit_notes', 'sales.invoice_revisions',
            'purchasing.view', 'purchasing.create', 'purchasing.edit', 'purchasing.submit', 'purchasing.approve', 'purchasing.post', 'purchasing.cancel',
            'purchasing.returns', 'purchasing.adjustment_notes',
        ]);

        $this->plainUser = User::factory()->create();

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $catFg = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->customer = Customer::query()->create([
            'code' => 'CUST-S10',
            'name' => 'Slice Ten Trading',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-S10',
            'name' => 'Slice Ten Supplies',
            'status' => 'active',
            'lock_version' => 1,
        ]);

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
    }

    private function nextCode(string $prefix): string
    {
        $this->sequence++;

        return $prefix.'-'.$this->sequence;
    }

    private function makeStockProduct(int $purchaseCostMinor = 2000): Product
    {
        $catFg = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        return Product::query()->create([
            'code' => $this->nextCode('PRD-STOCK'),
            'name' => ['en' => 'Stock Widget '.$this->sequence, 'ar' => 'منتج '.$this->sequence],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catFg->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::query()->create([
            'code' => $this->nextCode('CUST'),
            'name' => 'Customer '.$this->sequence,
            'status' => 'active',
            'lock_version' => 1,
        ]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::query()->create([
            'code' => $this->nextCode('SUPP'),
            'name' => 'Supplier '.$this->sequence,
            'status' => 'active',
            'lock_version' => 1,
        ]);
    }

    private function mappedAccount(string $key): Account
    {
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        return $mappingService->getAccount($key);
    }

    private function createConfirmedGoodsReceiptWithLine(Supplier $supplier, Product $product, int $qtyE6, int $unitCostMinor): array
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        $po = $poService->create([
            'supplier_id' => $supplier->id,
            'order_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => $qtyE6,
                    'unit_price_minor' => $unitCostMinor,
                ],
            ],
        ], $this->adminUser->id);
        $poService->submit($po->id, $this->adminUser->id);
        $confirmedPo = $poService->confirm($po->id, $this->adminUser->id);

        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        $gr = $grService->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $confirmedPo->id,
            'receipt_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'purchase_order_line_id' => $confirmedPo->lines->first()->id,
                    'product_id' => $product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => $qtyE6,
                ],
            ],
        ], $this->adminUser->id);
        $confirmedGr = $grService->confirm($gr->id, $this->adminUser->id);

        return ['gr' => $confirmedGr, 'line' => $confirmedGr->lines->first()];
    }

    private function createConfirmedDeliveryNoteWithLine(Customer $customer, Product $product, int $qtyE6, int $unitPriceMinor): array
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $customer->id,
            'order_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => $qtyE6,
                    'unit_price_minor' => $unitPriceMinor,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);
        $dn = $dnService->create([
            'sales_order_id' => $confirmedSo->id,
            'delivery_date' => self::DATE,
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'quantity_e6' => $qtyE6,
                ],
            ],
        ], $this->adminUser->id);
        $confirmedDn = $dnService->confirm($dn->id, $this->adminUser->id);

        return ['dn' => $confirmedDn, 'line' => $confirmedDn->lines->first()];
    }

    private function postInvoiceForDn(DeliveryNote $dn, DeliveryNoteLine $dnLine, int $qtyE6, int $unitPriceMinor): CustomerInvoice
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);
        $invoice = $service->create([
            'customer_id' => $dn->salesOrder->customer_id,
            'delivery_note_id' => $dn->id,
            'invoice_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'delivery_note_line_id' => $dnLine->id,
                    'product_id' => $dnLine->product_id,
                    'unit_of_measure_id' => $dnLine->unit_of_measure_id,
                    'quantity_e6' => $qtyE6,
                    'unit_price_minor' => $unitPriceMinor,
                ],
            ],
        ], $this->adminUser->id);
        $service->approve($invoice->id, $this->adminUser->id);

        return $service->post($invoice->id, $this->adminUser->id);
    }

    private function postBillForGr(GoodsReceipt $gr, GoodsReceiptLine $grLine, int $qtyE6, int $unitCostMinor): SupplierBill
    {
        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);
        $bill = $service->create([
            'supplier_id' => $gr->purchaseOrder->supplier_id,
            'goods_receipt_id' => $gr->id,
            'bill_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'product_id' => $grLine->product_id,
                    'unit_of_measure_id' => $grLine->unit_of_measure_id,
                    'quantity_e6' => $qtyE6,
                    'unit_cost_minor' => $unitCostMinor,
                ],
            ],
        ], $this->adminUser->id);
        $service->submit($bill->id, $this->adminUser->id);
        $service->approve($bill->id, $this->adminUser->id);

        return $service->post($bill->id, $this->adminUser->id);
    }

    private function postServiceProductBill(int $qtyE6, int $unitCostMinor): SupplierBill
    {
        $catServ = ProductCategory::query()->where('code', 'SERV')->firstOrFail();
        $serviceProduct = Product::query()->create([
            'code' => $this->nextCode('PRD-SERV'),
            'name' => ['en' => 'Adjustable Service '.$this->sequence, 'ar' => 'خدمة '.$this->sequence],
            'type' => 'service',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catServ->id,
            'status' => 'active',
            'is_sales_enabled' => false,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);

        /** @var SupplierBillService $service */
        $service = app(SupplierBillService::class);
        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'product_id' => $serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'description' => 'Service billing line',
                    'quantity_e6' => $qtyE6,
                    'unit_cost_minor' => $unitCostMinor,
                ],
            ],
        ], $this->adminUser->id);
        $service->submit($bill->id, $this->adminUser->id);
        $service->approve($bill->id, $this->adminUser->id);

        return $service->post($bill->id, $this->adminUser->id);
    }

    private function createPostedSalesReturn(DeliveryNote $dn, DeliveryNoteLine $dnLine, Product $product, int $qtyE6, ?CustomerInvoice $invoice = null, string $disposition = 'restock_original_cost', ?int $manualRestockValueMinor = null): SalesReturn
    {
        /** @var SalesReturnService $service */
        $service = app(SalesReturnService::class);

        $lineData = [
            'delivery_note_line_id' => $dnLine->id,
            'product_id' => $product->id,
            'quantity_e6' => $qtyE6,
            'disposition' => $disposition,
        ];

        if ($manualRestockValueMinor !== null) {
            $lineData['manual_restock_value_minor'] = $manualRestockValueMinor;
        }

        if ($invoice !== null) {
            $lineData['customer_invoice_line_id'] = $invoice->lines->first()->id;
        }

        $return = $service->create([
            'customer_id' => $dn->salesOrder->customer_id,
            'delivery_note_id' => $dn->id,
            'customer_invoice_id' => $invoice?->id,
            'return_date' => self::DATE,
            'currency' => self::CURRENCY,
            'reason' => 'damaged in transit',
            'lines' => [$lineData],
        ], $this->adminUser->id);

        $service->approve($return->id, $this->adminUser->id);

        return $service->post($return->id, $this->adminUser->id);
    }

    private function createPostedPurchaseReturn(GoodsReceipt $gr, GoodsReceiptLine $grLine, Product $product, int $qtyE6): PurchaseReturn
    {
        /** @var PurchaseReturnService $service */
        $service = app(PurchaseReturnService::class);

        $return = $service->create([
            'supplier_id' => $gr->purchaseOrder->supplier_id,
            'goods_receipt_id' => $gr->id,
            'return_date' => self::DATE,
            'currency' => self::CURRENCY,
            'reason' => 'defective items',
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'product_id' => $product->id,
                    'quantity_e6' => $qtyE6,
                ],
            ],
        ], $this->adminUser->id);

        $service->approve($return->id, $this->adminUser->id);

        return $service->post($return->id, $this->adminUser->id);
    }

    private function createPostedCreditNote(CustomerInvoice $invoice, ?CustomerInvoiceLine $invoiceLine, ?int $qtyE6, int $unitPriceMinor, array $overrides = []): CustomerCreditNote
    {
        /** @var CustomerCreditNoteService $service */
        $service = app(CustomerCreditNoteService::class);

        $lineData = [
            'description' => 'Credit line for '.$invoice->number,
            'unit_price_minor' => $unitPriceMinor,
        ];

        if ($qtyE6 !== null) {
            $lineData['quantity_e6'] = $qtyE6;
        }

        if ($invoiceLine !== null) {
            $lineData['customer_invoice_line_id'] = $invoiceLine->id;
        }

        $payload = array_merge([
            'customer_id' => $invoice->customer_id,
            'customer_invoice_id' => $invoice->id,
            'credit_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'none',
            'lines' => [$lineData],
        ], $overrides);

        $note = $service->create($payload, $this->adminUser->id);
        $service->approve($note->id, $this->adminUser->id);

        return $service->post($note->id, $this->adminUser->id);
    }

    private function createPostedSupplierAdjustmentNote(array $payload): SupplierAdjustmentNote
    {
        /** @var SupplierAdjustmentNoteService $service */
        $service = app(SupplierAdjustmentNoteService::class);

        $note = $service->create($payload, $this->adminUser->id);
        $service->approve($note->id, $this->adminUser->id);

        return $service->post($note->id, $this->adminUser->id);
    }

    private function buildInvoicedDeliveryChain(Product $product, int $deliveredQtyE6, int $unitPriceMinor): array
    {
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 20_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, $deliveredQtyE6, $unitPriceMinor);
        $invoice = $this->postInvoiceForDn($dn, $dnLine, $deliveredQtyE6, $unitPriceMinor);

        return ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice];
    }

    private function stockQuantityE6(Product $product): int
    {
        /** @var StockBalance|null $balance */
        $balance = StockBalance::query()->where('product_id', $product->id)->first();

        return $balance ? (int) $balance->quantity_e6 : 0;
    }

    public function test_pre_invoice_sales_return_restocks_and_reverses_cogs(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 10_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, 3_000_000, 5000);

        $qtyAfterIssue = $this->stockQuantityE6($product);
        $this->assertSame(7_000_000, $qtyAfterIssue);

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000);

        $this->assertEquals('posted', $return->status);
        $this->assertNotNull($return->number);
        $this->assertStringStartsWith('SR-', $return->number);
        $this->assertSame(9_000_000, $this->stockQuantityE6($product));

        $journalQuery = JournalEntry::query()
            ->where('source_type', 'sales_return')
            ->where('source_id', $return->id);
        $this->assertSame(1, $journalQuery->count());

        $journal = $journalQuery->with('lines')->first();
        $inventoryDebit = $journal->lines->where('account_id', $this->mappedAccount('inventory_asset')->id)->sum('debit_minor');
        $cogsCredit = $journal->lines->where('account_id', $this->mappedAccount('cogs')->id)->sum('credit_minor');

        $this->assertEquals(4000, $inventoryDebit);
        $this->assertEquals(4000, $cogsCredit);
        $this->assertEquals('posted', $journal->status);
    }

    public function test_post_invoice_sales_return_links_invoice_line_without_mutating_it(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 4_000_000, 5000);

        $invoiceLine = $invoice->lines->first();
        $headerBefore = [
            'total_minor' => $invoice->total_minor,
            'status' => $invoice->status,
            'journal_entry_id' => $invoice->journal_entry_id,
            'receivable_entry_id' => $invoice->receivable_entry_id,
        ];
        $lineBefore = [
            'quantity_e6' => $invoiceLine->quantity_e6,
            'line_total_minor' => $invoiceLine->line_total_minor,
        ];

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 1_000_000, $invoice);

        $this->assertEquals('posted', $return->status);
        $this->assertSame((string) $invoice->id, (string) $return->customer_invoice_id);
        $this->assertSame((string) $invoiceLine->id, (string) $return->lines->first()->customer_invoice_line_id);

        $invoice->refresh();
        $invoiceLine->refresh();

        $this->assertSame($headerBefore['total_minor'], $invoice->total_minor);
        $this->assertSame($headerBefore['status'], $invoice->status);
        $this->assertSame($headerBefore['journal_entry_id'], $invoice->journal_entry_id);
        $this->assertSame($headerBefore['receivable_entry_id'], $invoice->receivable_entry_id);
        $this->assertSame($lineBefore['quantity_e6'], $invoiceLine->quantity_e6);
        $this->assertSame($lineBefore['line_total_minor'], $invoiceLine->line_total_minor);
    }

    public function test_partial_returns_cannot_exceed_delivered_quantity_cumulatively(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 10_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, 3_000_000, 5000);

        $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000);

        $this->expectException(ValidationException::class);

        $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000);
    }

    public function test_restock_manual_value_posts_variance(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 10_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, 3_000_000, 5000);

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000, null, 'restock_manual_value', 5000);

        $returnLine = SalesReturnLine::query()->where('sales_return_id', $return->id)->firstOrFail();
        $this->assertEquals(5000, $returnLine->stock_value_minor);
        $this->assertEquals(1000, $returnLine->variance_minor);

        $varianceJournal = JournalEntry::query()
            ->where('source_type', 'sales_return_variance')
            ->where('source_id', $return->id)
            ->with('lines')
            ->firstOrFail();

        $varianceDebit = $varianceJournal->lines->where('account_id', $this->mappedAccount('inventory_return_variance')->id)->sum('debit_minor');
        $cogsCredit = $varianceJournal->lines->where('account_id', $this->mappedAccount('cogs')->id)->sum('credit_minor');

        $this->assertEquals(1000, $varianceDebit);
        $this->assertEquals(1000, $cogsCredit);
        $this->assertEquals($varianceJournal->lines->sum('debit_minor'), $varianceJournal->lines->sum('credit_minor'));
    }

    public function test_scrap_return_does_not_increase_saleable_stock_and_posts_scrap_loss(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, 2_000_000, 5000);

        $this->assertSame(3_000_000, $this->stockQuantityE6($product));

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 1_000_000, null, 'scrap_no_restock');

        $this->assertEquals('posted', $return->status);
        $this->assertSame(2_000_000, $this->stockQuantityE6($product));

        $scrapJournal = JournalEntry::query()
            ->where('source_type', 'sales_return')
            ->where('source_id', $return->id)
            ->with('lines')
            ->firstOrFail();

        $scrapDebit = $scrapJournal->lines->where('account_id', $this->mappedAccount('inventory_scrap_loss')->id)->sum('debit_minor');
        $cogsCredit = $scrapJournal->lines->where('account_id', $this->mappedAccount('cogs')->id)->sum('credit_minor');

        $this->assertEquals(2000, $scrapDebit);
        $this->assertEquals(2000, $cogsCredit);
    }

    public function test_sales_return_post_is_idempotent_on_replay(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 10_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, 3_000_000, 5000);

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000);

        $journalsAfterFirstPost = JournalEntry::query()
            ->whereIn('source_type', ['sales_return', 'sales_return_variance'])
            ->where('source_id', $return->id)
            ->count();
        $movementsAfterFirstPost = StockMovementLedger::query()
            ->where('source_type', 'sales_return')
            ->where('source_id', $return->id)
            ->count();

        $replayed = app(SalesReturnService::class)->post($return->id, $this->adminUser->id);

        $this->assertEquals('posted', $replayed->status);
        $this->assertSame($journalsAfterFirstPost, JournalEntry::query()
            ->whereIn('source_type', ['sales_return', 'sales_return_variance'])
            ->where('source_id', $return->id)
            ->count());
        $this->assertSame($movementsAfterFirstPost, StockMovementLedger::query()
            ->where('source_type', 'sales_return')
            ->where('source_id', $return->id)
            ->count());
        $this->assertSame(9_000_000, $this->stockQuantityE6($product));
    }

    public function test_sales_return_requires_confirmed_delivery_note(): void
    {
        $product = $this->makeStockProduct();

        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => self::DATE,
            'currency' => self::CURRENCY,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3_000_000,
                    'unit_price_minor' => 5000,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);
        $draftDn = $dnService->create([
            'sales_order_id' => $confirmedSo->id,
            'delivery_date' => self::DATE,
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'quantity_e6' => 3_000_000,
                ],
            ],
        ], $this->adminUser->id);

        $this->expectException(ValidationException::class);

        app(SalesReturnService::class)->create([
            'customer_id' => $this->customer->id,
            'delivery_note_id' => $draftDn->id,
            'return_date' => self::DATE,
            'lines' => [
                [
                    'delivery_note_line_id' => $draftDn->lines->first()->id,
                    'product_id' => $product->id,
                    'quantity_e6' => 1_000_000,
                    'disposition' => 'restock_original_cost',
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_credit_note_post_dr_sales_returns_cr_ar_control(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 1_000_000, 5000);

        $this->assertEquals('posted', $note->status);
        $this->assertNotNull($note->number);
        $this->assertStringStartsWith('CN-', $note->number);
        $this->assertEquals(5000, $note->subtotal_minor);
        $this->assertEquals(5000, $note->total_minor);

        $journal = JournalEntry::query()->with('lines')->findOrFail($note->journal_entry_id);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(5000, $journal->lines->where('account_id', $this->mappedAccount('sales_returns')->id)->sum('debit_minor'));
        $this->assertEquals(5000, $journal->lines->where('account_id', $this->mappedAccount('ar_control')->id)->sum('credit_minor'));

        /** @var ReceivableEntry $receivableEntry */
        $receivableEntry = ReceivableEntry::query()
            ->where('source_type', 'customer_credit_note')
            ->where('source_id', $note->id)
            ->firstOrFail();
        $this->assertEquals(5000, $receivableEntry->credit_minor);
        $this->assertEquals(0, $receivableEntry->debit_minor);
        $this->assertSame($journal->id, $receivableEntry->journal_entry_id);
    }

    public function test_price_only_credit_note_no_stock_movement(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        $movementCountBefore = StockMovementLedger::count();

        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), null, 3000);

        $this->assertEquals('posted', $note->status);
        $this->assertNull($note->lines->first()->quantity_e6);
        $this->assertEquals(3000, $note->total_minor);
        $this->assertSame($movementCountBefore, StockMovementLedger::count());
        $this->assertSame(0, StockMovementLedger::query()->where('source_type', 'customer_credit_note')->count());
    }

    public function test_tax_bps_integer_math_and_manual_amount_override(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        /** @var CustomerCreditNoteService $service */
        $service = app(CustomerCreditNoteService::class);

        $rateNote = $service->create([
            'customer_id' => $invoice->customer_id,
            'customer_invoice_id' => $invoice->id,
            'credit_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'manual_rate',
            'tax_rate_bps' => 1400,
            'lines' => [
                [
                    'description' => 'Rate based credit',
                    'unit_price_minor' => 10000,
                ],
            ],
        ], $this->adminUser->id);

        $expectedTax = intdiv((10000 * 1400) + 5000, 10000);
        $this->assertEquals(1400, $rateNote->tax_minor);
        $this->assertEquals($expectedTax, $rateNote->tax_minor);
        $this->assertEquals(11400, $rateNote->total_minor);

        $amountNote = $service->create([
            'customer_id' => $invoice->customer_id,
            'customer_invoice_id' => $invoice->id,
            'credit_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'manual_amount',
            'tax_minor_override' => 777,
            'lines' => [
                [
                    'description' => 'Override credit',
                    'unit_price_minor' => 2500,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(777, $amountNote->tax_minor);
        $this->assertEquals(3277, $amountNote->total_minor);
    }

    public function test_credit_note_remains_unallocated_until_manual_action(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 1_000_000, 5000);

        $this->assertNotNull($note->receivable_entry_id);
        $this->assertSame(
            0,
            ReceivableAllocation::query()->where('receivable_entry_id', $note->receivable_entry_id)->count(),
        );
    }

    public function test_manual_settlement_allocates_credit_against_invoice_debit(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 1_000_000, 5000);

        /** @var ReceivableEntry $creditEntry */
        $creditEntry = ReceivableEntry::query()->findOrFail($note->receivable_entry_id);
        $this->assertEquals(0, $creditEntry->debit_minor);
        $this->assertEquals(5000, $creditEntry->credit_minor);

        /** @var ReceivableEntry $invoiceDebitEntry */
        $invoiceDebitEntry = ReceivableEntry::query()->findOrFail($invoice->receivable_entry_id);
        $this->assertEquals(15000, $invoiceDebitEntry->debit_minor);

        $journalCountBefore = JournalEntry::count();

        /** @var ReceivableEntrySettlementService $settlementService */
        $settlementService = app(ReceivableEntrySettlementService::class);
        $settlements = $settlementService->settleCredit(
            $creditEntry->id,
            [[
                'target_receivable_entry_id' => $invoiceDebitEntry->id,
                'amount_minor' => 5000,
            ]],
            $this->adminUser->id
        );

        $this->assertCount(1, $settlements);
        $settlement = $settlements[0];
        $this->assertEquals('active', $settlement->status);
        $this->assertEquals(5000, $settlement->amount_minor);

        // Verification: no GL/journal entries created on settlement
        $this->assertSame($journalCountBefore, JournalEntry::count());

        // Verification: open balances reflect settlement
        $arAging = app(ArAgingReportService::class)->generate(self::DATE, $invoice->customer_id);
        $customerItems = $arAging['customers'][0]['items'];
        $invoiceItem = collect($customerItems)->firstWhere('id', $invoiceDebitEntry->id);
        $this->assertEquals(10000, $invoiceItem['unapplied_minor']);
    }

    public function test_credit_quantity_cannot_exceed_invoiced_quantity(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        $this->expectException(ValidationException::class);

        $this->createPostedCreditNote($invoice, $invoice->lines->first(), 4_000_000, 5000);
    }

    public function test_cancelled_credit_note_excluded_from_cumulative_limits(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);

        /** @var CustomerCreditNoteService $service */
        $service = app(CustomerCreditNoteService::class);

        $cancelledNote = $service->create([
            'customer_id' => $invoice->customer_id,
            'customer_invoice_id' => $invoice->id,
            'credit_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'none',
            'lines' => [
                [
                    'customer_invoice_line_id' => $invoice->lines->first()->id,
                    'description' => 'Cancelled credit',
                    'quantity_e6' => 2_000_000,
                    'unit_price_minor' => 5000,
                ],
            ],
        ], $this->adminUser->id);
        $cancelledNote = $service->cancel($cancelledNote->id, $this->adminUser->id);
        $this->assertEquals('cancelled', $cancelledNote->status);

        $fullNote = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 3_000_000, 5000);

        $this->assertEquals('posted', $fullNote->status);
        $this->assertEquals(15000, $fullNote->total_minor);
    }

    public function test_posted_return_creates_revision_r01(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 5_000_000, 2500);
        $invoiceLine = $invoice->lines->first();

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000, $invoice);
        $note = $this->createPostedCreditNote($invoice, $invoiceLine, 1_000_000, 2500);

        /** @var CustomerInvoiceRevisionService $revisionService */
        $revisionService = app(CustomerInvoiceRevisionService::class);
        $revision = $revisionService->generate($invoice->id, $note->id, $return->id, $this->adminUser->id);

        $this->assertEquals(1, $revision->revision_no);
        $this->assertSame($invoice->number.'-R01', $revision->display_string);
        $this->assertStringEndsWith('-R01', $revision->display_string);
        $this->assertSame((string) $return->id, (string) $revision->sales_return_id);
        $this->assertSame((string) $note->id, (string) $revision->customer_credit_note_id);

        $revisionLine = $revision->lines->first();
        $this->assertEquals(5_000_000, $revisionLine->original_quantity_e6);
        $this->assertEquals(3_000_000, $revisionLine->returned_quantity_e6);
        $this->assertEquals(2_000_000, $revisionLine->net_quantity_e6);
    }

    public function test_second_return_creates_r02_cumulative(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 5_000_000, 2500);
        $invoiceLine = $invoice->lines->first();

        $firstReturn = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000, $invoice);
        $firstNote = $this->createPostedCreditNote($invoice, $invoiceLine, 2_000_000, 2500);

        /** @var CustomerInvoiceRevisionService $revisionService */
        $revisionService = app(CustomerInvoiceRevisionService::class);
        $revisionOne = $revisionService->generate($invoice->id, $firstNote->id, $firstReturn->id, $this->adminUser->id);
        $this->assertEquals(1, $revisionOne->revision_no);

        $secondReturn = $this->createPostedSalesReturn($dn, $dnLine, $product, 1_000_000, $invoice);
        $secondNote = $this->createPostedCreditNote($invoice, $invoiceLine, 1_000_000, 2500);

        $revisionTwo = $revisionService->generate($invoice->id, $secondNote->id, $secondReturn->id, $this->adminUser->id);

        $this->assertEquals(2, $revisionTwo->revision_no);
        $this->assertStringEndsWith('-R02', $revisionTwo->display_string);

        $revisionTwoLine = $revisionTwo->lines->first();
        $this->assertEquals(6_000_000, $revisionTwoLine->returned_quantity_e6);
        $this->assertEquals(0, $revisionTwoLine->net_quantity_e6);
    }

    public function test_draft_or_cancelled_returns_do_not_affect_revision(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 5_000_000, 2500);

        /** @var SalesReturnService $service */
        $service = app(SalesReturnService::class);
        $draftReturn = $service->create([
            'customer_id' => $this->customer->id,
            'delivery_note_id' => $dn->id,
            'customer_invoice_id' => $invoice->id,
            'return_date' => self::DATE,
            'lines' => [
                [
                    'delivery_note_line_id' => $dnLine->id,
                    'product_id' => $product->id,
                    'customer_invoice_line_id' => $invoice->lines->first()->id,
                    'quantity_e6' => 2_000_000,
                    'disposition' => 'restock_original_cost',
                ],
            ],
        ], $this->adminUser->id);
        $this->assertEquals('draft', $draftReturn->status);

        /** @var CustomerInvoiceRevisionService $revisionService */
        $revisionService = app(CustomerInvoiceRevisionService::class);
        $revision = $revisionService->generate($invoice->id, null, $draftReturn->id, $this->adminUser->id);

        $revisionLine = $revision->lines->first();
        $this->assertEquals(0, $revisionLine->returned_quantity_e6);
        $this->assertEquals(5_000_000, $revisionLine->net_quantity_e6);
        $this->assertEquals(0, $revision->credited_total_minor);
        $this->assertEquals(12500, $revision->net_total_minor);
    }

    public function test_revision_lines_show_original_returned_net(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 5_000_000, 2500);

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000, $invoice);

        /** @var CustomerInvoiceRevisionService $revisionService */
        $revisionService = app(CustomerInvoiceRevisionService::class);
        $revision = $revisionService->generate($invoice->id, null, $return->id, $this->adminUser->id);

        $this->assertCount(1, $revision->lines);

        $revisionLine = $revision->lines->first();
        $this->assertSame((string) $invoice->lines->first()->id, (string) $revisionLine->customer_invoice_line_id);
        $this->assertEquals(5_000_000, $revisionLine->original_quantity_e6);
        $this->assertEquals(2_000_000, $revisionLine->returned_quantity_e6);
        $this->assertEquals(3_000_000, $revisionLine->net_quantity_e6);
        $this->assertEquals(2500, $revisionLine->unit_price_minor);
        $this->assertEquals(12500, $revisionLine->original_total_minor);
        $this->assertEquals(0, $revisionLine->credited_subtotal_minor);
        $this->assertEquals(12500, $revisionLine->net_total_minor);
    }

    public function test_original_invoice_untouched_after_revision_generation(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 5_000_000, 2500);
        $invoiceLine = $invoice->lines->first();

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000, $invoice);
        $note = $this->createPostedCreditNote($invoice, $invoiceLine, 1_000_000, 2500);

        $headerBefore = $invoice->refresh()->getAttributes();
        $lineBefore = $invoiceLine->refresh()->getAttributes();

        /** @var CustomerInvoiceRevisionService $revisionService */
        $revisionService = app(CustomerInvoiceRevisionService::class);
        $revisionService->generate($invoice->id, $note->id, $return->id, $this->adminUser->id);

        $this->assertSame($headerBefore, $invoice->refresh()->getAttributes());
        $this->assertSame($lineBefore, $invoiceLine->refresh()->getAttributes());
    }

    public function test_revision_generation_concurrent_unique(): void
    {
        $product = $this->makeStockProduct();
        ['dn' => $dn, 'line' => $dnLine, 'invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 5_000_000, 2500);

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 2_000_000, $invoice);

        /** @var CustomerInvoiceRevisionService $revisionService */
        $revisionService = app(CustomerInvoiceRevisionService::class);

        $revisionOne = $revisionService->generate($invoice->id, null, $return->id, $this->adminUser->id);
        $countAfterFirst = CustomerInvoiceRevision::query()->where('customer_invoice_id', $invoice->id)->count();
        $this->assertSame(1, $countAfterFirst);

        $revisionTwo = $revisionService->generate($invoice->id, null, $return->id, $this->adminUser->id);
        $countAfterSecond = CustomerInvoiceRevision::query()->where('customer_invoice_id', $invoice->id)->count();
        $this->assertSame($countAfterFirst + 1, $countAfterSecond);

        $revisionNumbers = CustomerInvoiceRevision::query()
            ->where('customer_invoice_id', $invoice->id)
            ->orderBy('revision_no')
            ->pluck('revision_no')
            ->all();

        $this->assertSame([1, 2], $revisionNumbers);
        $this->assertSame(2, count(array_unique($revisionNumbers)));
        $this->assertSame(2, CustomerInvoiceRevision::query()
            ->where('customer_invoice_id', $invoice->id)
            ->distinct()
            ->count('display_string'));
    }

    public function test_pre_bill_purchase_return_clears_grni(): void
    {
        $product = $this->makeStockProduct();
        ['gr' => $gr, 'line' => $grLine] = $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);

        $this->assertSame(5_000_000, $this->stockQuantityE6($product));

        $return = $this->createPostedPurchaseReturn($gr, $grLine, $product, 2_000_000);

        $this->assertEquals('posted', $return->status);
        $this->assertNotNull($return->number);
        $this->assertStringStartsWith('PRT-', $return->number);
        $this->assertSame(3_000_000, $this->stockQuantityE6($product));

        $journal = JournalEntry::query()->with('lines')->findOrFail($return->journal_entry_id);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(4000, $journal->lines->where('account_id', $this->mappedAccount('grni_clearing')->id)->sum('debit_minor'));
        $this->assertEquals(4000, $journal->lines->where('account_id', $this->mappedAccount('inventory_asset')->id)->sum('credit_minor'));
    }

    public function test_purchase_return_uses_original_receipt_cost(): void
    {
        $product = $this->makeStockProduct();
        ['gr' => $gr, 'line' => $grLine] = $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);

        $return = $this->createPostedPurchaseReturn($gr, $grLine, $product, 2_000_000);

        $returnLine = $return->lines->first();
        $proportionalCost = intdiv(2_000_000 * 10000, 5_000_000);

        $this->assertEquals($proportionalCost, $returnLine->original_receipt_cost_minor);
        $this->assertEquals(4000, $returnLine->stock_value_minor);
        $this->assertEquals(0, $returnLine->variance_minor);
    }

    public function test_partial_purchase_returns_cannot_exceed_received_qty(): void
    {
        $product = $this->makeStockProduct();
        ['gr' => $gr, 'line' => $grLine] = $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 3_000_000, 2000);

        $this->createPostedPurchaseReturn($gr, $grLine, $product, 2_000_000);

        $this->expectException(ValidationException::class);

        $this->createPostedPurchaseReturn($gr, $grLine, $product, 2_000_000);
    }

    public function test_purchase_return_post_idempotent_replay(): void
    {
        $product = $this->makeStockProduct();
        ['gr' => $gr, 'line' => $grLine] = $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);

        $return = $this->createPostedPurchaseReturn($gr, $grLine, $product, 2_000_000);

        $journalsBefore = JournalEntry::query()->where('source_type', 'purchase_return')->where('source_id', $return->id)->count();
        $movementsBefore = StockMovementLedger::query()->where('source_type', 'purchase_return')->where('source_id', $return->id)->count();

        $replayed = app(PurchaseReturnService::class)->post($return->id, $this->adminUser->id);

        $this->assertEquals('posted', $replayed->status);
        $this->assertSame($journalsBefore, JournalEntry::query()->where('source_type', 'purchase_return')->where('source_id', $return->id)->count());
        $this->assertSame($movementsBefore, StockMovementLedger::query()->where('source_type', 'purchase_return')->where('source_id', $return->id)->count());
        $this->assertSame($return->number, $replayed->number);
        $this->assertSame(3_000_000, $this->stockQuantityE6($product));
    }

    public function test_decrease_payable_posts_ap_debit_and_allowance_credit(): void
    {
        $bill = $this->postServiceProductBill(2_000_000, 5000);

        $note = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'supplier_bill_id' => $bill->id,
            'direction' => 'decrease_payable',
            'adjustment_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'manual_rate',
            'tax_rate_bps' => 1000,
            'lines' => [
                [
                    'supplier_bill_line_id' => $bill->lines->first()->id,
                    'description' => 'Price renegotiation allowance',
                    'quantity_e6' => 1_000_000,
                    'unit_cost_minor' => 8000,
                ],
            ],
        ]);

        $this->assertEquals('posted', $note->status);
        $this->assertNotNull($note->number);
        $this->assertStringStartsWith('SAN-', $note->number);
        $this->assertEquals(8000, $note->subtotal_minor);
        $this->assertEquals(800, $note->tax_minor);
        $this->assertEquals(8800, $note->total_minor);

        $journal = JournalEntry::query()->with('lines')->findOrFail($note->journal_entry_id);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(8000, $journal->lines->where('account_id', $this->mappedAccount('purchase_returns_allowances')->id)->sum('debit_minor'));
        $this->assertEquals(800, $journal->lines->where('account_id', $this->mappedAccount('input_tax_receivable')->id)->sum('debit_minor'));
        $this->assertEquals(8800, $journal->lines->where('account_id', $this->mappedAccount('ap_control')->id)->sum('credit_minor'));

        /** @var PayableEntry $payableEntry */
        $payableEntry = PayableEntry::query()
            ->where('source_type', 'supplier_adjustment_note')
            ->where('source_id', $note->id)
            ->firstOrFail();
        $this->assertEquals(8800, $payableEntry->debit_minor);
        $this->assertEquals(0, $payableEntry->credit_minor);
    }

    public function test_increase_payable_posts_expense_and_ap_credit(): void
    {
        $note = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'direction' => 'increase_payable',
            'adjustment_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'none',
            'lines' => [
                [
                    'description' => 'Missed freight charge',
                    'unit_cost_minor' => 6000,
                ],
            ],
        ]);

        $this->assertEquals('posted', $note->status);
        $this->assertEquals(6000, $note->subtotal_minor);
        $this->assertEquals(6000, $note->total_minor);

        $journal = JournalEntry::query()->with('lines')->findOrFail($note->journal_entry_id);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(6000, $journal->lines->where('account_id', $this->mappedAccount('purchase_expense')->id)->sum('debit_minor'));
        $this->assertEquals(6000, $journal->lines->where('account_id', $this->mappedAccount('ap_control')->id)->sum('credit_minor'));

        /** @var PayableEntry $payableEntry */
        $payableEntry = PayableEntry::query()
            ->where('source_type', 'supplier_adjustment_note')
            ->where('source_id', $note->id)
            ->firstOrFail();
        $this->assertEquals(0, $payableEntry->debit_minor);
        $this->assertEquals(6000, $payableEntry->credit_minor);
    }

    public function test_service_only_supplier_adjustment_without_stock_movement(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);
        $movementsBefore = StockMovementLedger::count();

        $note = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'direction' => 'decrease_payable',
            'adjustment_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'none',
            'lines' => [
                [
                    'description' => 'Service-only adjustment',
                    'unit_cost_minor' => 4500,
                ],
            ],
        ]);

        $this->assertEquals('posted', $note->status);
        $this->assertSame($movementsBefore, StockMovementLedger::count());
        $this->assertSame(5_000_000, $this->stockQuantityE6($product));
    }

    public function test_supplier_adjustment_tax_integer_math(): void
    {
        $rateNote = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'direction' => 'decrease_payable',
            'adjustment_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'manual_rate',
            'tax_rate_bps' => 1400,
            'lines' => [
                [
                    'description' => 'Basis point math check',
                    'unit_cost_minor' => 10000,
                ],
            ],
        ]);

        $this->assertEquals(1400, $rateNote->tax_minor);
        $this->assertEquals(intdiv((10000 * 1400) + 5000, 10000), $rateNote->tax_minor);
        $this->assertEquals(11400, $rateNote->total_minor);

        $amountNote = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'direction' => 'increase_payable',
            'adjustment_date' => self::DATE,
            'currency' => self::CURRENCY,
            'tax_mode' => 'manual_amount',
            'tax_amount_minor' => 999,
            'lines' => [
                [
                    'description' => 'Manual override check',
                    'unit_cost_minor' => 10000,
                ],
            ],
        ]);

        $this->assertEquals(999, $amountNote->tax_minor);
        $this->assertEquals(10999, $amountNote->total_minor);
    }

    public function test_unauthorized_user_denied_routes(): void
    {
        $response = $this->actingAs($this->plainUser)->get('/sales/returns');
        $response->assertStatus(403);
    }

    public function test_attachment_entities_registered(): void
    {
        $entities = config('erp_attachments.entities');

        foreach (['sales_return', 'customer_credit_note', 'purchase_return', 'supplier_adjustment_note', 'customer_invoice_revision'] as $key) {
            $this->assertArrayHasKey($key, $entities);
        }
    }

    public function test_audit_entries_written_via_activity_log(): void
    {
        $product = $this->makeStockProduct();
        $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 10_000_000, 2000);
        ['dn' => $dn, 'line' => $dnLine] = $this->createConfirmedDeliveryNoteWithLine($this->customer, $product, 3_000_000, 5000);

        $return = $this->createPostedSalesReturn($dn, $dnLine, $product, 1_000_000);

        $activityExists = Activity::query()
            ->where('properties->entity_type', 'sales_return')
            ->where('properties->entity_id', $return->id)
            ->where('description', 'like', '%sales_return%')
            ->exists();

        $this->assertTrue($activityExists);

        $postActivity = Activity::query()
            ->where('properties->entity_type', 'sales_return')
            ->where('properties->entity_id', $return->id)
            ->where('description', 'sales_return.post')
            ->first();

        $this->assertNotNull($postActivity);
        $this->assertEquals($this->adminUser->id, $postActivity->causer_id);
    }

    public function test_ar_settlement_over_settlement_and_validation_rules(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);
        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 1_000_000, 5000);

        /** @var ReceivableEntry $creditEntry */
        $creditEntry = ReceivableEntry::query()->findOrFail($note->receivable_entry_id);
        /** @var ReceivableEntry $invoiceDebitEntry */
        $invoiceDebitEntry = ReceivableEntry::query()->findOrFail($invoice->receivable_entry_id);

        /** @var ReceivableEntrySettlementService $settlementService */
        $settlementService = app(ReceivableEntrySettlementService::class);

        // 1. Over-settlement of credit capacity (credit is 5000, requesting 6000)
        try {
            $settlementService->settleCredit(
                $creditEntry->id,
                [['target_receivable_entry_id' => $invoiceDebitEntry->id, 'amount_minor' => 6000]],
                $this->adminUser->id
            );
            $this->fail('Expected ValidationException on over-settlement of credit capacity.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount_minor', $e->errors());
        }

        // 2. Wrong customer rejection
        $otherCustomer = $this->makeCustomer();
        $otherEntry = ReceivableEntry::query()->create([
            'customer_id' => $otherCustomer->id,
            'source_type' => 'customer_invoice',
            'source_id' => (string) Str::uuid(),
            'journal_entry_id' => $invoice->journal_entry_id,
            'financial_period_id' => $this->financialPeriod->id,
            'entry_date' => self::DATE,
            'currency' => self::CURRENCY,
            'debit_minor' => 10000,
            'credit_minor' => 0,
            'created_by' => $this->adminUser->id,
        ]);

        try {
            $settlementService->settleCredit(
                $creditEntry->id,
                [['target_receivable_entry_id' => $otherEntry->id, 'amount_minor' => 1000]],
                $this->adminUser->id
            );
            $this->fail('Expected ValidationException on customer mismatch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_id', $e->errors());
        }
    }

    public function test_ar_settlement_idempotency_and_reversal(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);
        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 1_000_000, 5000);

        $creditEntryId = $note->receivable_entry_id;
        $invoiceDebitId = $invoice->receivable_entry_id;

        /** @var ReceivableEntrySettlementService $settlementService */
        $settlementService = app(ReceivableEntrySettlementService::class);
        $key = 'ar-settlement-idempotency-key-test';

        $settlements1 = $settlementService->settleCredit(
            $creditEntryId,
            [['target_receivable_entry_id' => $invoiceDebitId, 'amount_minor' => 3000]],
            $this->adminUser->id,
            $key
        );

        // Idempotent replay
        $settlements2 = $settlementService->settleCredit(
            $creditEntryId,
            [['target_receivable_entry_id' => $invoiceDebitId, 'amount_minor' => 3000]],
            $this->adminUser->id,
            $key
        );

        $this->assertEquals($settlements1[0]->id, $settlements2[0]->id);
        $this->assertSame(1, ReceivableEntrySettlement::query()->where('source_receivable_entry_id', $creditEntryId)->count());

        // Reversal
        $reversed = $settlementService->reverseSettlement($settlements1[0]->id, 'Customer requested reversal', $this->adminUser->id);
        $this->assertEquals('reversed', $reversed->status);
        $this->assertEquals('Customer requested reversal', $reversed->reversed_reason);
    }

    public function test_ap_settlement_allocates_adjustment_debit_against_bill_credit(): void
    {
        $product = $this->makeStockProduct();
        ['gr' => $gr, 'line' => $grLine] = $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);
        $bill = $this->postBillForGr($gr, $grLine, 5_000_000, 2000);

        // Bill creates PayableEntry credit of 10,000 minor
        $billPayableEntry = PayableEntry::query()->where('source_type', 'supplier_bill')->where('source_id', $bill->id)->firstOrFail();
        $this->assertEquals(10000, $billPayableEntry->credit_minor);

        // Post supplier adjustment note decrease_payable for 4,000 minor
        $san = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'supplier_bill_id' => $bill->id,
            'adjustment_date' => self::DATE,
            'direction' => 'decrease_payable',
            'currency' => self::CURRENCY,
            'tax_mode' => 'none',
            'lines' => [
                [
                    'supplier_bill_line_id' => $bill->lines->first()->id,
                    'description' => 'Quality defect discount',
                    'unit_cost_minor' => 4000,
                ],
            ],
        ]);

        $sanPayableEntry = PayableEntry::query()->where('source_type', 'supplier_adjustment_note')->where('source_id', $san->id)->firstOrFail();
        $this->assertEquals(4000, $sanPayableEntry->debit_minor);

        $journalCountBefore = JournalEntry::count();

        /** @var PayableEntrySettlementService $settlementService */
        $settlementService = app(PayableEntrySettlementService::class);
        $settlements = $settlementService->settleDebit(
            $sanPayableEntry->id,
            [['target_payable_entry_id' => $billPayableEntry->id, 'amount_minor' => 4000]],
            $this->adminUser->id
        );

        $this->assertCount(1, $settlements);
        $this->assertEquals('active', $settlements[0]->status);
        $this->assertSame($journalCountBefore, JournalEntry::count());

        $apAging = app(ApAgingReportService::class)->generate(self::DATE, $this->supplier->id);
        $supplierItems = $apAging['suppliers'][0]['items'];
        $billItem = collect($supplierItems)->firstWhere('id', $billPayableEntry->id);
        $this->assertEquals(6000, $billItem['unapplied_minor']);
    }

    public function test_ap_settlement_over_settlement_and_idempotency_reversal(): void
    {
        $product = $this->makeStockProduct();
        ['gr' => $gr, 'line' => $grLine] = $this->createConfirmedGoodsReceiptWithLine($this->supplier, $product, 5_000_000, 2000);
        $bill = $this->postBillForGr($gr, $grLine, 5_000_000, 2000);

        $san = $this->createPostedSupplierAdjustmentNote([
            'supplier_id' => $this->supplier->id,
            'supplier_bill_id' => $bill->id,
            'adjustment_date' => self::DATE,
            'direction' => 'decrease_payable',
            'currency' => self::CURRENCY,
            'tax_mode' => 'none',
            'lines' => [
                [
                    'description' => 'Quality defect discount',
                    'unit_cost_minor' => 4000,
                ],
            ],
        ]);

        $billPayableEntry = PayableEntry::query()->where('source_type', 'supplier_bill')->where('source_id', $bill->id)->firstOrFail();
        $sanPayableEntry = PayableEntry::query()->where('source_type', 'supplier_adjustment_note')->where('source_id', $san->id)->firstOrFail();

        /** @var PayableEntrySettlementService $settlementService */
        $settlementService = app(PayableEntrySettlementService::class);

        // Over settlement rejection
        try {
            $settlementService->settleDebit(
                $sanPayableEntry->id,
                [['target_payable_entry_id' => $billPayableEntry->id, 'amount_minor' => 5000]],
                $this->adminUser->id
            );
            $this->fail('Expected ValidationException on over settlement of AP debit.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount_minor', $e->errors());
        }

        // Valid settlement with idempotency key
        $key = 'ap-settlement-idempotency-key';
        $settlements1 = $settlementService->settleDebit(
            $sanPayableEntry->id,
            [['target_payable_entry_id' => $billPayableEntry->id, 'amount_minor' => 4000]],
            $this->adminUser->id,
            $key
        );

        $settlements2 = $settlementService->settleDebit(
            $sanPayableEntry->id,
            [['target_payable_entry_id' => $billPayableEntry->id, 'amount_minor' => 4000]],
            $this->adminUser->id,
            $key
        );

        $this->assertEquals($settlements1[0]->id, $settlements2[0]->id);

        // Reversal
        $reversed = $settlementService->reverseSettlement($settlements1[0]->id, 'Mistake in SAN application', $this->adminUser->id);
        $this->assertEquals('reversed', $reversed->status);
    }

    public function test_reports_and_gl_reconciliation_reflect_note_settlements(): void
    {
        $product = $this->makeStockProduct();
        ['invoice' => $invoice] = $this->buildInvoicedDeliveryChain($product, 3_000_000, 5000);
        $note = $this->createPostedCreditNote($invoice, $invoice->lines->first(), 1_000_000, 5000);

        /** @var ArToGlReconciliationReportService $arGlService */
        $arGlService = app(ArToGlReconciliationReportService::class);
        $reconBefore = $arGlService->generate(self::DATE);
        $this->assertTrue($reconBefore['is_reconciled']);

        // Perform settlement
        app(ReceivableEntrySettlementService::class)->settleCredit(
            $note->receivable_entry_id,
            [['target_receivable_entry_id' => $invoice->receivable_entry_id, 'amount_minor' => 5000]],
            $this->adminUser->id
        );

        $reconAfter = $arGlService->generate(self::DATE);
        $this->assertTrue($reconAfter['is_reconciled'], 'AR to GL Reconciliation must remain balanced after manual settlement.');
    }

    public function test_no_tenant_columns_in_new_tables(): void
    {
        $tables = [
            'sales_return',
            'customer_credit_note',
            'customer_invoice_revision',
            'purchase_return',
            'supplier_adjustment_note',
            'receivable_entry_settlement',
            'payable_entry_settlement',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "company_id must not exist in {$table}.");
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "branch_id must not exist in {$table}.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "tenant_id must not exist in {$table}.");
        }
    }

    public function test_no_float_round_in_new_backend_code(): void
    {
        $filesToScan = [
            app_path('Application/Sales/SalesReturnService.php'),
            app_path('Application/Sales/CustomerCreditNoteService.php'),
            app_path('Application/Sales/CustomerInvoiceRevisionService.php'),
            app_path('Application/Purchasing/PurchaseReturnService.php'),
            app_path('Application/Purchasing/SupplierAdjustmentNoteService.php'),
            app_path('Application/Accounting/ReceivableEntrySettlementService.php'),
            app_path('Application/Accounting/PayableEntrySettlementService.php'),
        ];

        $roundPattern = 'ro'.'und(';
        $floatCastPattern = '('.'flo'.'at)';
        $floatKeywordPattern = 'flo'.'at';

        foreach ($filesToScan as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);

            $this->assertFalse(str_contains($content, $roundPattern), "Forbidden round pattern found in {$file}.");
            $this->assertFalse(str_contains($content, $floatCastPattern), "Forbidden float cast pattern found in {$file}.");
            $this->assertFalse(str_contains($content, $floatKeywordPattern), "Forbidden float keyword found in {$file}.");
        }
    }
}
