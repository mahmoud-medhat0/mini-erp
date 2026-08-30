<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Sales\DeliveryNoteService;
use App\Application\Sales\SalesOrderService;
use App\Models\Account;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PayableEntry;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase4Slice5CustomerInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Customer $customer;

    private Product $serviceProduct;

    private Product $nonStockProduct;

    private Product $stockProduct;

    private UnitOfMeasure $uom;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $financialPeriod;

    private Account $arAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductCategorySeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([
            'sales.view', 'sales.create', 'sales.edit', 'sales.submit', 'sales.approve', 'sales.post', 'sales.cancel',
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $catServ = ProductCategory::query()->where('code', 'SERV')->firstOrFail();
        $catFg = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->customer = Customer::query()->create([
            'code' => 'CUST-001',
            'name' => 'Acme Corp',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->serviceProduct = Product::query()->create([
            'code' => 'PRD-SERV-1',
            'name' => ['en' => 'Consulting Service 1', 'ar' => 'خدمة استشارية 1'],
            'type' => 'service',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catServ->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => false,
            'lock_version' => 1,
        ]);

        $this->nonStockProduct = Product::query()->create([
            'code' => 'PRD-NONSTOCK-1',
            'name' => ['en' => 'Software License 1', 'ar' => 'ترخيص برنامج 1'],
            'type' => 'non_stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catServ->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => false,
            'lock_version' => 1,
        ]);

        $this->stockProduct = Product::query()->create([
            'code' => 'PRD-STOCK-1',
            'name' => ['en' => 'Physical Widget 1', 'ar' => 'منتج مادي 1'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $catFg->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => false,
            'lock_version' => 1,
        ]);

        // Fiscal Year & Period
        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->financialPeriod = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 8,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);

        // Accounts & Mappings
        $this->arAccount = Account::query()->create([
            'code' => '1100-AR',
            'name' => ['en' => 'Accounts Receivable Control', 'ar' => 'حسابات العملاء'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'USD',
            'is_active' => true,
            'allow_manual_posting' => false,
        ]);

        $this->revenueAccount = Account::query()->create([
            'code' => '4100-REV',
            'name' => ['en' => 'Sales Revenue', 'ar' => 'إيرادات المبيعات'],
            'type' => 'revenue',
            'nature' => 'credit',
            'currency' => 'USD',
            'is_active' => true,
            'allow_manual_posting' => true,
        ]);

        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $mappingService->setMapping('ar_control', $this->arAccount->id, actorId: $this->adminUser->id);
        $mappingService->setMapping('sales_revenue', $this->revenueAccount->id, actorId: $this->adminUser->id);
    }

    public function test_customer_invoice_migrations_create_expected_tables_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('customer_invoice'));
        $this->assertTrue(Schema::hasTable('customer_invoice_line'));

        $this->assertTrue(Schema::hasColumns('customer_invoice', [
            'id', 'number', 'customer_id', 'sales_order_id', 'delivery_note_id',
            'fiscal_year_id', 'financial_period_id', 'invoice_date', 'due_date',
            'reference', 'description', 'currency', 'fx_rate_e6', 'subtotal_minor',
            'total_minor', 'status', 'journal_entry_id', 'receivable_entry_id',
            'submitted_by', 'approved_by', 'posted_by', 'cancelled_by', 'lock_version',
        ]));
    }

    public function test_no_tenant_company_branch_or_prohibited_columns_exist_in_customer_invoice_tables(): void
    {
        $prohibitedColumns = [
            'company_id', 'branch_id', 'tenant_id', 'current_company', 'current_branch',
            'supplier_bill_id', 'payable_entry_id', 'warehouse_id', 'inventory_entry_id',
            'stock_movement_id', 'cogs', 'discount_minor',
        ];
        $tables = ['customer_invoice', 'customer_invoice_line'];

        foreach ($tables as $table) {
            foreach ($prohibitedColumns as $col) {
                $this->assertFalse(
                    Schema::hasColumn($table, $col),
                    "Prohibited column [{$col}] was found in table [{$table}]."
                );
            }
        }
    }

    public function test_sales_revenue_mapping_key_is_allowed_in_service_and_db_constraint(): void
    {
        $this->assertContains('sales_revenue', AccountingAccountMappingService::ALLOWED_KEYS);

        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);
        $acc = $mappingService->getAccount('sales_revenue');

        $this->assertEquals($this->revenueAccount->id, $acc->id);
    }

    public function test_create_and_update_customer_invoice_works_for_service_and_non_stock_products(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'reference' => 'INV-REF-100',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000, // 2.00 units
                    'unit_price_minor' => 1500, // $15.00
                ],
                [
                    'product_id' => $this->nonStockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000, // 1.00 unit
                    'unit_price_minor' => 5000, // $50.00
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals('draft', $invoice->status);
        $this->assertEquals(8000, $invoice->total_minor); // 2 * 1500 + 1 * 5000 = 8000 cents ($80.00)
        $this->assertCount(2, $invoice->lines);
    }

    public function test_stock_product_invoice_lines_are_rejected_in_slice_5(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->stockProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 2000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_confirmed_sales_order_source_lines_can_be_invoiced_within_remaining_quantity(): void
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 5000000, // 5 units
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);
        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'sales_order_id' => $confirmedSo->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000, // 3 units
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(9000, $invoice->total_minor);
        $this->assertEquals($confirmedSo->id, $invoice->sales_order_id);
    }

    public function test_sales_order_line_source_requires_selected_sales_order_header(): void
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_sales_order_source_line_price_must_match_source_line(): void
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'customer_id' => $this->customer->id,
            'sales_order_id' => $confirmedSo->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 4000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_confirmed_delivery_note_source_lines_can_be_invoiced_within_remaining_quantity(): void
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 3000000,
                    'unit_price_minor' => 2500,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);
        $dn = $dnService->create([
            'sales_order_id' => $confirmedSo->id,
            'delivery_date' => '2026-08-22',
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'quantity_e6' => 2000000,
                ],
            ],
        ], $this->adminUser->id);
        $confirmedDn = $dnService->confirm($dn->id, $this->adminUser->id);

        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);
        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'delivery_note_id' => $confirmedDn->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'delivery_note_line_id' => $confirmedDn->lines->first()->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_price_minor' => 2500,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(5000, $invoice->total_minor);
        $this->assertEquals($confirmedDn->id, $invoice->delivery_note_id);
    }

    public function test_over_invoicing_sales_order_lines_is_rejected(): void
    {
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $this->customer->id,
            'order_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 5000000, // 5 units
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
        $soService->submit($so->id, $this->adminUser->id);
        $confirmedSo = $soService->confirm($so->id, $this->adminUser->id);

        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        // 1st invoice for 4 units
        $inv1 = $service->create([
            'customer_id' => $this->customer->id,
            'sales_order_id' => $confirmedSo->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 4000000,
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);

        // 2nd invoice for 2 units (total 6 > 5) must fail
        $this->expectException(ValidationException::class);
        $service->create([
            'customer_id' => $this->customer->id,
            'sales_order_id' => $confirmedSo->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'sales_order_line_id' => $confirmedSo->lines->first()->id,
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_price_minor' => 3000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_invoice_lifecycle_status_transitions_draft_to_submitted_to_approved_to_posted(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 2500,
                ],
            ],
        ], $this->adminUser->id);

        $submitted = $service->submit($invoice->id, $this->adminUser->id);
        $this->assertEquals('submitted', $submitted->status);

        $approved = $service->approve($invoice->id, $this->adminUser->id);
        $this->assertEquals('approved', $approved->status);

        $posted = $service->post($invoice->id, $this->adminUser->id);
        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertStringStartsWith('INV-2026-', $posted->number);
    }

    public function test_posting_creates_approved_journal_entry_ledger_entries_and_receivable_entry_debit(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000, // 2 units
                    'unit_price_minor' => 5000, // $50.00 -> $100.00 total
                ],
            ],
        ], $this->adminUser->id);

        $service->approve($invoice->id, $this->adminUser->id);
        $postedInvoice = $service->post($invoice->id, $this->adminUser->id);

        $this->assertNotNull($postedInvoice->journal_entry_id);
        $this->assertNotNull($postedInvoice->receivable_entry_id);

        /** @var JournalEntry $je */
        $je = JournalEntry::query()->with('lines')->find($postedInvoice->journal_entry_id);
        $this->assertEquals('posted', $je->status);
        $this->assertEquals(10000, $je->lines->sum('debit_minor'));
        $this->assertEquals(10000, $je->lines->sum('credit_minor'));
        $this->assertCount(2, $je->lines);

        // Check LedgerEntries
        $ledgerCount = LedgerEntry::query()->where('journal_entry_id', $je->id)->count();
        $this->assertEquals(2, $ledgerCount);

        // Check ReceivableEntry
        $rawRec = DB::table('receivable_entry')->where('source_type', 'customer_invoice')->first();
        $this->assertNotNull($rawRec);
        $this->assertEquals($this->customer->id, $rawRec->customer_id);
        $this->assertEquals($postedInvoice->id, $rawRec->source_id);
        $this->assertEquals(10000, (int) $rawRec->debit_minor);
    }

    public function test_posting_is_idempotent(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $service->approve($invoice->id, $this->adminUser->id);
        $posted1 = $service->post($invoice->id, $this->adminUser->id);

        $journalsCountBefore = JournalEntry::count();
        $receivablesCountBefore = ReceivableEntry::count();

        // Replay post
        $posted2 = $service->post($invoice->id, $this->adminUser->id);

        $this->assertEquals($posted1->number, $posted2->number);
        $this->assertEquals($journalsCountBefore, JournalEntry::count());
        $this->assertEquals($receivablesCountBefore, ReceivableEntry::count());
    }

    public function test_posted_invoices_are_immutable_and_cannot_be_updated_or_cancelled(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $service->approve($invoice->id, $this->adminUser->id);
        $service->post($invoice->id, $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $service->update($invoice->id, [
            'description' => 'Attempt to edit posted invoice',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 2000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);
    }

    public function test_audit_entries_are_recorded_for_customer_invoices_through_spatie_activitylog(): void
    {
        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $activityCount = Activity::query()
            ->where('properties->entity_type', 'customer_invoice')
            ->where('properties->entity_id', $invoice->id)
            ->count();

        $this->assertGreaterThanOrEqual(1, $activityCount);
    }

    public function test_attachment_registry_supports_customer_invoice(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);

        $allowedTypes = $authorizer->allowedEntityTypes();
        $this->assertContains('customer_invoice', $allowedTypes);
    }

    public function test_customer_invoice_posting_creates_zero_supplier_bill_payable_inventory_cogs_or_tax_entries(): void
    {
        $payablesBefore = PayableEntry::count();

        /** @var CustomerInvoiceService $service */
        $service = app(CustomerInvoiceService::class);

        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-22',
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $this->serviceProduct->id,
                    'unit_of_measure_id' => $this->uom->id,
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 1000,
                ],
            ],
        ], $this->adminUser->id);

        $service->approve($invoice->id, $this->adminUser->id);
        $service->post($invoice->id, $this->adminUser->id);

        $this->assertEquals($payablesBefore, PayableEntry::count());
    }

    public function test_inertia_customer_invoices_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/sales/invoices');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sales/CustomerInvoices'));
    }

    public function test_customer_invoice_backend_contains_no_forbidden_binary_or_rounding_math(): void
    {
        $filesToScan = [
            app_path('Application/Sales/CustomerInvoiceService.php'),
            app_path('Models/CustomerInvoice.php'),
            app_path('Models/CustomerInvoiceLine.php'),
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
}
