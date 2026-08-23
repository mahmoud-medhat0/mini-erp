<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxMasterDataService;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase7Slice3SalesOutputVatTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Customer $customer;

    private Product $product;

    private TaxCode $stdTaxCode;

    private TaxCode $zeroTaxCode;

    private TaxCode $exemptTaxCode;

    private CustomerInvoiceService $invoiceService;

    private CustomerCreditNoteService $creditNoteService;

    private TaxMasterDataService $taxMasterService;

    private TaxCalculationService $taxCalcService;

    private AccountingAccountMappingService $mappingService;

    private PeriodService $periodService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Permission::findOrCreate('sales.view');
        Permission::findOrCreate('sales.create');
        Permission::findOrCreate('sales.post');
        Permission::findOrCreate('taxes.view');

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo(['sales.view', 'sales.create', 'sales.post', 'taxes.view']);

        $this->invoiceService = app(CustomerInvoiceService::class);
        $this->creditNoteService = app(CustomerCreditNoteService::class);
        $this->taxMasterService = app(TaxMasterDataService::class);
        $this->taxCalcService = app(TaxCalculationService::class);
        $this->mappingService = app(AccountingAccountMappingService::class);
        $this->periodService = app(PeriodService::class);

        $currency = $this->mappingService->getAccount('ar_control')->currency;
        Account::query()->update(['currency' => $currency]);

        $this->customer = Customer::query()->first() ?? Customer::create([
            'code' => 'CUST-VAT-TEST',
            'name' => 'VAT Test Customer',
            'status' => 'active',
        ]);
        $uom = UnitOfMeasure::query()->first() ?? UnitOfMeasure::create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);

        $this->product = Product::query()->create([
            'code' => 'PROD-VAT-TEST',
            'name' => ['en' => 'VAT Test Product', 'ar' => 'منتج ضريبي'],
            'type' => 'service',
            'status' => 'active',
            'is_sales_enabled' => true,
            'unit_of_measure_id' => $uom->id,
        ]);

        // Setup 14% standard tax code
        $this->stdTaxCode = TaxCode::query()->where('code', 'VAT_STD_14')->first() ?? $this->taxMasterService->createTaxCode([
            'code' => 'VAT_STD_14',
            'name' => ['en' => 'Standard VAT 14%', 'ar' => 'ضريبة 14%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);
        if ($this->stdTaxCode->rates()->count() === 0) {
            $this->taxMasterService->createTaxRate([
                'tax_code_id' => $this->stdTaxCode->id,
                'rate_bps' => 1400,
                'effective_from' => '2020-01-01',
            ]);
        }

        // Setup 0% zero-rated tax code
        $this->zeroTaxCode = TaxCode::query()->where('code', 'VAT_ZERO')->first() ?? $this->taxMasterService->createTaxCode([
            'code' => 'VAT_ZERO',
            'name' => ['en' => 'Zero Rate 0%', 'ar' => 'نسبة صفري 0%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);

        // Setup exempt tax code
        $this->exemptTaxCode = TaxCode::query()->where('code', 'EXEMPT')->first() ?? $this->taxMasterService->createTaxCode([
            'code' => 'EXEMPT',
            'name' => ['en' => 'Exempt VAT', 'ar' => 'معفاة'],
            'calculation_mode' => 'exempt',
            'recoverability_mode' => 'none',
        ]);
    }

    public function test_sales_tax_columns_exist_without_unsupported_scope(): void
    {
        $this->assertTrue(Schema::hasColumn('customer_invoice', 'tax_amount_minor'));
        $this->assertTrue(Schema::hasColumn('customer_invoice_line', 'tax_code_id'));
        $this->assertTrue(Schema::hasColumn('customer_invoice_line', 'tax_rate_bps'));
        $this->assertTrue(Schema::hasColumn('customer_invoice_line', 'tax_amount_minor'));
        $this->assertTrue(Schema::hasColumn('customer_invoice_line', 'gross_amount_minor'));

        $this->assertFalse(Schema::hasColumn('customer_invoice', 'company_id'));
        $this->assertFalse(Schema::hasColumn('customer_invoice_line', 'company_id'));
    }

    public function test_invoice_creation_calculates_exact_integer_tax(): void
    {
        $currency = $this->mappingService->getAccount('ar_control')->currency;

        $invoice = $this->invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'currency' => $currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Standard Taxable Line',
                    'quantity_e6' => 1000000, // 1 unit
                    'unit_price_minor' => 10000, // $100.00
                    'tax_code_id' => $this->stdTaxCode->id,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(10000, $invoice->subtotal_minor);
        $this->assertEquals(1400, $invoice->tax_amount_minor);
        $this->assertEquals(11400, $invoice->total_minor);

        $line = $invoice->lines->first();
        $this->assertEquals(1400, $line->tax_rate_bps);
        $this->assertEquals(1400, $line->tax_amount_minor);
        $this->assertEquals(11400, $line->gross_amount_minor);
    }

    public function test_invoice_posting_creates_balanced_jv_with_output_tax(): void
    {
        $currency = $this->mappingService->getAccount('ar_control')->currency;

        $invoice = $this->invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'currency' => $currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Standard Taxable Line',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->stdTaxCode->id,
                ],
            ],
        ], $this->adminUser->id);

        $this->invoiceService->approve($invoice->id, $this->adminUser->id);
        $posted = $this->invoiceService->post($invoice->id, $this->adminUser->id);

        $journalEntry = $posted->journalEntry;
        $this->assertNotNull($journalEntry);

        $outputTaxAccount = $this->mappingService->getAccount('output_tax_payable');

        // Dr AR Control = 11400 (Gross)
        $arLine = $journalEntry->lines()->where('account_id', $this->mappingService->getAccount('ar_control')->id)->first();
        $this->assertEquals(11400, $arLine->debit_minor);

        // Cr Sales Revenue = 10000 (Net)
        $revLine = $journalEntry->lines()->where('account_id', $this->mappingService->getAccount('sales_revenue')->id)->first();
        $this->assertEquals(10000, $revLine->credit_minor);

        // Cr Output Tax Payable = 1400 (Tax)
        $taxLine = $journalEntry->lines()->where('account_id', $outputTaxAccount->id)->first();
        $this->assertEquals(1400, $taxLine->credit_minor);
    }

    public function test_linked_credit_note_uses_original_tax_snapshot_after_master_rate_change(): void
    {
        $currency = $this->mappingService->getAccount('ar_control')->currency;

        // 1. Create and post invoice with 14% rate
        $invoice = $this->invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'currency' => $currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Original Invoice Line',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->stdTaxCode->id,
                ],
            ],
        ], $this->adminUser->id);

        $this->invoiceService->approve($invoice->id, $this->adminUser->id);
        $postedInvoice = $this->invoiceService->post($invoice->id, $this->adminUser->id);

        // 2. Change master tax rate on standard tax code to 20% (2000 bps)
        $this->taxMasterService->createTaxRate([
            'tax_code_id' => $this->stdTaxCode->id,
            'rate_bps' => 2000,
            'effective_from' => '2020-01-01',
        ]);

        // 3. Create credit note linked to original invoice line
        $creditNote = $this->creditNoteService->create([
            'customer_id' => $this->customer->id,
            'customer_invoice_id' => $postedInvoice->id,
            'credit_date' => now()->format('Y-m-d'),
            'currency' => $currency,
            'lines' => [
                [
                    'customer_invoice_line_id' => $postedInvoice->lines->first()->id,
                    'description' => 'Reversal of original line',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                ],
            ],
        ], $this->adminUser->id);

        // Credit note tax MUST equal original 1400 (14%), NOT 2000 (20%)
        $this->assertEquals(1400, $creditNote->tax_minor);
        $this->assertEquals(11400, $creditNote->total_minor);

        $this->creditNoteService->approve($creditNote->id, $this->adminUser->id);
        $postedCN = $this->creditNoteService->post($creditNote->id, $this->adminUser->id);

        $outputTaxAccount = $this->mappingService->getAccount('output_tax_payable');
        $taxLine = $postedCN->journalEntry->lines()->where('account_id', $outputTaxAccount->id)->first();
        $this->assertEquals(1400, $taxLine->debit_minor);
    }

    public function test_mixed_tax_lines_produce_exact_line_summed_totals(): void
    {
        $currency = $this->mappingService->getAccount('ar_control')->currency;

        $invoice = $this->invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'currency' => $currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Line 1: 14% Standard ($100.00 base -> $14.00 tax)',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->stdTaxCode->id,
                ],
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Line 2: 0% Zero-Rated ($50.00 base -> $0.00 tax)',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 5000,
                    'tax_code_id' => $this->zeroTaxCode->id,
                ],
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Line 3: Exempt ($30.00 base -> $0.00 tax)',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 3000,
                    'tax_code_id' => $this->exemptTaxCode->id,
                ],
            ],
        ], $this->adminUser->id);

        $this->assertEquals(18000, $invoice->subtotal_minor);
        $this->assertEquals(1400, $invoice->tax_amount_minor);
        $this->assertEquals(19400, $invoice->total_minor);
    }
}
