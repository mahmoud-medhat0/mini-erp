<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Purchasing\SupplierAdjustmentNoteService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Taxes\TaxMasterDataService;
use App\Application\Taxes\TaxPeriodService;
use App\Application\Taxes\TaxReturnService;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase7Slice6TaxFilingTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Customer $customer;

    private Supplier $supplier;

    private Product $product;

    private TaxCode $vat14;

    private string $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        Permission::findOrCreate('taxes.view');
        Permission::findOrCreate('taxes.edit');
        Permission::findOrCreate('taxes.file');

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo(['taxes.view', 'taxes.edit', 'taxes.file']);

        $taxMasterService = app(TaxMasterDataService::class);
        $this->vat14 = $taxMasterService->createTaxCode([
            'code' => 'VAT_FLG_14',
            'name' => ['en' => 'Filing VAT 14%', 'ar' => 'ضريبة 14%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);
        $taxMasterService->createTaxRate([
            'tax_code_id' => $this->vat14->id,
            'rate_bps' => 1400,
            'effective_from' => '2020-01-01',
        ]);

        $this->customer = Customer::query()->first() ?? Customer::create(['code' => 'CUST-FLG-1', 'name' => 'Filing Customer', 'status' => 'active']);
        $this->supplier = Supplier::query()->first() ?? Supplier::create(['code' => 'SUPP-FLG-1', 'name' => 'Filing Supplier', 'status' => 'active']);

        $uom = UnitOfMeasure::query()->first() ?? UnitOfMeasure::create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);
        $this->product = Product::query()->create([
            'code' => 'PROD-FLG-1',
            'name' => ['en' => 'Filing Product', 'ar' => 'منتج الإقرار'],
            'type' => 'service',
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'unit_of_measure_id' => $uom->id,
        ]);

        $mappingService = app(AccountingAccountMappingService::class);
        $this->currency = $mappingService->getAccount('ar_control')->currency;
        Account::query()->update(['currency' => $this->currency]);
    }

    public function test_tax_period_non_overlap_validation(): void
    {
        $periodService = app(TaxPeriodService::class);

        $periodService->createPeriod([
            'period_label' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $this->expectException(ValidationException::class);
        $periodService->createPeriod([
            'period_label' => '2026-01-OVERLAP',
            'start_date' => '2026-01-15',
            'end_date' => '2026-02-15',
        ]);
    }

    public function test_tax_return_draft_totals_match_vat_summary_service(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);
        $invoiceService = app(CustomerInvoiceService::class);

        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-01-10',
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Sales Item',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $invoiceService->approve($invoice->id, $this->adminUser->id);
        $invoiceService->post($invoice->id, $this->adminUser->id);

        $period = $periodService->createPeriod([
            'period_label' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);

        $this->assertEquals('draft', $draft->status);
        $this->assertEquals(1400, $draft->output_tax_minor);
        $this->assertEquals(0, $draft->input_tax_minor);
        $this->assertEquals(1400, $draft->net_payable_minor);
        $this->assertNotNull($draft->snapshot);
    }

    public function test_filing_tax_return_stores_immutable_snapshot_and_locks_period(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);

        $period = $periodService->createPeriod([
            'period_label' => '2026-02',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);
        $filedReturn = $returnService->fileReturn($draft->id, $this->adminUser->id, 'Filing Note');

        $period->refresh();

        $this->assertEquals('filed', $filedReturn->status);
        $this->assertEquals('filed', $period->status);
        $this->assertEquals($filedReturn->number, $period->file_reference);
        $this->assertNotNull($filedReturn->filed_at);
    }

    public function test_filed_period_blocks_customer_invoice_tax_posting(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);
        $invoiceService = app(CustomerInvoiceService::class);

        $period = $periodService->createPeriod([
            'period_label' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);
        $returnService->fileReturn($draft->id, $this->adminUser->id);

        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-03-15',
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Blocked Item',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $invoiceService->approve($invoice->id, $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $invoiceService->post($invoice->id, $this->adminUser->id);
    }

    public function test_filed_period_blocks_supplier_bill_tax_posting(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);
        $billService = app(SupplierBillService::class);

        $period = $periodService->createPeriod([
            'period_label' => '2026-04',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);
        $returnService->fileReturn($draft->id, $this->adminUser->id);

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-04-15',
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Blocked Purchase',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $billService->submit($bill->id, $this->adminUser->id);
        $billService->approve($bill->id, $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $billService->post($bill->id, $this->adminUser->id);
    }

    public function test_filed_period_blocks_customer_credit_note_tax_posting(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);
        $invoiceService = app(CustomerInvoiceService::class);
        $creditNoteService = app(CustomerCreditNoteService::class);

        // Period 2026-06 filed
        $period = $periodService->createPeriod([
            'period_label' => '2026-06',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);
        $returnService->fileReturn($draft->id, $this->adminUser->id);

        // Create invoice in open month (May)
        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-15',
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Sales Item',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $invoiceService->approve($invoice->id, $this->adminUser->id);
        $postedInvoice = $invoiceService->post($invoice->id, $this->adminUser->id);

        // Attempt credit note in filed month (June)
        $creditNote = $creditNoteService->create([
            'customer_id' => $this->customer->id,
            'customer_invoice_id' => $postedInvoice->id,
            'credit_date' => '2026-06-15',
            'currency' => $this->currency,
            'lines' => [
                [
                    'customer_invoice_line_id' => $postedInvoice->lines->first()->id,
                    'description' => 'Blocked Credit Note',
                    'unit_price_minor' => 4000,
                ],
            ],
        ], $this->adminUser->id);
        $creditNoteService->approve($creditNote->id, $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $creditNoteService->post($creditNote->id, $this->adminUser->id);
    }

    public function test_filed_period_blocks_supplier_adjustment_note_tax_posting(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);
        $adjustmentService = app(SupplierAdjustmentNoteService::class);

        $period = $periodService->createPeriod([
            'period_label' => '2026-07',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);
        $returnService->fileReturn($draft->id, $this->adminUser->id);

        $note = $adjustmentService->create([
            'supplier_id' => $this->supplier->id,
            'adjustment_date' => '2026-07-15',
            'currency' => $this->currency,
            'direction' => 'decrease_payable',
            'tax_mode' => 'none',
            'lines' => [
                [
                    'description' => 'Blocked Adjustment',
                    'line_subtotal_minor' => 5000,
                ],
            ],
        ], $this->adminUser->id);
        $adjustmentService->approve($note->id, $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $adjustmentService->post($note->id, $this->adminUser->id);
    }

    public function test_idempotent_filing_action(): void
    {
        $periodService = app(TaxPeriodService::class);
        $returnService = app(TaxReturnService::class);

        $period = $periodService->createPeriod([
            'period_label' => '2026-05',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);

        $draft = $returnService->generateDraftReturn($period->id, $this->adminUser->id);

        $firstFiled = $returnService->fileReturn($draft->id, $this->adminUser->id);
        $secondFiled = $returnService->fileReturn($draft->id, $this->adminUser->id);

        $this->assertEquals($firstFiled->id, $secondFiled->id);
        $this->assertEquals('filed', $secondFiled->status);
    }

    public function test_permissions_for_tax_period_view_edit_and_file(): void
    {
        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser);

        $this->get('/taxes/periods')->assertStatus(403);

        $this->actingAs($this->adminUser);

        $this->get('/taxes/periods')->assertStatus(200);
    }
}
