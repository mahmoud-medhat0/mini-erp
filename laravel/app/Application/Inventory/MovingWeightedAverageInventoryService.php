<?php

namespace App\Application\Inventory;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\JournalEntry;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovingWeightedAverageInventoryService
{
    private const QUANTITY_SCALE = 1000000;

    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function recordReceipt(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityE6,
        int $unitCostMinor,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $unitCostMinor, $financialPeriodId, $actorId, $warehouseId
        ): StockMovementLedger {
            // Idempotency check
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', 'receipt')
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(['quantity' => [__('Receipt quantity must be greater than zero.')]]);
            }

            if ($unitCostMinor <= 0) {
                throw ValidationException::withMessages(['unit_cost' => [__('Receipt unit cost must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);
            $branchId = $this->branchIdForWarehouse($resolvedWarehouseId);
            $lineValueMinor = $this->receiptValueMinor($quantityE6, $unitCostMinor);

            // Fetch mapped GL Accounts
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset', $branchId);
            $grniAccount = $this->mappingService->getAccount('grni_clearing', $branchId);

            if ($inventoryAccount->currency !== $currency || $grniAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped GL account currencies must match movement currency.')]]);
            }

            // Lock stock balance row
            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                // Verify no balance exists with another currency
                $otherCurrencyBalance = StockBalance::query()
                    ->where('warehouse_id', $resolvedWarehouseId)
                    ->where('product_id', $productId)
                    ->first();

                if ($otherCurrencyBalance && $otherCurrencyBalance->currency !== $currency) {
                    throw ValidationException::withMessages([
                        'currency' => [__('Stock balance already exists for this product in currency [:currency]. Multi-currency valuation for the same product is not allowed.', [
                            'currency' => $otherCurrencyBalance->currency,
                        ])],
                    ]);
                }

                $balance = StockBalance::query()->create([
                    'warehouse_id' => $resolvedWarehouseId,
                    'product_id' => $productId,
                    'unit_of_measure_id' => $unitOfMeasureId,
                    'currency' => $currency,
                    'quantity_e6' => 0,
                    'valuation_amount_minor' => 0,
                    'avg_unit_cost_e6' => 0,
                    'lock_version' => 1,
                ]);
            }

            $newQtyE6 = $this->checkedAdd($balance->quantity_e6, $quantityE6, 'quantity');
            $newValueMinor = $this->checkedAdd($balance->valuation_amount_minor, $lineValueMinor, 'valuation');
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            // Create Journal Entry: Dr Inventory Asset / Cr GRNI Clearing
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $movementDate,
                'financial_period_id' => $financialPeriodId,
                'branch_id' => $branchId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => "Inventory Receipt - {$sourceType} #{$sourceId}",
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            // Dr Inventory Asset
            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $inventoryAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Inventory Asset Receipt',
                'debit_minor' => $lineValueMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $lineValueMinor,
                'credit_txn_minor' => 0,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            // Cr GRNI Clearing
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $grniAccount->id,
                'branch_id' => $branchId,
                'memo' => 'GRNI Clearing',
                'debit_minor' => 0,
                'credit_minor' => $lineValueMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $lineValueMinor,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => 'receipt',
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => $quantityE6,
                'value_delta_minor' => $lineValueMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => $postedJournal->id,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.receipt',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    public function recordIssue(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityE6,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $financialPeriodId, $actorId, $warehouseId
        ): StockMovementLedger {
            // Idempotency check
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', 'issue')
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(['quantity' => [__('Issue quantity must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);
            $branchId = $this->branchIdForWarehouse($resolvedWarehouseId);

            // Fetch mapped GL Accounts
            $cogsAccount = $this->mappingService->getAccount('cogs', $branchId);
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset', $branchId);

            if ($cogsAccount->currency !== $currency || $inventoryAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped GL account currencies must match movement currency.')]]);
            }

            // Lock stock balance row
            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance || $balance->quantity_e6 < $quantityE6) {
                $available = $balance ? $balance->quantity_e6 : 0;
                $wholeAvail = intdiv($available, 1000000);
                $fracAvail = sprintf('%06d', abs($available % 1000000));
                throw ValidationException::withMessages([
                    'stock' => [__('Insufficient stock balance for product. Available: :available.', [
                        'available' => "{$wholeAvail}.{$fracAvail}",
                    ])],
                ]);
            }

            $issueCostMinor = $this->issueValueMinor($quantityE6, $balance);

            $newQtyE6 = $balance->quantity_e6 - $quantityE6;
            $newValueMinor = $balance->valuation_amount_minor - $issueCostMinor;
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            // Create Journal Entry: Dr COGS / Cr Inventory Asset
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $movementDate,
                'financial_period_id' => $financialPeriodId,
                'branch_id' => $branchId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => "Inventory Issue (COGS) - {$sourceType} #{$sourceId}",
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            // Dr COGS
            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $cogsAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Cost of Goods Sold',
                'debit_minor' => $issueCostMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $issueCostMinor,
                'credit_txn_minor' => 0,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            // Cr Inventory Asset
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $inventoryAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Inventory Asset Issue',
                'debit_minor' => 0,
                'credit_minor' => $issueCostMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $issueCostMinor,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => 'issue',
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => -$quantityE6,
                'value_delta_minor' => -$issueCostMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => $postedJournal->id,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.issue',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    private function receiptValueMinor(int $quantityE6, int $unitCostMinor): int
    {
        $this->assertProductWithinIntegerRange($quantityE6, $unitCostMinor, 'unit_cost');

        $product = $quantityE6 * $unitCostMinor;
        if ($product % self::QUANTITY_SCALE !== 0) {
            throw ValidationException::withMessages(['unit_cost' => [__('Quantity and unit cost result in fractional minor units.')]]);
        }

        return intdiv($product, self::QUANTITY_SCALE);
    }

    private function issueValueMinor(int $quantityE6, StockBalance $balance): int
    {
        if ($quantityE6 === $balance->quantity_e6) {
            return $balance->valuation_amount_minor;
        }

        $this->assertProductWithinIntegerRange($quantityE6, $balance->valuation_amount_minor, 'stock');

        return intdiv($quantityE6 * $balance->valuation_amount_minor, $balance->quantity_e6);
    }

    private function averageUnitCostE6(int $valuationAmountMinor, int $quantityE6): int
    {
        if ($quantityE6 <= 0) {
            return 0;
        }

        $this->assertProductWithinIntegerRange($valuationAmountMinor, self::QUANTITY_SCALE, 'valuation');

        return intdiv($valuationAmountMinor * self::QUANTITY_SCALE, $quantityE6);
    }

    private function checkedAdd(int $left, int $right, string $field): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw ValidationException::withMessages([$field => [__('Inventory calculation exceeds supported integer range.')]]);
        }

        return $left + $right;
    }

    public function recordReturn(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityE6,
        int $unitCostMinor,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $unitCostMinor, $financialPeriodId, $actorId, $warehouseId
        ): StockMovementLedger {
            $returnMovementType = 'reversal';

            // Idempotency check
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', $returnMovementType)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(['quantity' => [__('Return quantity must be greater than zero.')]]);
            }

            if ($unitCostMinor <= 0) {
                throw ValidationException::withMessages(['unit_cost' => [__('Return unit cost must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);
            $branchId = $this->branchIdForWarehouse($resolvedWarehouseId);
            $lineValueMinor = $this->receiptValueMinor($quantityE6, $unitCostMinor);

            // Fetch mapped GL Accounts
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset', $branchId);
            $cogsAccount = $this->mappingService->getAccount('cogs', $branchId);

            if ($inventoryAccount->currency !== $currency || $cogsAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped GL account currencies must match movement currency.')]]);
            }

            // Lock stock balance row
            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $otherCurrencyBalance = StockBalance::query()
                    ->where('warehouse_id', $resolvedWarehouseId)
                    ->where('product_id', $productId)
                    ->first();

                if ($otherCurrencyBalance && $otherCurrencyBalance->currency !== $currency) {
                    throw ValidationException::withMessages([
                        'currency' => [__('Stock balance already exists for this product in currency [:currency]. Multi-currency valuation for the same product is not allowed.', [
                            'currency' => $otherCurrencyBalance->currency,
                        ])],
                    ]);
                }

                $balance = StockBalance::query()->create([
                    'warehouse_id' => $resolvedWarehouseId,
                    'product_id' => $productId,
                    'unit_of_measure_id' => $unitOfMeasureId,
                    'currency' => $currency,
                    'quantity_e6' => 0,
                    'valuation_amount_minor' => 0,
                    'avg_unit_cost_e6' => 0,
                    'lock_version' => 1,
                ]);
            }

            $newQtyE6 = $this->checkedAdd($balance->quantity_e6, $quantityE6, 'quantity');
            $newValueMinor = $this->checkedAdd($balance->valuation_amount_minor, $lineValueMinor, 'valuation');
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            // Create Journal Entry: Dr Inventory Asset / Cr COGS (reverse of issue)
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $movementDate,
                'financial_period_id' => $financialPeriodId,
                'branch_id' => $branchId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => "Inventory Return - {$sourceType} #{$sourceId}",
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            // Dr Inventory Asset
            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $inventoryAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Inventory Asset Return',
                'debit_minor' => $lineValueMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $lineValueMinor,
                'credit_txn_minor' => 0,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            // Cr COGS
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $cogsAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Cost of Goods Sold Reversal',
                'debit_minor' => 0,
                'credit_minor' => $lineValueMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $lineValueMinor,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => $returnMovementType,
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => $quantityE6,
                'value_delta_minor' => $lineValueMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => $postedJournal->id,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.return',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    public function recordScrap(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityE6,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $financialPeriodId, $actorId, $warehouseId
        ): StockMovementLedger {
            $scrapMovementType = 'scrap';

            // Idempotency check
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', $scrapMovementType)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(['quantity' => [__('Scrap quantity must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);
            $branchId = $this->branchIdForWarehouse($resolvedWarehouseId);

            // Fetch mapped GL Accounts
            $scrapLossAccount = $this->mappingService->getAccount('inventory_scrap_loss', $branchId);
            $cogsAccount = $this->mappingService->getAccount('cogs', $branchId);

            if ($scrapLossAccount->currency !== $currency || $cogsAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped GL account currencies must match movement currency.')]]);
            }

            // Lock stock balance row
            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance || $balance->quantity_e6 < $quantityE6) {
                $available = $balance ? $balance->quantity_e6 : 0;
                $wholeAvail = intdiv($available, 1000000);
                $fracAvail = sprintf('%06d', abs($available % 1000000));
                throw ValidationException::withMessages([
                    'stock' => [__('Insufficient stock balance for scrap. Available: :available.', [
                        'available' => "{$wholeAvail}.{$fracAvail}",
                    ])],
                ]);
            }

            // Calculate scrap value using MWA cost
            $scrapValueMinor = $this->issueValueMinor($quantityE6, $balance);

            // Reduce stock balance quantity and valuation
            $newQtyE6 = $balance->quantity_e6 - $quantityE6;
            $newValueMinor = $balance->valuation_amount_minor - $scrapValueMinor;
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            // Create Journal Entry: Dr Inventory Scrap Loss / Cr COGS
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $movementDate,
                'financial_period_id' => $financialPeriodId,
                'branch_id' => $branchId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => "Inventory Scrap - {$sourceType} #{$sourceId}",
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            // Dr Inventory Scrap Loss
            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $scrapLossAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Inventory Scrap Loss',
                'debit_minor' => $scrapValueMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $scrapValueMinor,
                'credit_txn_minor' => 0,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            // Cr COGS
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $cogsAccount->id,
                'branch_id' => $branchId,
                'memo' => 'Cost of Goods Sold (Scrap)',
                'debit_minor' => 0,
                'credit_minor' => $scrapValueMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $scrapValueMinor,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => $scrapMovementType,
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => -$quantityE6,
                'value_delta_minor' => -$scrapValueMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => $postedJournal->id,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.scrap',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    public function recordTransferOut(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityE6,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $actorId, $warehouseId
        ): StockMovementLedger {
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', 'transfer_out')
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(['quantity' => [__('Transfer quantity must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);

            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance || $balance->quantity_e6 < $quantityE6) {
                $available = $balance ? $balance->quantity_e6 : 0;
                $wholeAvail = intdiv($available, self::QUANTITY_SCALE);
                $fracAvail = sprintf('%06d', abs($available % self::QUANTITY_SCALE));
                throw ValidationException::withMessages([
                    'stock' => [__('Insufficient source warehouse stock. Available: :available.', [
                        'available' => "{$wholeAvail}.{$fracAvail}",
                    ])],
                ]);
            }

            $transferValueMinor = $this->issueValueMinor($quantityE6, $balance);
            $newQtyE6 = $balance->quantity_e6 - $quantityE6;
            $newValueMinor = $balance->valuation_amount_minor - $transferValueMinor;
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => 'transfer_out',
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => -$quantityE6,
                'value_delta_minor' => -$transferValueMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => null,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.transfer_out',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    public function recordTransferIn(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityE6,
        int $valueMinor,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $valueMinor, $actorId, $warehouseId
        ): StockMovementLedger {
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', 'transfer_in')
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(['quantity' => [__('Transfer receipt quantity must be greater than zero.')]]);
            }

            if ($valueMinor <= 0) {
                throw ValidationException::withMessages(['value' => [__('Transfer receipt value must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);

            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $otherCurrencyBalance = StockBalance::query()
                    ->where('warehouse_id', $resolvedWarehouseId)
                    ->where('product_id', $productId)
                    ->first();

                if ($otherCurrencyBalance && $otherCurrencyBalance->currency !== $currency) {
                    throw ValidationException::withMessages([
                        'currency' => [__('Stock balance already exists for this product in currency [:currency]. Multi-currency valuation for the same product and warehouse is not allowed.', [
                            'currency' => $otherCurrencyBalance->currency,
                        ])],
                    ]);
                }

                $balance = StockBalance::query()->create([
                    'warehouse_id' => $resolvedWarehouseId,
                    'product_id' => $productId,
                    'unit_of_measure_id' => $unitOfMeasureId,
                    'currency' => $currency,
                    'quantity_e6' => 0,
                    'valuation_amount_minor' => 0,
                    'avg_unit_cost_e6' => 0,
                    'lock_version' => 1,
                ]);
            }

            $newQtyE6 = $this->checkedAdd($balance->quantity_e6, $quantityE6, 'quantity');
            $newValueMinor = $this->checkedAdd($balance->valuation_amount_minor, $valueMinor, 'valuation');
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => 'transfer_in',
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => $quantityE6,
                'value_delta_minor' => $valueMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => null,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.transfer_in',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    public function recordAdjustment(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $quantityDeltaE6,
        ?int $unitCostMinor,
        string $fiscalYearId,
        string $financialPeriodId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityDeltaE6, $unitCostMinor, $financialPeriodId, $actorId, $warehouseId
        ): StockMovementLedger {
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', 'adjustment')
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($quantityDeltaE6 === 0) {
                throw ValidationException::withMessages(['quantity' => [__('Adjustment quantity delta must not be zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);
            $branchId = $this->branchIdForWarehouse($resolvedWarehouseId);
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset', $branchId);
            $gainAccount = $this->mappingService->getAccount('inventory_adjustment_gain', $branchId);
            $lossAccount = $this->mappingService->getAccount('inventory_adjustment_loss', $branchId);

            if ($inventoryAccount->currency !== $currency || $gainAccount->currency !== $currency || $lossAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped GL account currencies must match movement currency.')]]);
            }

            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if ($quantityDeltaE6 > 0) {
                if (! $unitCostMinor || $unitCostMinor <= 0) {
                    $unitCostMinor = $balance && $balance->avg_unit_cost_e6 > 0 ? (int) $balance->avg_unit_cost_e6 : null;
                }

                if (! $unitCostMinor || $unitCostMinor <= 0) {
                    throw ValidationException::withMessages(['unit_cost' => [__('Positive stock adjustments require a positive unit cost.')]]);
                }

                $valueDeltaMinor = $this->receiptValueMinor($quantityDeltaE6, $unitCostMinor);

                if (! $balance) {
                    $otherCurrencyBalance = StockBalance::query()
                        ->where('warehouse_id', $resolvedWarehouseId)
                        ->where('product_id', $productId)
                        ->first();

                    if ($otherCurrencyBalance && $otherCurrencyBalance->currency !== $currency) {
                        throw ValidationException::withMessages([
                            'currency' => [__('Stock balance already exists for this product in currency [:currency]. Multi-currency valuation for the same product and warehouse is not allowed.', [
                                'currency' => $otherCurrencyBalance->currency,
                            ])],
                        ]);
                    }

                    $balance = StockBalance::query()->create([
                        'warehouse_id' => $resolvedWarehouseId,
                        'product_id' => $productId,
                        'unit_of_measure_id' => $unitOfMeasureId,
                        'currency' => $currency,
                        'quantity_e6' => 0,
                        'valuation_amount_minor' => 0,
                        'avg_unit_cost_e6' => 0,
                        'lock_version' => 1,
                    ]);
                }

                $newQtyE6 = $this->checkedAdd($balance->quantity_e6, $quantityDeltaE6, 'quantity');
                $newValueMinor = $this->checkedAdd($balance->valuation_amount_minor, $valueDeltaMinor, 'valuation');
                $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

                $journalDebitAccount = $inventoryAccount;
                $journalCreditAccount = $gainAccount;
                $debitMemo = 'Inventory Adjustment Increase';
                $creditMemo = 'Inventory Adjustment Gain';
            } else {
                $absoluteQuantityE6 = abs($quantityDeltaE6);

                if (! $balance || $balance->quantity_e6 < $absoluteQuantityE6) {
                    $available = $balance ? $balance->quantity_e6 : 0;
                    $wholeAvail = intdiv($available, self::QUANTITY_SCALE);
                    $fracAvail = sprintf('%06d', abs($available % self::QUANTITY_SCALE));
                    throw ValidationException::withMessages([
                        'stock' => [__('Insufficient stock balance for adjustment. Available: :available.', [
                            'available' => "{$wholeAvail}.{$fracAvail}",
                        ])],
                    ]);
                }

                $absoluteValueMinor = $this->issueValueMinor($absoluteQuantityE6, $balance);
                $valueDeltaMinor = -$absoluteValueMinor;
                $newQtyE6 = $balance->quantity_e6 - $absoluteQuantityE6;
                $newValueMinor = $balance->valuation_amount_minor - $absoluteValueMinor;
                $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

                $journalDebitAccount = $lossAccount;
                $journalCreditAccount = $inventoryAccount;
                $debitMemo = 'Inventory Adjustment Loss';
                $creditMemo = 'Inventory Adjustment Decrease';
            }

            $balance->update([
                'quantity_e6' => $newQtyE6,
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            $journalAmountMinor = abs($valueDeltaMinor);

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $movementDate,
                'financial_period_id' => $financialPeriodId,
                'branch_id' => $branchId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => "Inventory Adjustment - {$sourceType} #{$sourceId}",
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $journalDebitAccount->id,
                'branch_id' => $branchId,
                'memo' => $debitMemo,
                'debit_minor' => $journalAmountMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $journalAmountMinor,
                'credit_txn_minor' => 0,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $journalCreditAccount->id,
                'branch_id' => $branchId,
                'memo' => $creditMemo,
                'debit_minor' => 0,
                'credit_minor' => $journalAmountMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $journalAmountMinor,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => 'adjustment',
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => $quantityDeltaE6,
                'value_delta_minor' => $valueDeltaMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $newQtyE6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => $postedJournal->id,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.adjustment',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    public function calculateIssueCostForReturn(
        string $sourceType,
        string $sourceLineId,
        int $returnQuantityE6,
    ): int {
        $movementType = $sourceType === 'sales_return' ? 'issue' : 'receipt';

        /** @var StockMovementLedger|null $originalMovement */
        $originalMovement = StockMovementLedger::query()
            ->where('source_line_id', $sourceLineId)
            ->where('movement_type', $movementType)
            ->first();

        if (! $originalMovement) {
            throw ValidationException::withMessages([
                'source_line_id' => [__('No original :movement_type movement found for source line [:source_line_id].', [
                    'movement_type' => $movementType,
                    'source_line_id' => $sourceLineId,
                ])],
            ]);
        }

        $originalQuantityE6 = abs($originalMovement->quantity_delta_e6);

        if ($returnQuantityE6 <= 0) {
            throw ValidationException::withMessages(['quantity' => [__('Return quantity must be greater than zero.')]]);
        }

        if ($returnQuantityE6 > $originalQuantityE6) {
            throw ValidationException::withMessages(['quantity' => [__('Return quantity cannot exceed original quantity.')]]);
        }

        $originalValueMinor = abs($originalMovement->value_delta_minor);

        if ($returnQuantityE6 === $originalQuantityE6) {
            return $originalValueMinor;
        }

        $this->assertProductWithinIntegerRange($returnQuantityE6, $originalValueMinor, 'stock');

        return intdiv($returnQuantityE6 * $originalValueMinor, $originalQuantityE6);
    }

    public function recordLandedCostValueAdjustment(
        string $sourceType,
        string $sourceId,
        string $sourceLineId,
        string $movementDate,
        string $productId,
        string $unitOfMeasureId,
        string $currency,
        int $valueMinor,
        string $journalEntryId,
        ?int $actorId = null,
        ?string $warehouseId = null,
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType,
            $sourceId,
            $sourceLineId,
            $movementDate,
            $productId,
            $unitOfMeasureId,
            $currency,
            $valueMinor,
            $journalEntryId,
            $actorId,
            $warehouseId,
        ): StockMovementLedger {
            /** @var StockMovementLedger|null $existing */
            $existing = StockMovementLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_line_id', $sourceLineId)
                ->where('movement_type', 'landed_cost')
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($valueMinor <= 0) {
                throw ValidationException::withMessages(['value' => [__('Landed cost value must be greater than zero.')]]);
            }

            $resolvedWarehouseId = $this->resolveWarehouseId($warehouseId);

            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $resolvedWarehouseId)
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance || $balance->quantity_e6 <= 0) {
                throw ValidationException::withMessages([
                    'stock' => [__('Landed cost can only be capitalized while stock remains in the target warehouse.')],
                ]);
            }

            $newValueMinor = $this->checkedAdd($balance->valuation_amount_minor, $valueMinor, 'valuation');
            $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $balance->quantity_e6);

            $balance->update([
                'valuation_amount_minor' => $newValueMinor,
                'avg_unit_cost_e6' => $avgCostE6,
                'lock_version' => $balance->lock_version + 1,
            ]);

            /** @var StockMovementLedger $movement */
            $movement = StockMovementLedger::query()->create([
                'warehouse_id' => $resolvedWarehouseId,
                'movement_date' => $movementDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'movement_type' => 'landed_cost',
                'product_id' => $productId,
                'unit_of_measure_id' => $unitOfMeasureId,
                'currency' => $currency,
                'quantity_delta_e6' => 0,
                'value_delta_minor' => $valueMinor,
                'unit_cost_e6' => $avgCostE6,
                'balance_quantity_e6' => $balance->quantity_e6,
                'balance_valuation_amount_minor' => $newValueMinor,
                'journal_entry_id' => $journalEntryId,
                'created_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_movement.landed_cost',
                entityType: 'stock_movement_ledger',
                entityId: $movement->id,
                before: null,
                after: $movement->toArray(),
            );

            return $movement;
        });
    }

    private function assertProductWithinIntegerRange(int $left, int $right, string $field): void
    {
        if ($left !== 0 && $right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw ValidationException::withMessages([$field => [__('Inventory calculation exceeds supported integer range.')]]);
        }
    }

    private function branchIdForWarehouse(string $warehouseId): ?string
    {
        /** @var Warehouse|null $warehouse */
        $warehouse = Warehouse::query()->where('id', $warehouseId)->first();

        return $warehouse?->branch_id ? (string) $warehouse->branch_id : null;
    }

    private function resolveWarehouseId(?string $warehouseId): string
    {
        if ($warehouseId) {
            /** @var Warehouse|null $warehouse */
            $warehouse = Warehouse::query()->where('id', $warehouseId)->first();
            if (! $warehouse || ! $warehouse->is_active) {
                throw ValidationException::withMessages(['warehouse_id' => [__('Selected warehouse is invalid or inactive.')]]);
            }

            return $warehouse->id;
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => ['en' => 'Main Warehouse', 'ar' => 'المخزن الرئيسي'],
                'warehouse_type' => 'standard',
                'is_default' => true,
                'is_active' => true,
                'lock_version' => 1,
            ],
        );

        return $warehouse->id;
    }
}
