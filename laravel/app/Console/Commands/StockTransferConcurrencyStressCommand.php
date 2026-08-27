<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Inventory\StockTransferService;
use App\Application\Support\BaseCurrencyResolver;
use App\Console\Commands\Concerns\ResolvesStressCurrency;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingAccountMapping;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class StockTransferConcurrencyStressCommand extends Command
{
    use ResolvesStressCurrency;

    protected $signature = 'accounting:stock-transfer-stress {--workers=50 : Number of concurrent transfer workers}';

    protected $description = 'Stress test warehouse stock transfer issue and receipt integrity under concurrency';

    public function handle(
        AccountingAccountMappingService $mappingService,
        MovingWeightedAverageInventoryService $inventoryService,
        BaseCurrencyResolver $baseCurrencyResolver,
    ): int {
        $driver = DB::connection()->getDriverName();
        $workers = max(2, min((int) $this->option('workers'), 100));

        $this->info("Running Stock Transfer Concurrency Stress Test on DB driver: [{$driver}] with [{$workers}] workers..");

        if ($driver !== 'pgsql') {
            $this->error('Stock transfer concurrency stress requires PostgreSQL row locking.');

            return SymfonyCommand::FAILURE;
        }

        $mappingKeys = ['inventory_asset', 'grni_clearing', 'cogs'];
        $previousMappings = $this->captureMappings($mappingKeys);

        try {
            $user = User::query()->first() ?? User::factory()->create();
            $currency = $this->resolveStressCurrency($baseCurrencyResolver);
            $suffix = Str::upper(Str::random(8));
            $year = random_int(3300, 8999);

            while (FiscalYear::query()->where('year', $year)->exists()) {
                $year = random_int(3300, 8999);
            }

            $fiscalYear = FiscalYear::query()->create([
                'year' => $year,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'lock_version' => 1,
            ]);

            /** @var FinancialPeriod $period */
            $period = FinancialPeriod::query()->create([
                'fiscal_year_id' => $fiscalYear->id,
                'month' => 1,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-01-31",
                'status' => 'open',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'lock_version' => 1,
            ]);

            $group = AccountGroup::query()->firstOrCreate(
                ['code' => "ST-XFER-GRP-{$suffix}"],
                [
                    'id' => (string) Str::uuid(),
                    'name' => ['en' => 'Stock Transfer Stress Group', 'ar' => 'مجموعة ضغط تحويل المخزون'],
                    'type' => 'asset',
                    'statement_section' => 'balance_sheet',
                    'sort_order' => 1,
                ],
            );

            $inventoryAccount = Account::query()->create([
                'code' => "14-XFER-{$suffix}",
                'name' => ['en' => 'Stock Transfer Inventory Asset', 'ar' => 'أصول مخزون اختبار التحويل'],
                'type' => 'asset',
                'nature' => 'debit',
                'account_group_id' => $group->id,
                'currency' => $currency,
                'is_control' => false,
                'allow_manual_posting' => true,
                'is_active' => true,
            ]);

            $grniAccount = Account::query()->create([
                'code' => "23-XFER-{$suffix}",
                'name' => ['en' => 'Stock Transfer GRNI', 'ar' => 'مقاصة استلام اختبار التحويل'],
                'type' => 'liability',
                'nature' => 'credit',
                'account_group_id' => $group->id,
                'currency' => $currency,
                'is_control' => false,
                'allow_manual_posting' => true,
                'is_active' => true,
            ]);

            $cogsAccount = Account::query()->create([
                'code' => "55-XFER-{$suffix}",
                'name' => ['en' => 'Stock Transfer COGS', 'ar' => 'تكلفة مبيعات اختبار التحويل'],
                'type' => 'expense',
                'nature' => 'debit',
                'account_group_id' => $group->id,
                'currency' => $currency,
                'is_control' => false,
                'allow_manual_posting' => true,
                'is_active' => true,
            ]);

            $mappingService->setMapping('inventory_asset', $inventoryAccount->id, 'Stock transfer stress inventory asset', $user->id);
            $mappingService->setMapping('grni_clearing', $grniAccount->id, 'Stock transfer stress GRNI clearing', $user->id);
            $mappingService->setMapping('cogs', $cogsAccount->id, 'Stock transfer stress COGS', $user->id);

            $uom = UnitOfMeasure::query()->firstOrCreate(
                ['code' => "PCS-XFER-{$suffix}"],
                [
                    'id' => (string) Str::uuid(),
                    'name' => ['en' => 'Transfer Piece', 'ar' => 'قطعة تحويل'],
                    'symbol' => 'pc',
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'lock_version' => 1,
                ],
            );

            $category = ProductCategory::query()->firstOrCreate(
                ['code' => "CAT-XFER-{$suffix}"],
                [
                    'id' => (string) Str::uuid(),
                    'name' => ['en' => 'Transfer Stress Category', 'ar' => 'تصنيف ضغط التحويل'],
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'lock_version' => 1,
                ],
            );

            $product = Product::query()->create([
                'code' => "PROD-XFER-{$suffix}",
                'name' => ['en' => 'Stock Transfer Stress Product', 'ar' => 'صنف ضغط تحويل المخزون'],
                'type' => 'stock',
                'unit_of_measure_id' => $uom->id,
                'product_category_id' => $category->id,
                'status' => 'active',
                'is_sales_enabled' => true,
                'is_purchase_enabled' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'lock_version' => 1,
            ]);

            $sourceWarehouse = Warehouse::query()->create([
                'code' => "SRC-XFER-{$suffix}",
                'name' => ['en' => 'Transfer Stress Source', 'ar' => 'مصدر ضغط التحويل'],
                'warehouse_type' => 'standard',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'lock_version' => 1,
            ]);

            $destinationWarehouse = Warehouse::query()->create([
                'code' => "DST-XFER-{$suffix}",
                'name' => ['en' => 'Transfer Stress Destination', 'ar' => 'وجهة ضغط التحويل'],
                'warehouse_type' => 'standard',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'lock_version' => 1,
            ]);

            $unitQtyE6 = 1000000;
            $unitCostMinor = 1000;
            $totalQtyE6 = $workers * $unitQtyE6;

            $inventoryService->recordReceipt(
                sourceType: 'stock_transfer_stress_seed',
                sourceId: (string) Str::uuid(),
                sourceLineId: (string) Str::uuid(),
                movementDate: "{$year}-01-05",
                productId: $product->id,
                unitOfMeasureId: $uom->id,
                currency: $currency,
                quantityE6: $totalQtyE6,
                unitCostMinor: $unitCostMinor,
                fiscalYearId: $fiscalYear->id,
                financialPeriodId: $period->id,
                actorId: $user->id,
                warehouseId: $sourceWarehouse->id,
            );

            $tasks = [];
            for ($i = 0; $i < $workers; $i++) {
                $sourceWarehouseId = $sourceWarehouse->id;
                $destinationWarehouseId = $destinationWarehouse->id;
                $productId = $product->id;
                $uomId = $uom->id;
                $userId = $user->id;

                $tasks[] = static function () use ($sourceWarehouseId, $destinationWarehouseId, $productId, $uomId, $year, $unitQtyE6, $userId): array {
                    /** @var StockTransferService $service */
                    $service = app(StockTransferService::class);

                    try {
                        $transfer = $service->create([
                            'transfer_date' => "{$year}-01-10",
                            'source_warehouse_id' => $sourceWarehouseId,
                            'destination_warehouse_id' => $destinationWarehouseId,
                            'reference' => (string) Str::uuid(),
                            'reason' => 'Stock transfer stress worker',
                            'lines' => [
                                [
                                    'product_id' => $productId,
                                    'unit_of_measure_id' => $uomId,
                                    'quantity_e6' => $unitQtyE6,
                                ],
                            ],
                        ], $userId);

                        $transfer = $service->approve($transfer->id, $userId);
                        $transfer = $service->issue($transfer->id, $userId);
                        $transfer = $service->receive($transfer->id, [
                            'receipt_date' => "{$year}-01-11",
                            'lines' => [],
                        ], $userId);

                        return ['status' => 'success', 'transfer_id' => $transfer->id];
                    } catch (ValidationException $e) {
                        return ['status' => 'validation_error', 'message' => $e->getMessage()];
                    } catch (Throwable $e) {
                        return ['status' => 'error', 'message' => $e->getMessage()];
                    }
                };
            }

            $results = collect(Concurrency::run($tasks));
            $statusCounts = $results->pluck('status')->countBy()->all();
            $this->info('Worker results: '.json_encode($statusCounts, JSON_THROW_ON_ERROR));

            $sourceBalance = StockBalance::query()
                ->where('warehouse_id', $sourceWarehouse->id)
                ->where('product_id', $product->id)
                ->firstOrFail();

            $destinationBalance = StockBalance::query()
                ->where('warehouse_id', $destinationWarehouse->id)
                ->where('product_id', $product->id)
                ->firstOrFail();

            $transferOutCount = StockMovementLedger::query()
                ->where('warehouse_id', $sourceWarehouse->id)
                ->where('movement_type', 'transfer_out')
                ->count();

            $transferInCount = StockMovementLedger::query()
                ->where('warehouse_id', $destinationWarehouse->id)
                ->where('movement_type', 'transfer_in')
                ->count();

            $transferJournalLeakCount = StockMovementLedger::query()
                ->whereIn('movement_type', ['transfer_out', 'transfer_in'])
                ->whereNotNull('journal_entry_id')
                ->count();

            $expectedValueMinor = $workers * $unitCostMinor;

            if (($statusCounts['success'] ?? 0) !== $workers) {
                $this->error('STRESS TEST FAILED: Not all transfer workers completed successfully.');

                return SymfonyCommand::FAILURE;
            }

            if ($sourceBalance->quantity_e6 !== 0 || $sourceBalance->valuation_amount_minor !== 0) {
                $this->error("STRESS TEST FAILED: Source residual qty={$sourceBalance->quantity_e6}, value={$sourceBalance->valuation_amount_minor}.");

                return SymfonyCommand::FAILURE;
            }

            if ($destinationBalance->quantity_e6 !== $totalQtyE6 || $destinationBalance->valuation_amount_minor !== $expectedValueMinor) {
                $this->error("STRESS TEST FAILED: Destination qty={$destinationBalance->quantity_e6}, value={$destinationBalance->valuation_amount_minor}.");

                return SymfonyCommand::FAILURE;
            }

            if ($transferOutCount !== $workers || $transferInCount !== $workers || $transferJournalLeakCount !== 0) {
                $this->error("STRESS TEST FAILED: movements out={$transferOutCount}, in={$transferInCount}, journal leaks={$transferJournalLeakCount}.");

                return SymfonyCommand::FAILURE;
            }

            $this->info('Stock Transfer Concurrency Stress Test PASSED CLEANLY.');

            return SymfonyCommand::SUCCESS;
        } finally {
            $this->restoreMappings($previousMappings, $mappingKeys);
        }
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>>
     */
    private function captureMappings(array $keys): array
    {
        return AccountingAccountMapping::query()
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (AccountingAccountMapping $mapping): array => [
                $mapping->key => [
                    'account_id' => $mapping->account_id,
                    'description' => $mapping->description,
                    'created_by' => $mapping->created_by,
                    'updated_by' => $mapping->updated_by,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $previousMappings
     * @param  array<int, string>  $keys
     */
    private function restoreMappings(array $previousMappings, array $keys): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $previousMappings)) {
                AccountingAccountMapping::query()->where('key', $key)->delete();

                continue;
            }

            AccountingAccountMapping::query()->updateOrCreate(
                ['key' => $key],
                $previousMappings[$key],
            );
        }
    }
}
