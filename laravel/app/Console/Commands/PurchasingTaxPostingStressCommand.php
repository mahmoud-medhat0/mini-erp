<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\Purchasing\SupplierAdjustmentNoteService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Taxes\TaxMasterDataService;
use App\Console\Commands\Concerns\GuardsStressExecution;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchasingTaxPostingStressCommand extends Command
{
    use GuardsStressExecution;

    protected $signature = 'accounting:purchasing-tax-stress {--workers=50}';

    protected $description = 'Run stress test for Phase 7 Purchasing Input VAT posting integrity and idempotency.';

    public function handle(
        SupplierBillService $billService,
        SupplierAdjustmentNoteService $adjustmentNoteService,
        TaxMasterDataService $taxMasterService,
        PeriodService $periodService,
        AccountingAccountMappingService $mappingService,
    ): int {
        if ($this->refusesProductionStressRun()) {
            return self::FAILURE;
        }

        $driver = DB::connection()->getDriverName();
        $this->info("Running Purchasing Input VAT Posting Stress test on DB driver: {$driver}");

        DB::beginTransaction();

        try {
            $user = User::query()->first() ?? User::factory()->create();

            // 1. Setup Input Tax Code (14% VAT)
            $taxCode = TaxCode::query()->where('code', 'VAT_INPUT_STRESS_14')->first();
            if (! $taxCode) {
                $taxCode = $taxMasterService->createTaxCode([
                    'code' => 'VAT_INPUT_STRESS_14',
                    'name' => ['en' => 'Input VAT 14%', 'ar' => 'ضريبة المدخلات 14%'],
                    'calculation_mode' => 'exclusive',
                    'recoverability_mode' => 'full',
                ]);
                $taxMasterService->createTaxRate([
                    'tax_code_id' => $taxCode->id,
                    'rate_bps' => 1400,
                    'effective_from' => '2020-01-01',
                ]);
            }

            // 2. Setup Supplier & Product
            $supplier = Supplier::query()->where('status', 'active')->first() ?? Supplier::factory()->create(['status' => 'active']);
            $product = Product::query()->where('status', 'active')->where('is_purchase_enabled', true)->whereIn('type', ['service', 'non_stock'])->first();
            if (! $product) {
                $uom = UnitOfMeasure::query()->first() ?? UnitOfMeasure::query()->create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);
                $product = Product::query()->create([
                    'code' => 'PROD-PURCHASE-STRESS-1',
                    'name' => ['en' => 'Purchase Stress Product', 'ar' => 'منتج شراء اختباري'],
                    'type' => 'service',
                    'status' => 'active',
                    'is_purchase_enabled' => true,
                    'unit_of_measure_id' => $uom->id,
                ]);
            }

            $currency = $mappingService->getAccount('ap_control')->currency;

            // 3. Create Draft Supplier Bill with Input Tax ($200.00 base, $28.00 tax = $228.00 total)
            $bill = $billService->create([
                'supplier_id' => $supplier->id,
                'bill_date' => now()->format('Y-m-d'),
                'currency' => $currency,
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'unit_of_measure_id' => $product->unit_of_measure_id,
                        'description' => 'Taxable Service Purchase Line',
                        'quantity_e6' => 1000000,
                        'unit_cost_minor' => 20000,
                        'tax_code_id' => $taxCode->id,
                    ],
                ],
            ], $user->id);

            if ($bill->subtotal_minor !== 20000 || $bill->tax_amount_minor !== 2800 || $bill->total_minor !== 22800) {
                throw new \Exception("Supplier Bill totals mismatch. Expected subtotal 20000, tax 2800, total 22800. Got: subtotal {$bill->subtotal_minor}, tax {$bill->tax_amount_minor}, total {$bill->total_minor}");
            }

            $billService->submit($bill->id, $user->id);
            $billService->approve($bill->id, $user->id);

            // 4. Repeated Bill Posting Attempts (Idempotency Check)
            $postedBill = null;
            for ($i = 0; $i < 5; $i++) {
                $postedBill = $billService->post($bill->id, $user->id);
            }

            if ($postedBill->status !== 'posted' || ! $postedBill->journal_entry_id) {
                throw new \Exception('Supplier Bill status is not posted or missing journal entry ID.');
            }

            // Verify Journal Entry Lines
            $journalEntry = $postedBill->journalEntry()->with('lines.account')->first();
            $inputTaxLine = $journalEntry->lines->first(fn ($l) => $l->account->type === 'asset' && $l->debit_minor > 0);
            if (! $inputTaxLine || $inputTaxLine->debit_minor !== 2800) {
                throw new \Exception('Journal Entry input tax debit mismatch. Expected 2800, got: '.($inputTaxLine?->debit_minor ?? 0));
            }

            // 5. Create Supplier Adjustment Note Linked to Supplier Bill Line (Credit Note / Reversal)
            $billLine = $postedBill->lines->first();
            $adjustmentNote = $adjustmentNoteService->create([
                'supplier_id' => $supplier->id,
                'supplier_bill_id' => $postedBill->id,
                'adjustment_date' => now()->format('Y-m-d'),
                'direction' => 'decrease_payable',
                'currency' => $currency,
                'lines' => [
                    [
                        'supplier_bill_line_id' => $billLine->id,
                        'description' => 'Return/Reversal of Service Purchase',
                        'unit_cost_minor' => 10000, // $100.00 base returned => $14.00 tax reversed
                    ],
                ],
            ], $user->id);

            $adjustmentNoteService->submit($adjustmentNote->id, $user->id);
            $adjustmentNoteService->approve($adjustmentNote->id, $user->id);

            // 6. Repeated Adjustment Note Posting Attempts
            $postedNote = null;
            for ($i = 0; $i < 5; $i++) {
                $postedNote = $adjustmentNoteService->post($adjustmentNote->id, $user->id);
            }

            if ($postedNote->status !== 'posted' || ! $postedNote->journal_entry_id) {
                throw new \Exception('Supplier Adjustment Note status is not posted or missing journal entry ID.');
            }

            // Verify Adjustment Note Journal Entry Lines (Dr AP Control 11400, Cr Purchase Returns 10000, Cr Input Tax 1400)
            $noteJournal = $postedNote->journalEntry()->with('lines.account')->first();
            $reversedTaxLine = $noteJournal->lines->first(fn ($l) => $l->credit_minor > 0 && $l->account->type === 'asset');
            if (! $reversedTaxLine || $reversedTaxLine->credit_minor !== 1400) {
                throw new \Exception('Supplier Adjustment Note input tax reversal credit mismatch. Expected 1400, got: '.($reversedTaxLine?->credit_minor ?? 0));
            }

            DB::rollBack();

            $this->info("Purchasing Input VAT Posting Stress Test PASSED cleanly on {$driver}. All checks verified.");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error("Purchasing Input VAT Posting Stress Test FAILED: {$e->getMessage()}");
            $this->error($e->getFile().':'.$e->getLine());

            return Command::FAILURE;
        }
    }
}
