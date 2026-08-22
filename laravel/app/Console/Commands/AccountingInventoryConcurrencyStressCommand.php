<?php

namespace App\Console\Commands;

use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AccountingInventoryConcurrencyStressCommand extends Command
{
    protected $signature = 'accounting:inventory-concurrency-stress {--workers=50}';

    protected $description = 'Stress test Moving Weighted Average Inventory Costing integrity';

    public function handle(): int
    {
        $workers = (int) $this->option('workers');
        $this->info("Starting Inventory Integrity Stress Test with {$workers} iterations...");

        // Setup test user & baseline data
        /** @var User $user */
        $user = User::query()->firstOrCreate(['email' => 'stress-inventory@example.com'], [
            'name' => 'Stress Inventory User',
            'password' => 'stress-secret-123',
            'locale' => 'en',
        ]);

        $currency = Currency::query()->firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'sub_unit' => 'Cent', 'sub_unit_to_unit' => 100, 'is_active' => true,
        ]);

        $fiscalYear = FiscalYear::query()->firstOrCreate(['year' => 2026], [
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_by' => $user->id, 'updated_by' => $user->id, 'lock_version' => 1,
        ]);

        $period = FinancialPeriod::query()->firstOrCreate(['fiscal_year_id' => $fiscalYear->id, 'month' => 1], [
            'name' => 'January 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'open', 'created_by' => $user->id, 'updated_by' => $user->id, 'lock_version' => 1,
        ]);

        $inventoryAcc = Account::query()->firstOrCreate(['code' => '1300-STRESS'], [
            'name' => 'Inventory Asset Stress', 'type' => 'asset', 'nature' => 'debit', 'currency' => 'USD', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $grniAcc = Account::query()->firstOrCreate(['code' => '2200-STRESS'], [
            'name' => 'GRNI Clearing Stress', 'type' => 'liability', 'nature' => 'credit', 'currency' => 'USD', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $cogsAcc = Account::query()->firstOrCreate(['code' => '5200-STRESS'], [
            'name' => 'COGS Stress', 'type' => 'expense', 'nature' => 'debit', 'currency' => 'USD', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        AccountingAccountMapping::query()->updateOrCreate(['key' => 'inventory_asset'], ['account_id' => $inventoryAcc->id, 'created_by' => $user->id, 'updated_by' => $user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'grni_clearing'], ['account_id' => $grniAcc->id, 'created_by' => $user->id, 'updated_by' => $user->id]);
        AccountingAccountMapping::query()->updateOrCreate(['key' => 'cogs'], ['account_id' => $cogsAcc->id, 'created_by' => $user->id, 'updated_by' => $user->id]);

        $uom = UnitOfMeasure::query()->firstOrCreate(['code' => 'PCS-STR'], ['name' => ['en' => 'Pieces'], 'symbol' => 'pcs', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $cat = ProductCategory::query()->firstOrCreate(['code' => 'CAT-STR'], ['name' => ['en' => 'Stress Cat'], 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);

        $runId = now()->format('YmdHis').'-'.Str::lower(Str::random(8));
        $product = Product::query()->create([
            'code' => "PROD-STRESS-INV-{$runId}",
            'name' => ['en' => "Stress Inventory Product {$runId}"],
            'type' => 'stock',
            'product_category_id' => $cat->id,
            'unit_of_measure_id' => $uom->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'lock_version' => 1,
        ]);

        /** @var MovingWeightedAverageInventoryService $inventoryService */
        $inventoryService = app(MovingWeightedAverageInventoryService::class);

        $this->info("Executing {$workers} receipt iterations...");
        $receiptQtyE6 = 1000000; // 1 unit
        $unitCostMinor = 1000; // 10.00 USD

        for ($i = 0; $i < $workers; $i++) {
            $sourceId = (string) Str::uuid();
            $sourceLineId = (string) Str::uuid();

            $inventoryService->recordReceipt(
                sourceType: 'stress_gr',
                sourceId: $sourceId,
                sourceLineId: $sourceLineId,
                movementDate: '2026-01-15',
                productId: $product->id,
                unitOfMeasureId: $uom->id,
                currency: 'USD',
                quantityE6: $receiptQtyE6,
                unitCostMinor: $unitCostMinor,
                fiscalYearId: $fiscalYear->id,
                financialPeriodId: $period->id,
                actorId: $user->id,
            );
        }

        // Verify balance after receipts
        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('product_id', $product->id)->firstOrFail();
        $expectedQtyE6 = $workers * $receiptQtyE6;
        $expectedValuation = $workers * $unitCostMinor;

        if ($balance->quantity_e6 !== $expectedQtyE6 || $balance->valuation_amount_minor !== $expectedValuation) {
            $this->error("Stock balance mismatch after receipts! Qty: {$balance->quantity_e6} (expected {$expectedQtyE6}), Val: {$balance->valuation_amount_minor} (expected {$expectedValuation})");

            return 1;
        }

        $this->info("Executing {$workers} issue iterations...");
        for ($i = 0; $i < $workers; $i++) {
            $sourceId = (string) Str::uuid();
            $sourceLineId = (string) Str::uuid();

            $inventoryService->recordIssue(
                sourceType: 'stress_dn',
                sourceId: $sourceId,
                sourceLineId: $sourceLineId,
                movementDate: '2026-01-20',
                productId: $product->id,
                unitOfMeasureId: $uom->id,
                currency: 'USD',
                quantityE6: $receiptQtyE6,
                fiscalYearId: $fiscalYear->id,
                financialPeriodId: $period->id,
                actorId: $user->id,
            );
        }

        $balance->refresh();
        if ($balance->quantity_e6 !== 0 || $balance->valuation_amount_minor !== 0) {
            $this->error("Stock balance residual mismatch after issues! Qty: {$balance->quantity_e6}, Val: {$balance->valuation_amount_minor}");

            return 1;
        }

        $this->info('Inventory Integrity Stress Test completed successfully!');

        return 0;
    }
}
