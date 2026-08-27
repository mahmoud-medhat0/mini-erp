<?php

namespace App\Application\Purchasing;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Inventory\WarehouseResolver;
use App\Application\Support\CurrencyInput;
use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxPeriodGuard;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\StockBalance;
use App\Models\StockMovementLedger;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly WarehouseResolver $warehouseResolver,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
        private readonly TaxCalculationService $taxCalcService,
        private readonly TaxPeriodGuard $taxPeriodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $actorId): PurchaseReturn {
            $supplierId = $data['supplier_id'] ?? null;
            if (! $supplierId) {
                throw ValidationException::withMessages(['supplier_id' => [__('Supplier is required.')]]);
            }

            /** @var Supplier|null $supplier */
            $supplier = Supplier::query()->where('id', $supplierId)->first();
            if (! $supplier || $supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => [__('Supplier must be active.')]]);
            }

            $goodsReceiptId = $data['goods_receipt_id'] ?? null;
            if (! $goodsReceiptId) {
                throw ValidationException::withMessages(['goods_receipt_id' => [__('Goods Receipt is required.')]]);
            }

            /** @var GoodsReceipt|null $goodsReceipt */
            $goodsReceipt = GoodsReceipt::query()->with('purchaseOrder')->where('id', $goodsReceiptId)->lockForUpdate()->first();
            if (! $goodsReceipt || $goodsReceipt->status !== 'confirmed') {
                throw ValidationException::withMessages(['goods_receipt_id' => [__('Purchase Returns can only be created for confirmed Goods Receipts.')]]);
            }

            $grSupplierId = $goodsReceipt->purchaseOrder?->supplier_id;
            if ($grSupplierId && $grSupplierId !== $supplier->id) {
                throw ValidationException::withMessages(['supplier_id' => [__('Supplier must match the Goods Receipt supplier.')]]);
            }

            $currency = CurrencyInput::required($data['currency'] ?? null);
            $grCurrency = CurrencyInput::related($goodsReceipt->purchaseOrder?->currency, 'currency', 'Goods Receipt');
            if ($grCurrency !== $currency) {
                throw ValidationException::withMessages(['currency' => [__('Currency must match the Goods Receipt currency.')]]);
            }
            $warehouse = $this->warehouseResolver->active($data['warehouse_id'] ?? $goodsReceipt->warehouse_id ?? null);

            $returnDate = $data['return_date'] ?? null;
            if (! $returnDate) {
                throw ValidationException::withMessages(['return_date' => [__('Return date is required.')]]);
            }

            $period = $this->resolveFinancialPeriodForDate($returnDate);

            $supplierBillId = $data['supplier_bill_id'] ?? null;
            if ($supplierBillId) {
                /** @var SupplierBill|null $supplierBill */
                $supplierBill = SupplierBill::query()->where('id', $supplierBillId)->first();
                if (! $supplierBill || $supplierBill->supplier_id !== $supplier->id) {
                    throw ValidationException::withMessages(['supplier_bill_id' => [__('Supplier Bill does not belong to the selected supplier.')]]);
                }
                if ($supplierBill->currency !== $currency) {
                    throw ValidationException::withMessages(['currency' => [__('Currency must match the Supplier Bill currency.')]]);
                }
            }

            $validatedLines = $this->validateAndCalculateLines(
                $data['lines'] ?? [],
                $goodsReceipt,
                $supplierBillId,
                $returnDate
            );

            $taxAmountMinor = array_sum(array_column($validatedLines, 'tax_amount_minor'));

            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->create([
                'supplier_id' => $supplier->id,
                'goods_receipt_id' => $goodsReceipt->id,
                'warehouse_id' => $warehouse->id,
                'supplier_bill_id' => $supplierBillId,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'return_date' => $returnDate,
                'tax_amount_minor' => $taxAmountMinor,
                'status' => 'draft',
                'currency' => $currency,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($validatedLines as $index => $line) {
                $return->lines()->create([
                    'line_no' => $index + 1,
                    'goods_receipt_line_id' => $line['goods_receipt_line_id'],
                    'supplier_bill_line_id' => $line['supplier_bill_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'original_receipt_cost_minor' => $line['original_receipt_cost_minor'],
                    'stock_value_minor' => $line['stock_value_minor'],
                    'variance_minor' => $line['variance_minor'],
                    'tax_code_id' => $line['tax_code_id'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount_minor' => $line['tax_amount_minor'],
                    'gross_amount_minor' => $line['gross_amount_minor'],
                ]);
            }

            $return->load(['supplier', 'goodsReceipt', 'warehouse', 'lines.product', 'lines.unitOfMeasure', 'lines.taxCode']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_return.create',
                entityType: 'purchase_return',
                entityId: $return->id,
                before: null,
                after: $return->toArray(),
            );

            return $return;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $data, $actorId): PurchaseReturn {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($return->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft purchase returns can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $return->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $returnDate = $data['return_date'] ?? $return->return_date;
            $period = $this->resolveFinancialPeriodForDate($returnDate);

            $goodsReceipt = GoodsReceipt::query()->where('id', $return->goods_receipt_id)->lockForUpdate()->firstOrFail();
            $warehouse = $this->warehouseResolver->active($data['warehouse_id'] ?? $return->warehouse_id ?? $goodsReceipt->warehouse_id ?? null);
            $supplierBillId = $data['supplier_bill_id'] ?? $return->supplier_bill_id;

            $validatedLines = $this->validateAndCalculateLines(
                $data['lines'] ?? [],
                $goodsReceipt,
                $supplierBillId,
                $returnDate,
                $return->id
            );

            $taxAmountMinor = array_sum(array_column($validatedLines, 'tax_amount_minor'));

            $before = $return->toArray();

            $return->update([
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'warehouse_id' => $warehouse->id,
                'return_date' => $returnDate,
                'supplier_bill_id' => $supplierBillId,
                'tax_amount_minor' => $taxAmountMinor,
                'reason' => $data['reason'] ?? $return->reason,
                'notes' => $data['notes'] ?? $return->notes,
                'updated_by' => $actorId,
                'lock_version' => $return->lock_version + 1,
            ]);

            $return->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $return->lines()->create([
                    'line_no' => $index + 1,
                    'goods_receipt_line_id' => $line['goods_receipt_line_id'],
                    'supplier_bill_line_id' => $line['supplier_bill_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'original_receipt_cost_minor' => $line['original_receipt_cost_minor'],
                    'stock_value_minor' => $line['stock_value_minor'],
                    'variance_minor' => $line['variance_minor'],
                    'tax_code_id' => $line['tax_code_id'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount_minor' => $line['tax_amount_minor'],
                    'gross_amount_minor' => $line['gross_amount_minor'],
                ]);
            }

            $return->load(['supplier', 'goodsReceipt', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_return.update',
                entityType: 'purchase_return',
                entityId: $return->id,
                before: $before,
                after: $return->toArray(),
            );

            return $return;
        });
    }

    public function submit(string $id, ?int $actorId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $actorId): PurchaseReturn {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($return->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft purchase returns can be submitted.')]]);
            }

            if ($return->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => [__('Purchase return must have at least one line item before submitting.')]]);
            }

            $before = $return->toArray();

            $return->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $return->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_return.submit',
                entityType: 'purchase_return',
                entityId: $return->id,
                before: $before,
                after: $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function approve(string $id, ?int $actorId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $actorId): PurchaseReturn {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($return->status === 'approved') {
                return $return->load(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
            }

            if (! in_array($return->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted purchase returns can be approved.')]]);
            }

            if ($return->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => [__('Purchase return must have at least one line item before approving.')]]);
            }

            $before = $return->toArray();

            $return->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $return->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_return.approve',
                entityType: 'purchase_return',
                entityId: $return->id,
                before: $before,
                after: $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $actorId): PurchaseReturn {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($return->status === 'posted') {
                throw ValidationException::withMessages(['status' => [__('Posted purchase returns cannot be cancelled in this slice.')]]);
            }

            if ($return->status === 'cancelled') {
                return $return->load(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
            }

            $before = $return->toArray();

            $return->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $return->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_return.cancel',
                entityType: 'purchase_return',
                entityId: $return->id,
                before: $before,
                after: $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function post(string $id, ?int $actorId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $actorId): PurchaseReturn {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->with(['lines.product', 'lines.goodsReceiptLine', 'supplier'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($return->status === 'posted') {
                return $return->load(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure', 'journalEntry']);
            }

            if ($return->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved purchase returns can be posted.')]]);
            }

            if ($return->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Cannot post purchase return without line items.')]]);
            }

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $return->financial_period_id, (string) $return->return_date);
            $this->taxPeriodGuard->ensureDateNotFiled((string) $return->return_date);
            if ($period->fiscal_year_id !== $return->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => [__('Financial period does not belong to the return fiscal year.')]]);
            }

            $grniAccount = $this->mappingService->getAccount('grni_clearing');
            $inventoryAccount = $this->mappingService->getAccount('inventory_asset');

            if ($grniAccount->currency !== $return->currency || $inventoryAccount->currency !== $return->currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped GL account currencies must match return currency.')]]);
            }

            $number = $return->number;
            if (! $number) {
                $year = Carbon::parse($return->return_date)->format('Y');
                $seq = $this->numberAllocator->nextValue('purchase.return');
                $number = 'PRT-'.$year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $movements = [];
            $totalStockValueMinor = 0;

            foreach ($return->lines as $line) {
                $product = $line->product;
                if (! $product || $product->type !== 'stock') {
                    continue;
                }

                /** @var StockMovementLedger|null $existingMovement */
                $existingMovement = StockMovementLedger::query()
                    ->where('source_type', 'purchase_return')
                    ->where('source_line_id', $line->id)
                    ->where('movement_type', 'reversal')
                    ->first();

                if ($existingMovement) {
                    continue;
                }

                $quantityE6 = (int) $line->quantity_e6;
                $reversalValueMinor = (int) $line->stock_value_minor;

                /** @var StockBalance|null $balance */
                $balance = StockBalance::query()
                    ->where('warehouse_id', $return->warehouse_id)
                    ->where('product_id', $line->product_id)
                    ->where('currency', $return->currency)
                    ->lockForUpdate()
                    ->first();

                if (! $balance || $balance->quantity_e6 < $quantityE6) {
                    $available = $balance ? $balance->quantity_e6 : 0;
                    $wholeAvail = intdiv($available, 1000000);
                    $fracAvail = sprintf('%06d', abs($available % 1000000));
                    throw ValidationException::withMessages([
                        'stock' => [__('Insufficient stock balance for product. Available: :available.', ['available' => "{$wholeAvail}.{$fracAvail}"])],
                    ]);
                }

                $newQtyE6 = $balance->quantity_e6 - $quantityE6;
                $newValueMinor = $balance->valuation_amount_minor - $reversalValueMinor;
                $avgCostE6 = $this->averageUnitCostE6($newValueMinor, $newQtyE6);

                $balance->update([
                    'quantity_e6' => $newQtyE6,
                    'valuation_amount_minor' => $newValueMinor,
                    'avg_unit_cost_e6' => $avgCostE6,
                    'lock_version' => $balance->lock_version + 1,
                ]);

                $movements[] = [
                    'line' => $line,
                    'quantity_e6' => $quantityE6,
                    'value_minor' => $reversalValueMinor,
                    'avg_cost_e6' => $avgCostE6,
                    'balance_quantity_e6' => $newQtyE6,
                    'balance_valuation_amount_minor' => $newValueMinor,
                ];

                $totalStockValueMinor = $this->checkedAdd($totalStockValueMinor, $reversalValueMinor);
            }

            $before = $return->toArray();

            /** @var JournalEntry|null $postedJournal */
            $postedJournal = null;

            if ($totalStockValueMinor > 0) {
                /** @var JournalEntry $journalEntry */
                $journalEntry = JournalEntry::query()->create([
                    'entry_date' => $return->return_date,
                    'financial_period_id' => $return->financial_period_id,
                    'source_type' => 'purchase_return',
                    'source_id' => $return->id,
                    'description' => "Purchase Return {$number} - {$return->supplier->name}",
                    'currency' => $return->currency,
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
                    'account_id' => $grniAccount->id,
                    'memo' => "GRNI Clearing - Return {$number}",
                    'debit_minor' => $totalStockValueMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $totalStockValueMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $return->currency,
                    'fx_rate_e6' => 1000000,
                ]);

                $journalEntry->lines()->create([
                    'line_no' => 2,
                    'account_id' => $inventoryAccount->id,
                    'memo' => "Inventory Asset - Return {$number}",
                    'debit_minor' => 0,
                    'credit_minor' => $totalStockValueMinor,
                    'debit_txn_minor' => 0,
                    'credit_txn_minor' => $totalStockValueMinor,
                    'currency' => $return->currency,
                    'fx_rate_e6' => 1000000,
                ]);

                $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);
            }

            foreach ($movements as $movement) {
                /** @var StockMovementLedger $ledgerRow */
                $ledgerRow = StockMovementLedger::query()->create([
                    'warehouse_id' => $return->warehouse_id,
                    'movement_date' => $return->return_date,
                    'source_type' => 'purchase_return',
                    'source_id' => $return->id,
                    'source_line_id' => $movement['line']->id,
                    'movement_type' => 'reversal',
                    'product_id' => $movement['line']->product_id,
                    'unit_of_measure_id' => $movement['line']->unit_of_measure_id,
                    'currency' => $return->currency,
                    'quantity_delta_e6' => -$movement['quantity_e6'],
                    'value_delta_minor' => -$movement['value_minor'],
                    'unit_cost_e6' => $movement['avg_cost_e6'],
                    'balance_quantity_e6' => $movement['balance_quantity_e6'],
                    'balance_valuation_amount_minor' => $movement['balance_valuation_amount_minor'],
                    'journal_entry_id' => $postedJournal?->id,
                    'created_by' => $actorId,
                ]);

                $this->auditLogger->record(
                    actorId: $actorId,
                    action: 'stock_movement.reversal',
                    entityType: 'stock_movement_ledger',
                    entityId: $ledgerRow->id,
                    before: null,
                    after: $ledgerRow->toArray(),
                );
            }

            $return->number = $number;
            $return->status = 'posted';
            if ($postedJournal) {
                $return->journal_entry_id = $postedJournal->id;
            }
            $return->posted_by = $actorId;
            $return->posted_at = Carbon::now();
            $return->updated_by = $actorId;
            $return->lock_version = $return->lock_version + 1;
            $return->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_return.post',
                entityType: 'purchase_return',
                entityId: $return->id,
                before: $before,
                after: $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure', 'journalEntry'])->toArray(),
            );

            return $return->fresh(['supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure', 'journalEntry']);
        });
    }

    private function resolveFinancialPeriodForDate(string $date): FinancialPeriod
    {
        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereIn('status', ['open', 'reopened'])
            ->first();

        if (! $period) {
            throw ValidationException::withMessages(['return_date' => [__('No open financial period covers date :date.', ['date' => $date])]]);
        }

        return $period;
    }

    private function validateAndCalculateLines(array $lines, GoodsReceipt $goodsReceipt, ?string $supplierBillId = null, ?string $returnDate = null, ?string $currentReturnId = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => [__('At least one line item is required.')]]);
        }

        $grLineIds = [];
        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $grlId = $line['goods_receipt_line_id'] ?? null;
            if (! $grlId) {
                throw ValidationException::withMessages(["lines.{$index}.goods_receipt_line_id" => [__('Line :line must reference a Goods Receipt line.', ['line' => $lineIndex])]]);
            }
            $grLineIds[] = $grlId;
        }

        $grLines = GoodsReceiptLine::query()
            ->whereIn('id', array_values(array_unique($grLineIds)))
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $validatedLines = [];
        $requestedGrQuantitiesE6 = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $grlId = $line['goods_receipt_line_id'];

            /** @var GoodsReceiptLine|null $grLine */
            $grLine = $grLines->get($grlId);
            if (! $grLine || $grLine->goods_receipt_id !== $goodsReceipt->id) {
                throw ValidationException::withMessages(["lines.{$index}.goods_receipt_line_id" => [__('Line :line does not belong to the selected Goods Receipt.', ['line' => $lineIndex])]]);
            }

            $productId = $line['product_id'] ?? $grLine->product_id;
            if (! $productId) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Line :line product is required.', ['line' => $lineIndex])]]);
            }

            /** @var Product|null $product */
            $product = Product::query()->where('id', $productId)->first();
            if (! $product || $product->status !== 'active') {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Product on line :line is inactive or does not exist.', ['line' => $lineIndex])]]);
            }

            if ($grLine->product_id !== $product->id) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Product on line :line must match the selected Goods Receipt line.', ['line' => $lineIndex])]]);
            }

            $uomId = $line['unit_of_measure_id'] ?? $grLine->unit_of_measure_id;
            if ($grLine->unit_of_measure_id !== $uomId) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Unit of measure on line :line must match the selected Goods Receipt line.', ['line' => $lineIndex])]]);
            }

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity on line :line must be greater than zero.', ['line' => $lineIndex])]]);
            }

            $supplierBillLineId = $line['supplier_bill_line_id'] ?? null;
            if ($supplierBillLineId) {
                if (! $supplierBillId) {
                    throw ValidationException::withMessages(["lines.{$index}.supplier_bill_line_id" => [__('Line :line references a Supplier Bill line but no Supplier Bill was selected.', ['line' => $lineIndex])]]);
                }

                /** @var SupplierBillLine|null $supplierBillLine */
                $supplierBillLine = SupplierBillLine::query()->where('id', $supplierBillLineId)->first();
                if (! $supplierBillLine || $supplierBillLine->supplier_bill_id !== $supplierBillId) {
                    throw ValidationException::withMessages(["lines.{$index}.supplier_bill_line_id" => [__('Line :line does not belong to the selected Supplier Bill.', ['line' => $lineIndex])]]);
                }

                if ($supplierBillLine->product_id !== $product->id) {
                    throw ValidationException::withMessages(["lines.{$index}.supplier_bill_line_id" => [__('Product on line :line must match the selected Supplier Bill line.', ['line' => $lineIndex])]]);
                }

                if ($supplierBillLine->unit_of_measure_id !== $uomId) {
                    throw ValidationException::withMessages(["lines.{$index}.supplier_bill_line_id" => [__('Unit of measure on line :line must match the selected Supplier Bill line.', ['line' => $lineIndex])]]);
                }
            }

            $originalReceiptCostMinor = 0;
            $stockValueMinor = 0;
            $varianceMinor = 0;

            if ($product->type === 'stock') {
                $proportionalCostMinor = $this->inventoryService->calculateIssueCostForReturn(
                    sourceType: 'goods_receipt_line',
                    sourceLineId: (string) $grLine->id,
                    returnQuantityE6: $quantityE6,
                );

                $this->assertMultiplyWithinRange($proportionalCostMinor, 1000000, "lines.{$index}.quantity_e6");

                $unitReceiptCostMinor = intdiv($proportionalCostMinor * 1000000, $quantityE6);

                $this->assertMultiplyWithinRange($quantityE6, $unitReceiptCostMinor, "lines.{$index}.quantity_e6");

                $stockValueMinor = intdiv($quantityE6 * $unitReceiptCostMinor, 1000000);
                $originalReceiptCostMinor = $proportionalCostMinor;
                $varianceMinor = $proportionalCostMinor - $stockValueMinor;
            }

            $alreadyReturnedQuery = PurchaseReturnLine::query()
                ->where('goods_receipt_line_id', $grlId)
                ->whereHas('purchaseReturn', fn ($q) => $q->where('status', '!=', 'cancelled'));

            if ($currentReturnId) {
                $alreadyReturnedQuery->where('purchase_return_id', '!=', $currentReturnId);
            }

            $alreadyReturnedE6 = (int) $alreadyReturnedQuery->sum('quantity_e6');
            $requestedGrQuantitiesE6[$grlId] = ($requestedGrQuantitiesE6[$grlId] ?? 0) + $quantityE6;

            if ($alreadyReturnedE6 + $requestedGrQuantitiesE6[$grlId] > $grLine->quantity_e6) {
                $maxAllowedE6 = $grLine->quantity_e6 - $alreadyReturnedE6;
                $whole = intdiv($maxAllowedE6, 1000000);
                $fraction = str_pad((string) intdiv($maxAllowedE6 % 1000000, 10000), 2, '0', STR_PAD_LEFT);
                $maxAllowedDecimal = "{$whole}.{$fraction}";
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity_e6" => [__('Returned quantity on line :line exceeds remaining Goods Receipt line quantity. Maximum remaining allowed is :maximum.', [
                        'line' => $lineIndex,
                        'maximum' => $maxAllowedDecimal,
                    ])],
                ]);
            }

            $taxCodeId = null;
            $taxRateBps = 0;
            $taxAmountMinor = 0;

            if ($supplierBillLineId && isset($supplierBillLine) && $supplierBillLine->tax_code_id) {
                $taxCodeId = $supplierBillLine->tax_code_id;
                $taxRateBps = $supplierBillLine->tax_rate_bps;
                $taxResult = $this->taxCalcService->calculateTax($taxCodeId, $originalReceiptCostMinor, $returnDate ?? now()->format('Y-m-d'));
                $taxAmountMinor = $taxResult['tax_minor'];
            }

            $validatedLines[] = [
                'goods_receipt_line_id' => $grLine->id,
                'supplier_bill_line_id' => $supplierBillLineId,
                'product_id' => $product->id,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? $grLine->description,
                'quantity_e6' => $quantityE6,
                'original_receipt_cost_minor' => $originalReceiptCostMinor,
                'stock_value_minor' => $stockValueMinor,
                'variance_minor' => $varianceMinor,
                'tax_code_id' => $taxCodeId,
                'tax_rate_bps' => $taxRateBps,
                'tax_amount_minor' => $taxAmountMinor,
                'gross_amount_minor' => $originalReceiptCostMinor + $taxAmountMinor,
            ];
        }

        return $validatedLines;
    }

    private function averageUnitCostE6(int $valuationAmountMinor, int $quantityE6): int
    {
        if ($quantityE6 <= 0) {
            return 0;
        }

        $this->assertMultiplyWithinRange($valuationAmountMinor, 1000000, 'stock');

        return intdiv($valuationAmountMinor * 1000000, $quantityE6);
    }

    private function checkedAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw ValidationException::withMessages(['stock' => [__('Inventory calculation exceeds supported integer range.')]]);
        }

        return $left + $right;
    }

    private function assertMultiplyWithinRange(int $left, int $right, string $field): void
    {
        if ($left !== 0 && $right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw ValidationException::withMessages([$field => [__('Calculation exceeds supported integer range.')]]);
        }
    }
}
