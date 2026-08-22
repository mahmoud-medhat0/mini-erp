<?php

namespace App\Application\Inventory;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\JournalEntry;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
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
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $unitCostMinor, $financialPeriodId, $actorId
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
                throw ValidationException::withMessages(['quantity' => ['Receipt quantity must be greater than zero.']]);
            }

            if ($unitCostMinor <= 0) {
                throw ValidationException::withMessages(['unit_cost' => ['Receipt unit cost must be greater than zero.']]);
            }

            $lineValueMinor = $this->receiptValueMinor($quantityE6, $unitCostMinor);

            // Fetch mapped GL Accounts
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset');
            $grniAccount = $this->mappingService->getAccount('grni_clearing');

            if ($inventoryAccount->currency !== $currency || $grniAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => ['Mapped GL account currencies must match movement currency.']]);
            }

            // Lock stock balance row
            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                // Verify no balance exists with another currency
                $otherCurrencyBalance = StockBalance::query()
                    ->where('product_id', $productId)
                    ->first();

                if ($otherCurrencyBalance && $otherCurrencyBalance->currency !== $currency) {
                    throw ValidationException::withMessages(['currency' => ["Stock balance already exists for this product in currency [{$otherCurrencyBalance->currency}]. Multi-currency valuation for the same product is not allowed."]]);
                }

                $balance = StockBalance::query()->create([
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
    ): StockMovementLedger {
        return DB::transaction(function () use (
            $sourceType, $sourceId, $sourceLineId, $movementDate, $productId,
            $unitOfMeasureId, $currency, $quantityE6, $financialPeriodId, $actorId
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
                throw ValidationException::withMessages(['quantity' => ['Issue quantity must be greater than zero.']]);
            }

            // Fetch mapped GL Accounts
            $cogsAccount = $this->mappingService->getAccount('cogs');
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset');

            if ($cogsAccount->currency !== $currency || $inventoryAccount->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => ['Mapped GL account currencies must match movement currency.']]);
            }

            // Lock stock balance row
            /** @var StockBalance|null $balance */
            $balance = StockBalance::query()
                ->where('product_id', $productId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance || $balance->quantity_e6 < $quantityE6) {
                $available = $balance ? $balance->quantity_e6 : 0;
                $wholeAvail = intdiv($available, 1000000);
                $fracAvail = sprintf('%06d', abs($available % 1000000));
                throw ValidationException::withMessages([
                    'stock' => ["Insufficient stock balance for product. Available: {$wholeAvail}.{$fracAvail}."],
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
            throw ValidationException::withMessages(['unit_cost' => ['Quantity and unit cost result in fractional minor units.']]);
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
            throw ValidationException::withMessages([$field => ['Inventory calculation exceeds supported integer range.']]);
        }

        return $left + $right;
    }

    private function assertProductWithinIntegerRange(int $left, int $right, string $field): void
    {
        if ($left !== 0 && $right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw ValidationException::withMessages([$field => ['Inventory calculation exceeds supported integer range.']]);
        }
    }
}
