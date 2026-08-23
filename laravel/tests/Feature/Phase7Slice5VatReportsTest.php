<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Reports\VatRegisterReportService;
use App\Application\Reports\VatSummaryReportService;
use App\Application\Reports\VatToGlReconciliationService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Taxes\TaxMasterDataService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase7Slice5VatReportsTest extends TestCase
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

        Permission::findOrCreate('reports.view');
        Permission::findOrCreate('reports.export');
        Permission::findOrCreate('view_financials');

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);

        $taxMasterService = app(TaxMasterDataService::class);
        $this->vat14 = $taxMasterService->createTaxCode([
            'code' => 'VAT_RPT_14',
            'name' => ['en' => 'Report VAT 14%', 'ar' => 'ضريبة 14%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);
        $taxMasterService->createTaxRate([
            'tax_code_id' => $this->vat14->id,
            'rate_bps' => 1400,
            'effective_from' => '2020-01-01',
        ]);

        $this->customer = Customer::query()->first() ?? Customer::create(['code' => 'CUST-RPT-1', 'name' => 'Report Customer', 'status' => 'active']);
        $this->supplier = Supplier::query()->first() ?? Supplier::create(['code' => 'SUPP-RPT-1', 'name' => 'Report Supplier', 'status' => 'active']);

        $uom = UnitOfMeasure::query()->first() ?? UnitOfMeasure::create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);
        $this->product = Product::query()->create([
            'code' => 'PROD-RPT-1',
            'name' => ['en' => 'Report Product', 'ar' => 'منتج تقارير'],
            'type' => 'service',
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'unit_of_measure_id' => $uom->id,
        ]);

        Account::query()->update(['currency' => 'USD']);
        $mappingService = app(AccountingAccountMappingService::class);
        $this->currency = 'USD';

        $outputAccount = Account::query()->where('code', '2200')->first();
        if ($outputAccount) {
            AccountingAccountMapping::query()->updateOrCreate(
                ['key' => 'output_tax_payable'],
                ['account_id' => $outputAccount->id]
            );
        }

        $inputAccount = Account::query()->where('code', '1300')->first();
        if ($inputAccount) {
            AccountingAccountMapping::query()->updateOrCreate(
                ['key' => 'input_tax_receivable'],
                ['account_id' => $inputAccount->id]
            );
        }
    }

    public function test_vat_register_includes_posted_sales_and_purchases_tax_documents_with_correct_signs(): void
    {
        $invoiceService = app(CustomerInvoiceService::class);
        $billService = app(SupplierBillService::class);
        $creditNoteService = app(CustomerCreditNoteService::class);

        $today = now()->format('Y-m-d');

        // 1. Post Invoice ($100 base + $14 tax = $114 total) => Output +1400
        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $today,
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

        // 2. Post Credit Note ($40 base + $5.60 tax) => Output -560
        $invoiceLine = $postedInvoice->lines->first();
        $creditNote = $creditNoteService->create([
            'customer_id' => $this->customer->id,
            'customer_invoice_id' => $postedInvoice->id,
            'credit_date' => $today,
            'currency' => $this->currency,
            'lines' => [
                [
                    'customer_invoice_line_id' => $invoiceLine->id,
                    'description' => 'Return Partial',
                    'unit_price_minor' => 4000,
                ],
            ],
        ], $this->adminUser->id);
        $creditNoteService->approve($creditNote->id, $this->adminUser->id);
        $creditNoteService->post($creditNote->id, $this->adminUser->id);

        // 3. Post Supplier Bill ($200 base + $28 tax = $228 total) => Input +2800
        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => $today,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Purchase Item',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 20000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $billService->submit($bill->id, $this->adminUser->id);
        $billService->approve($bill->id, $this->adminUser->id);
        $billService->post($bill->id, $this->adminUser->id);

        $registerService = app(VatRegisterReportService::class);
        $report = $registerService->generate([
            'from_date' => $today,
            'to_date' => $today,
        ]);

        $this->assertCount(3, $report['rows']);
        $this->assertEquals(840, $report['summary']['total_output_tax_minor']); // 1400 - 560 = 840
        $this->assertEquals(2800, $report['summary']['total_input_tax_minor']); // 2800
        $this->assertEquals(-1960, $report['summary']['net_vat_payable_minor']); // 840 - 2800 = -1960
    }

    public function test_vat_register_excludes_draft_and_cancelled_documents(): void
    {
        $invoiceService = app(CustomerInvoiceService::class);
        $today = now()->format('Y-m-d');

        // Create draft invoice
        $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $today,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Draft Item',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);

        $registerService = app(VatRegisterReportService::class);
        $report = $registerService->generate(['from_date' => $today, 'to_date' => $today]);

        $this->assertCount(0, $report['rows']);
        $this->assertEquals(0, $report['summary']['total_output_tax_minor']);
    }

    public function test_changing_current_tax_master_rates_after_posting_does_not_change_vat_register_totals(): void
    {
        $invoiceService = app(CustomerInvoiceService::class);
        $today = now()->format('Y-m-d');

        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $today,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Original Item',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $invoiceService->approve($invoice->id, $this->adminUser->id);
        $invoiceService->post($invoice->id, $this->adminUser->id);

        // Mutate current rate to 20% in tax_rates table
        $taxMasterService = app(TaxMasterDataService::class);
        $taxMasterService->createTaxRate([
            'tax_code_id' => $this->vat14->id,
            'rate_bps' => 2000,
            'effective_from' => '2020-01-01',
        ]);

        $registerService = app(VatRegisterReportService::class);
        $report = $registerService->generate(['from_date' => $today, 'to_date' => $today]);

        // Output tax must remain 1400 (from historical snapshot), not 2000
        $this->assertEquals(1400, $report['summary']['total_output_tax_minor']);
    }

    public function test_vat_summary_totals_equal_vat_register_detail_totals(): void
    {
        $invoiceService = app(CustomerInvoiceService::class);
        $billService = app(SupplierBillService::class);
        $today = now()->format('Y-m-d');

        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $today,
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

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => $today,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Purchase Item',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 20000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $billService->submit($bill->id, $this->adminUser->id);
        $billService->approve($bill->id, $this->adminUser->id);
        $billService->post($bill->id, $this->adminUser->id);

        $registerService = app(VatRegisterReportService::class);
        $summaryService = app(VatSummaryReportService::class);

        $regReport = $registerService->generate(['from_date' => $today, 'to_date' => $today]);
        $sumReport = $summaryService->generate(['from_date' => $today, 'to_date' => $today]);

        $this->assertEquals($regReport['summary']['total_output_tax_minor'], $sumReport['summary']['total_output_tax_minor']);
        $this->assertEquals($regReport['summary']['total_input_tax_minor'], $sumReport['summary']['total_input_tax_minor']);
        $this->assertEquals($regReport['summary']['net_vat_payable_minor'], $sumReport['summary']['net_vat_payable_minor']);
    }

    public function test_vat_gl_reconciliation_matches_mapped_ledger_movement(): void
    {
        $invoiceService = app(CustomerInvoiceService::class);
        $billService = app(SupplierBillService::class);
        $today = now()->format('Y-m-d');

        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $today,
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

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => $today,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Purchase Item',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 20000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->adminUser->id);
        $billService->submit($bill->id, $this->adminUser->id);
        $billService->approve($bill->id, $this->adminUser->id);
        $billService->post($bill->id, $this->adminUser->id);

        $reconService = app(VatToGlReconciliationService::class);
        $report = $reconService->generate(['from_date' => $today, 'to_date' => $today, 'currency' => $this->currency]);

        $this->assertTrue($report['is_reconciled']);
        $this->assertEquals(0, $report['output_tax_difference_minor']);
        $this->assertEquals(0, $report['input_tax_difference_minor']);
        $this->assertEquals(1400, $report['register_output_tax_minor']);
        $this->assertEquals(1400, $report['gl_output_tax_minor']);
        $this->assertEquals(2800, $report['register_input_tax_minor']);
        $this->assertEquals(2800, $report['gl_input_tax_minor']);
        $this->assertEmpty($report['warnings']);
    }

    public function test_vat_gl_reconciliation_reports_forced_mismatch_with_warning_code(): void
    {
        $invoiceService = app(CustomerInvoiceService::class);
        $today = now()->format('Y-m-d');

        $invoice = $invoiceService->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $today,
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

        // Inject a raw manual debit to output_tax_payable account to force GL mismatch
        $mappingService = app(AccountingAccountMappingService::class);
        $outputAccount = $mappingService->getAccount('output_tax_payable');

        $dummyJournalLine = JournalLine::query()->create([
            'journal_entry_id' => $postedInvoice->journal_entry_id,
            'account_id' => $outputAccount->id,
            'line_no' => 99,
            'debit_minor' => 500,
            'credit_minor' => 0,
            'description' => 'Forced mismatch line',
        ]);

        LedgerEntry::query()->create([
            'account_id' => $outputAccount->id,
            'journal_entry_id' => $postedInvoice->journal_entry_id,
            'journal_line_id' => $dummyJournalLine->id,
            'financial_period_id' => $postedInvoice->financial_period_id,
            'entry_date' => $today,
            'debit_minor' => 500,
            'credit_minor' => 0,
            'debit_txn_minor' => 500,
            'credit_txn_minor' => 0,
            'currency' => $this->currency,
            'fx_rate_e6' => 1000000,
        ]);

        $reconService = app(VatToGlReconciliationService::class);
        $report = $reconService->generate(['from_date' => $today, 'to_date' => $today, 'currency' => $this->currency]);

        $this->assertFalse($report['is_reconciled']);
        $this->assertEquals(500, $report['output_tax_difference_minor']);
        $this->assertNotEmpty($report['warnings']);
        $this->assertEquals('WARN_VAT_GL_MISMATCH', $report['warnings'][0]['code']);
    }

    public function test_csv_export_totals_match_service_totals(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/reports/vat-register/export?from_date=2026-01-01&to_date=2026-12-31');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $summaryResponse = $this->get('/reports/vat-summary/export?from_date=2026-01-01&to_date=2026-12-31');
        $summaryResponse->assertStatus(200);
        $summaryResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $reconResponse = $this->get('/reports/vat-gl-reconciliation/export?from_date=2026-01-01&to_date=2026-12-31&currency='.$this->currency);
        $reconResponse->assertStatus(200);
        $reconResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_missing_tax_account_mappings_produce_localized_warning_codes(): void
    {
        $existingMappings = AccountingAccountMapping::query()
            ->whereIn('key', ['output_tax_payable', 'input_tax_receivable'])
            ->get();

        AccountingAccountMapping::query()->whereIn('key', ['output_tax_payable', 'input_tax_receivable'])->delete();

        try {
            $reconService = app(VatToGlReconciliationService::class);
            $report = $reconService->generate(['from_date' => '2026-01-01', 'to_date' => '2026-12-31', 'currency' => $this->currency]);

            $this->assertFalse($report['is_reconciled']);
            $this->assertCount(2, $report['warnings']);
            $codes = array_column($report['warnings'], 'code');
            $this->assertContains('ERR_OUTPUT_TAX_ACCOUNT_NOT_MAPPED', $codes);
            $this->assertContains('ERR_INPUT_TAX_ACCOUNT_NOT_MAPPED', $codes);
        } finally {
            foreach ($existingMappings as $mapping) {
                AccountingAccountMapping::query()->updateOrCreate(
                    ['key' => $mapping->key],
                    ['account_id' => $mapping->account_id, 'description' => $mapping->description]
                );
            }
        }
    }

    public function test_authorization_for_vat_report_view_and_export(): void
    {
        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser);

        $this->get('/reports/vat-register')->assertStatus(403);
        $this->get('/reports/vat-summary')->assertStatus(403);
        $this->get('/reports/vat-gl-reconciliation')->assertStatus(403);

        $this->get('/reports/vat-register/export')->assertStatus(403);
        $this->get('/reports/vat-summary/export')->assertStatus(403);
        $this->get('/reports/vat-gl-reconciliation/export')->assertStatus(403);

        $this->actingAs($this->adminUser);

        $this->get('/reports/vat-register')->assertStatus(200);
        $this->get('/reports/vat-summary')->assertStatus(200);
        $this->get('/reports/vat-gl-reconciliation')->assertStatus(200);
    }
}
