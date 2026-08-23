<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Taxes\TaxMasterDataService;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesTaxPostingStressCommand extends Command
{
    protected $signature = 'accounting:sales-tax-stress {--workers=50}';

    protected $description = 'Run stress test for Phase 7 Sales Output VAT posting integrity and idempotency.';

    public function handle(
        CustomerInvoiceService $invoiceService,
        CustomerCreditNoteService $creditNoteService,
        TaxMasterDataService $taxMasterService,
        PeriodService $periodService,
        AccountingAccountMappingService $mappingService,
    ): int {
        $driver = DB::connection()->getDriverName();
        $this->info("Running Sales Output VAT Posting Stress test on DB driver: {$driver}");

        DB::beginTransaction();

        try {
            $user = User::query()->first() ?? User::factory()->create();

            // 1. Setup Tax Code
            $taxCode = TaxCode::query()->where('code', 'VAT_STRESS_14')->first();
            if (! $taxCode) {
                $taxCode = $taxMasterService->createTaxCode([
                    'code' => 'VAT_STRESS_14',
                    'name' => ['en' => 'Stress VAT 14%', 'ar' => 'ضريبة 14%'],
                    'calculation_mode' => 'exclusive',
                    'recoverability_mode' => 'full',
                ]);
                $taxMasterService->createTaxRate([
                    'tax_code_id' => $taxCode->id,
                    'rate_bps' => 1400,
                    'effective_from' => '2020-01-01',
                ]);
            }

            // 2. Setup Customer & Product
            $customer = Customer::query()->where('status', 'active')->first() ?? Customer::factory()->create(['status' => 'active']);
            $product = Product::query()->where('status', 'active')->where('is_sales_enabled', true)->whereIn('type', ['service', 'non_stock'])->first();
            if (! $product) {
                $uom = UnitOfMeasure::query()->first() ?? UnitOfMeasure::query()->create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);
                $product = Product::query()->create([
                    'code' => 'PROD-STRESS-1',
                    'name' => ['en' => 'Stress Product', 'ar' => 'منتج اختبار'],
                    'type' => 'service',
                    'status' => 'active',
                    'is_sales_enabled' => true,
                    'unit_of_measure_id' => $uom->id,
                ]);
            }

            $currency = $mappingService->getAccount('ar_control')->currency;

            // 3. Create Draft Customer Invoice with Taxable Line ($100.00 base, $14.00 tax)
            $invoice = $invoiceService->create([
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'currency' => $currency,
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'unit_of_measure_id' => $product->unit_of_measure_id,
                        'description' => 'Taxable Service Line',
                        'quantity_e6' => 1000000,
                        'unit_price_minor' => 10000,
                        'tax_code_id' => $taxCode->id,
                    ],
                ],
            ], $user->id);

            if ($invoice->subtotal_minor !== 10000 || $invoice->tax_amount_minor !== 1400 || $invoice->total_minor !== 11400) {
                throw new \Exception("Invoice totals calculation mismatch. Expected subtotal 10000, tax 1400, total 11400. Got: {$invoice->subtotal_minor}, {$invoice->tax_amount_minor}, {$invoice->total_minor}");
            }

            $invoiceService->approve($invoice->id, $user->id);

            // 4. Repeated Posting Attempts (Idempotency Check)
            $postedInvoice = null;
            for ($i = 0; $i < 5; $i++) {
                $postedInvoice = $invoiceService->post($invoice->id, $user->id);
            }

            if ($postedInvoice->status !== 'posted' || ! $postedInvoice->journal_entry_id) {
                throw new \Exception('Invoice state is not posted or missing journal_entry_id.');
            }

            $outputTaxAccount = $mappingService->getAccount('output_tax_payable');

            // Verify Journal Entry contains Cr Output Tax Payable of 1400 minor
            $journalEntry = $postedInvoice->journalEntry;
            if (! $journalEntry) {
                throw new \Exception('Missing journal entry on posted invoice.');
            }
            $outputTaxLines = $journalEntry->lines()->where('account_id', $outputTaxAccount->id)->get();
            if ($outputTaxLines->count() !== 1 || (int) $outputTaxLines->first()->credit_minor !== 1400) {
                throw new \Exception('Journal entry missing Cr Output Tax Payable line of 1400 minor.');
            }

            $this->info('PASS: Customer Invoice Output VAT posted correctly and idempotently.');

            // 5. Create Credit Note reversing original invoice tax
            $creditNote = $creditNoteService->create([
                'customer_id' => $customer->id,
                'customer_invoice_id' => $postedInvoice->id,
                'credit_date' => now()->format('Y-m-d'),
                'currency' => $currency,
                'lines' => [
                    [
                        'customer_invoice_line_id' => $postedInvoice->lines->first()->id,
                        'description' => 'Reversal of Taxable Service',
                        'quantity_e6' => 1000000,
                        'unit_price_minor' => 10000,
                    ],
                ],
            ], $user->id);

            if ((int) $creditNote->tax_minor !== 1400) {
                throw new \Exception("Credit note tax mismatch. Expected 1400, got {$creditNote->tax_minor}");
            }
            $creditNoteService->approve($creditNote->id, $user->id);
            $postedCN = $creditNoteService->post($creditNote->id, $user->id);

            $cnJournal = $postedCN->journalEntry;
            $cnOutputTaxLines = $cnJournal->lines()->where('account_id', $outputTaxAccount->id)->get();
            if ($cnOutputTaxLines->count() !== 1 || (int) $cnOutputTaxLines->first()->debit_minor !== 1400) {
                throw new \Exception('Credit note journal entry missing Dr Output Tax Payable line of 1400 minor.');
            }

            $this->info('PASS: Credit Note Output VAT reversed correctly.');

            DB::rollBack();
            $this->info('Sales Output VAT Posting Stress Test PASSED CLEANLY.');

            return 0;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Sales Output VAT Stress Test FAILED: '.$e->getMessage()."\n".$e->getTraceAsString());

            return 1;
        }
    }
}
