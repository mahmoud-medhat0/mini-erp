<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Purchasing\SupplierAdjustmentNoteService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Taxes\TaxMasterDataService;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7Slice4PurchasingInputVatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Product $product;

    private TaxCode $vat14;

    private string $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::query()->first() ?? User::factory()->create();

        $taxMasterService = app(TaxMasterDataService::class);
        $this->vat14 = $taxMasterService->createTaxCode([
            'code' => 'VAT_IN_14',
            'name' => ['en' => 'Input VAT 14%', 'ar' => 'ضريبة مدخلات 14%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);
        $taxMasterService->createTaxRate([
            'tax_code_id' => $this->vat14->id,
            'rate_bps' => 1400,
            'effective_from' => '2020-01-01',
        ]);

        $this->supplier = Supplier::query()->where('status', 'active')->first() ?? Supplier::create([
            'code' => 'SUPP-VAT-TEST',
            'name' => 'VAT Test Supplier',
            'status' => 'active',
        ]);

        $uom = UnitOfMeasure::query()->first() ?? UnitOfMeasure::query()->create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);
        $this->product = Product::query()->create([
            'code' => 'PROD-PURCH-14',
            'name' => ['en' => 'Purchasing Tax Test Product', 'ar' => 'منتج شراء اختبار'],
            'type' => 'service',
            'status' => 'active',
            'is_purchase_enabled' => true,
            'unit_of_measure_id' => $uom->id,
        ]);

        $mappingService = app(AccountingAccountMappingService::class);
        $this->currency = $mappingService->getAccount('ap_control')->currency;
    }

    public function test_supplier_bill_calculates_and_posts_input_vat(): void
    {
        $billService = app(SupplierBillService::class);

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => now()->format('Y-m-d'),
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Service item',
                    'quantity_e6' => 1000000, // 1 unit
                    'unit_cost_minor' => 10000, // $100.00
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(10000, $bill->subtotal_minor);
        $this->assertEquals(1400, $bill->tax_amount_minor);
        $this->assertEquals(11400, $bill->total_minor);

        $billService->submit($bill->id, $this->user->id);
        $billService->approve($bill->id, $this->user->id);
        $postedBill = $billService->post($bill->id, $this->user->id);

        $this->assertEquals('posted', $postedBill->status);

        $journalEntry = $postedBill->journalEntry()->with('lines.account')->first();
        $this->assertNotNull($journalEntry);

        // Lines: Dr Purchase Expense 10000, Dr Input Tax 1400, Cr AP Control 11400
        $inputTaxLine = $journalEntry->lines->first(fn ($l) => $l->account->type === 'asset' && $l->debit_minor > 0);
        $this->assertNotNull($inputTaxLine);
        $this->assertEquals(1400, $inputTaxLine->debit_minor);

        $apLine = $journalEntry->lines->first(fn ($l) => $l->credit_minor > 0);
        $this->assertNotNull($apLine);
        $this->assertEquals(11400, $apLine->credit_minor);
    }

    public function test_supplier_adjustment_note_reverses_input_vat_when_decreasing_payable(): void
    {
        $billService = app(SupplierBillService::class);
        $adjustmentNoteService = app(SupplierAdjustmentNoteService::class);

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => now()->format('Y-m-d'),
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Service item',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 10000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->user->id);

        $billService->submit($bill->id, $this->user->id);
        $billService->approve($bill->id, $this->user->id);
        $postedBill = $billService->post($bill->id, $this->user->id);

        $billLine = $postedBill->lines->first();

        $note = $adjustmentNoteService->create([
            'supplier_id' => $this->supplier->id,
            'supplier_bill_id' => $postedBill->id,
            'adjustment_date' => now()->format('Y-m-d'),
            'direction' => 'decrease_payable',
            'currency' => $this->currency,
            'lines' => [
                [
                    'supplier_bill_line_id' => $billLine->id,
                    'description' => 'Partial Credit Note',
                    'unit_cost_minor' => 5000, // $50.00 returned => $7.00 tax reversed
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(5000, $note->subtotal_minor);
        $this->assertEquals(700, $note->tax_minor);
        $this->assertEquals(5700, $note->total_minor);

        $adjustmentNoteService->submit($note->id, $this->user->id);
        $adjustmentNoteService->approve($note->id, $this->user->id);
        $postedNote = $adjustmentNoteService->post($note->id, $this->user->id);

        $journalEntry = $postedNote->journalEntry()->with('lines.account')->first();
        $this->assertNotNull($journalEntry);

        // Lines: Dr AP Control 5700, Cr Purchase Returns 5000, Cr Input Tax 700
        $apLine = $journalEntry->lines->first(fn ($l) => $l->debit_minor > 0);
        $this->assertNotNull($apLine);
        $this->assertEquals(5700, $apLine->debit_minor);

        $taxReversalLine = $journalEntry->lines->first(fn ($l) => $l->credit_minor > 0 && $l->account->type === 'asset');
        $this->assertNotNull($taxReversalLine);
        $this->assertEquals(700, $taxReversalLine->credit_minor);
    }

    public function test_linked_adjustment_note_preserves_original_bill_line_tax_code(): void
    {
        $billService = app(SupplierBillService::class);
        $adjustmentNoteService = app(SupplierAdjustmentNoteService::class);

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => now()->format('Y-m-d'),
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Original Purchase Line',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 20000,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->user->id);

        $billService->submit($bill->id, $this->user->id);
        $billService->approve($bill->id, $this->user->id);
        $postedBill = $billService->post($bill->id, $this->user->id);

        $billLine = $postedBill->lines->first();

        // Create adjustment note without passing tax_code_id explicitly
        $note = $adjustmentNoteService->create([
            'supplier_id' => $this->supplier->id,
            'supplier_bill_id' => $postedBill->id,
            'adjustment_date' => now()->format('Y-m-d'),
            'direction' => 'decrease_payable',
            'currency' => $this->currency,
            'lines' => [
                [
                    'supplier_bill_line_id' => $billLine->id,
                    'description' => 'Adjustment Line',
                    'unit_cost_minor' => 10000,
                ],
            ],
        ], $this->user->id);

        $noteLine = $note->lines->first();
        $this->assertEquals($this->vat14->id, $noteLine->tax_code_id);
        $this->assertEquals(1400, $noteLine->tax_rate_bps);
        $this->assertEquals(1400, $noteLine->tax_amount_minor);
        $this->assertEquals(11400, $noteLine->gross_amount_minor);
    }

    public function test_zero_tax_bill_posting_does_not_create_input_tax_journal_line(): void
    {
        $billService = app(SupplierBillService::class);

        $bill = $billService->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => now()->format('Y-m-d'),
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Exempt Service Line',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => 15000,
                    'tax_code_id' => null,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(15000, $bill->subtotal_minor);
        $this->assertEquals(0, $bill->tax_amount_minor);
        $this->assertEquals(15000, $bill->total_minor);

        $billService->submit($bill->id, $this->user->id);
        $billService->approve($bill->id, $this->user->id);
        $postedBill = $billService->post($bill->id, $this->user->id);

        $journalEntry = $postedBill->journalEntry()->with('lines.account')->first();
        $this->assertCount(2, $journalEntry->lines); // Dr Expense 15000, Cr AP 15000
    }
}
