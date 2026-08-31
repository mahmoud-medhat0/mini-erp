<?php

namespace App\Application\Purchasing;

use App\Application\Accounting\PeriodGuard;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Inventory\WarehouseResolver;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public const ALLOWED_STATUSES = ['draft', 'confirmed', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly WarehouseResolver $warehouseResolver,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actorId): GoodsReceipt {
            $purchaseOrderId = $data['purchase_order_id'] ?? null;
            if (! $purchaseOrderId) {
                throw ValidationException::withMessages(['purchase_order_id' => [__('Purchase Order is required.')]]);
            }

            /** @var PurchaseOrder|null $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->where('id', $purchaseOrderId)->lockForUpdate()->first();
            if (! $purchaseOrder || $purchaseOrder->status !== 'confirmed') {
                throw ValidationException::withMessages(['purchase_order_id' => [__('Goods Receipts can only be created for confirmed Purchase Orders.')]]);
            }

            $receiptDate = $data['receipt_date'] ?? null;
            if (! $receiptDate) {
                throw ValidationException::withMessages(['receipt_date' => [__('Receipt date is required.')]]);
            }

            $warehouse = $this->warehouseResolver->active($data['warehouse_id'] ?? null);
            $validatedLines = $this->validateAndLockFulfillmentLines($purchaseOrder, $data['lines'] ?? []);

            /** @var GoodsReceipt $goodsReceipt */
            $goodsReceipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'warehouse_id' => $warehouse->id,
                'receipt_date' => $receiptDate,
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($validatedLines as $index => $line) {
                $goodsReceipt->lines()->create([
                    'line_no' => $index + 1,
                    'purchase_order_line_id' => $line['purchase_order_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                ]);
            }

            $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'goods_receipt.create',
                entityType: 'goods_receipt',
                entityId: $goodsReceipt->id,
                before: null,
                after: $goodsReceipt->toArray(),
            );

            return $goodsReceipt;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($id, $data, $actorId): GoodsReceipt {
            /** @var GoodsReceipt $goodsReceipt */
            $goodsReceipt = GoodsReceipt::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($goodsReceipt->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft goods receipts can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $goodsReceipt->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->where('id', $goodsReceipt->purchase_order_id)->lockForUpdate()->firstOrFail();

            $receiptDate = $data['receipt_date'] ?? $goodsReceipt->receipt_date;
            $warehouse = $this->warehouseResolver->active($data['warehouse_id'] ?? $goodsReceipt->warehouse_id);
            $validatedLines = $this->validateAndLockFulfillmentLines($purchaseOrder, $data['lines'] ?? [], $goodsReceipt->id);

            $before = $goodsReceipt->toArray();

            $goodsReceipt->update([
                'warehouse_id' => $warehouse->id,
                'receipt_date' => $receiptDate,
                'reference' => $data['reference'] ?? $goodsReceipt->reference,
                'notes' => $data['notes'] ?? $goodsReceipt->notes,
                'updated_by' => $actorId,
                'lock_version' => $goodsReceipt->lock_version + 1,
            ]);

            $goodsReceipt->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $goodsReceipt->lines()->create([
                    'line_no' => $index + 1,
                    'purchase_order_line_id' => $line['purchase_order_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                ]);
            }

            $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'goods_receipt.update',
                entityType: 'goods_receipt',
                entityId: $goodsReceipt->id,
                before: $before,
                after: $goodsReceipt->toArray(),
            );

            return $goodsReceipt;
        });
    }

    public function confirm(string $id, ?int $actorId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($id, $actorId): GoodsReceipt {
            /** @var GoodsReceipt $goodsReceipt */
            $goodsReceipt = GoodsReceipt::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($goodsReceipt->status === 'confirmed') {
                return $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
            }

            if ($goodsReceipt->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft goods receipts can be confirmed.')]]);
            }

            if ($goodsReceipt->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Cannot confirm a goods receipt without line items.')]]);
            }

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->where('id', $goodsReceipt->purchase_order_id)->lockForUpdate()->firstOrFail();

            // Re-check fulfillment quantities under transaction lock before confirming
            $linesArray = $goodsReceipt->lines->map(fn ($l) => [
                'purchase_order_line_id' => $l->purchase_order_line_id,
                'quantity_e6' => $l->quantity_e6,
            ])->toArray();

            $this->validateAndLockFulfillmentLines($purchaseOrder, $linesArray, $goodsReceipt->id);

            // Process stock line inventory costing if stock products are present
            $goodsReceipt->load(['lines.product', 'lines.purchaseOrderLine']);
            $hasStockLines = $goodsReceipt->lines->contains(fn ($line) => $line->product && $line->product->type === 'stock');

            if ($hasStockLines) {
                $period = $this->resolveFinancialPeriodForDate($goodsReceipt->receipt_date);

                foreach ($goodsReceipt->lines as $line) {
                    if ($line->product && $line->product->type === 'stock') {
                        $poLine = $line->purchaseOrderLine ?? PurchaseOrderLine::query()->where('id', $line->purchase_order_line_id)->first();
                        $unitCostMinor = $poLine ? $poLine->unit_price_minor : 0;

                        $this->inventoryService->recordReceipt(
                            sourceType: 'goods_receipt',
                            sourceId: $goodsReceipt->id,
                            sourceLineId: $line->id,
                            movementDate: $goodsReceipt->receipt_date,
                            productId: $line->product_id,
                            unitOfMeasureId: $line->unit_of_measure_id,
                            currency: $purchaseOrder->currency,
                            quantityE6: $line->quantity_e6,
                            unitCostMinor: $unitCostMinor,
                            fiscalYearId: $period->fiscal_year_id,
                            financialPeriodId: $period->id,
                            actorId: $actorId,
                            warehouseId: (string) $goodsReceipt->warehouse_id,
                        );
                    }
                }
            }

            $before = $goodsReceipt->toArray();

            $number = $goodsReceipt->number;
            if (! $number) {
                $orderYear = Carbon::parse($goodsReceipt->receipt_date)->format('Y');
                $seq = $this->numberAllocator->nextValue('goods.receipt');
                $number = 'GRN-'.$orderYear.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $goodsReceipt->update([
                'number' => $number,
                'status' => 'confirmed',
                'confirmed_by' => $actorId,
                'confirmed_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $goodsReceipt->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'goods_receipt.confirm',
                entityType: 'goods_receipt',
                entityId: $goodsReceipt->id,
                before: $before,
                after: $goodsReceipt->fresh(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $goodsReceipt->fresh(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function resolveFinancialPeriodForDate(string $date): FinancialPeriod
    {
        return $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($date);
    }

    public function cancel(string $id, ?int $actorId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($id, $actorId): GoodsReceipt {
            /** @var GoodsReceipt $goodsReceipt */
            $goodsReceipt = GoodsReceipt::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($goodsReceipt->status === 'confirmed') {
                throw ValidationException::withMessages(['status' => [__('Confirmed goods receipts cannot be cancelled in this slice.')]]);
            }

            if ($goodsReceipt->status === 'cancelled') {
                return $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
            }

            $before = $goodsReceipt->toArray();

            $goodsReceipt->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $goodsReceipt->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'goods_receipt.cancel',
                entityType: 'goods_receipt',
                entityId: $goodsReceipt->id,
                before: $before,
                after: $goodsReceipt->fresh(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $goodsReceipt->fresh(['purchaseOrder.supplier', 'warehouse', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function validateAndLockFulfillmentLines(PurchaseOrder $purchaseOrder, array $lines, ?string $currentReceiptId = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => [__('At least one line item is required.')]]);
        }

        // Lock PurchaseOrderLines in deterministic order
        $orderLines = PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $polId = $line['purchase_order_line_id'] ?? null;
            if (! $polId || ! isset($orderLines[$polId])) {
                throw ValidationException::withMessages(["lines.{$index}.purchase_order_line_id" => [__('Line :line does not belong to the selected Purchase Order.', ['line' => $lineIndex])]]);
            }

            /** @var PurchaseOrderLine $poLine */
            $poLine = $orderLines[$polId];

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity on line :line must be greater than zero.', ['line' => $lineIndex])]]);
            }

            // Calculate cumulative received quantity from active (non-cancelled) GoodsReceipts
            $alreadyReceivedQuery = GoodsReceiptLine::query()
                ->where('purchase_order_line_id', $polId)
                ->whereHas('goodsReceipt', function ($q): void {
                    $q->where('status', '!=', 'cancelled');
                });

            if ($currentReceiptId) {
                $alreadyReceivedQuery->where('goods_receipt_id', '!=', $currentReceiptId);
            }

            $alreadyReceivedE6 = (int) $alreadyReceivedQuery->sum('quantity_e6');

            if ($alreadyReceivedE6 + $quantityE6 > $poLine->quantity_e6) {
                $maxAllowedE6 = $poLine->quantity_e6 - $alreadyReceivedE6;
                $whole = intdiv($maxAllowedE6, 1000000);
                $fraction = str_pad((string) intdiv($maxAllowedE6 % 1000000, 10000), 2, '0', STR_PAD_LEFT);
                $maxAllowedDecimal = "{$whole}.{$fraction}";
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity_e6" => [__('Received quantity on line :line exceeds remaining Purchase Order quantity. Maximum remaining allowed is :maximum.', ['line' => $lineIndex, 'maximum' => $maxAllowedDecimal])],
                ]);
            }

            $validatedLines[] = [
                'purchase_order_line_id' => $polId,
                'product_id' => $poLine->product_id,
                'unit_of_measure_id' => $poLine->unit_of_measure_id,
                'description' => $line['description'] ?? $poLine->description,
                'quantity_e6' => $quantityE6,
            ];
        }

        return $validatedLines;
    }
}
