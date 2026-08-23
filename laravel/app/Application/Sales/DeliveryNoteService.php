<?php

namespace App\Application\Sales;

use App\Application\Accounting\PeriodGuard;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Domain\Audit\AuditLogger;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\FinancialPeriod;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteService
{
    public const ALLOWED_STATUSES = ['draft', 'confirmed', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): DeliveryNote
    {
        return DB::transaction(function () use ($data, $actorId): DeliveryNote {
            $salesOrderId = $data['sales_order_id'] ?? null;
            if (! $salesOrderId) {
                throw ValidationException::withMessages(['sales_order_id' => ['Sales Order is required.']]);
            }

            /** @var SalesOrder|null $salesOrder */
            $salesOrder = SalesOrder::query()->where('id', $salesOrderId)->lockForUpdate()->first();
            if (! $salesOrder || $salesOrder->status !== 'confirmed') {
                throw ValidationException::withMessages(['sales_order_id' => ['Delivery Notes can only be created for confirmed Sales Orders.']]);
            }

            $deliveryDate = $data['delivery_date'] ?? null;
            if (! $deliveryDate) {
                throw ValidationException::withMessages(['delivery_date' => ['Delivery date is required.']]);
            }

            $validatedLines = $this->validateAndLockFulfillmentLines($salesOrder, $data['lines'] ?? []);

            /** @var DeliveryNote $deliveryNote */
            $deliveryNote = DeliveryNote::query()->create([
                'sales_order_id' => $salesOrder->id,
                'delivery_date' => $deliveryDate,
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($validatedLines as $index => $line) {
                $deliveryNote->lines()->create([
                    'line_no' => $index + 1,
                    'sales_order_line_id' => $line['sales_order_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                ]);
            }

            $deliveryNote->load(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'delivery_note.create',
                entityType: 'delivery_note',
                entityId: $deliveryNote->id,
                before: null,
                after: $deliveryNote->toArray(),
            );

            return $deliveryNote;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): DeliveryNote
    {
        return DB::transaction(function () use ($id, $data, $actorId): DeliveryNote {
            /** @var DeliveryNote $deliveryNote */
            $deliveryNote = DeliveryNote::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($deliveryNote->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft delivery notes can be updated.']]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $deliveryNote->lock_version) {
                throw ValidationException::withMessages(['lock_version' => ['The record has been modified by another user. Please refresh and try again.']]);
            }

            /** @var SalesOrder $salesOrder */
            $salesOrder = SalesOrder::query()->where('id', $deliveryNote->sales_order_id)->lockForUpdate()->firstOrFail();

            $deliveryDate = $data['delivery_date'] ?? $deliveryNote->delivery_date;
            $validatedLines = $this->validateAndLockFulfillmentLines($salesOrder, $data['lines'] ?? [], $deliveryNote->id);

            $before = $deliveryNote->toArray();

            $deliveryNote->update([
                'delivery_date' => $deliveryDate,
                'reference' => $data['reference'] ?? $deliveryNote->reference,
                'notes' => $data['notes'] ?? $deliveryNote->notes,
                'updated_by' => $actorId,
                'lock_version' => $deliveryNote->lock_version + 1,
            ]);

            $deliveryNote->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $deliveryNote->lines()->create([
                    'line_no' => $index + 1,
                    'sales_order_line_id' => $line['sales_order_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                ]);
            }

            $deliveryNote->load(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'delivery_note.update',
                entityType: 'delivery_note',
                entityId: $deliveryNote->id,
                before: $before,
                after: $deliveryNote->toArray(),
            );

            return $deliveryNote;
        });
    }

    public function confirm(string $id, ?int $actorId = null): DeliveryNote
    {
        return DB::transaction(function () use ($id, $actorId): DeliveryNote {
            /** @var DeliveryNote $deliveryNote */
            $deliveryNote = DeliveryNote::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($deliveryNote->status === 'confirmed') {
                return $deliveryNote->load(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            if ($deliveryNote->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft delivery notes can be confirmed.']]);
            }

            if ($deliveryNote->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['Cannot confirm a delivery note without line items.']]);
            }

            /** @var SalesOrder $salesOrder */
            $salesOrder = SalesOrder::query()->where('id', $deliveryNote->sales_order_id)->lockForUpdate()->firstOrFail();

            // Re-check fulfillment quantities under transaction lock before confirming
            $linesArray = $deliveryNote->lines->map(fn ($l) => [
                'sales_order_line_id' => $l->sales_order_line_id,
                'quantity_e6' => $l->quantity_e6,
            ])->toArray();

            $this->validateAndLockFulfillmentLines($salesOrder, $linesArray, $deliveryNote->id);

            // Process stock line inventory costing if stock products are present
            $deliveryNote->load(['lines.product']);
            $hasStockLines = $deliveryNote->lines->contains(fn ($line) => $line->product && $line->product->type === 'stock');

            if ($hasStockLines) {
                $period = $this->resolveFinancialPeriodForDate($deliveryNote->delivery_date);

                foreach ($deliveryNote->lines as $line) {
                    if ($line->product && $line->product->type === 'stock') {
                        $this->inventoryService->recordIssue(
                            sourceType: 'delivery_note',
                            sourceId: $deliveryNote->id,
                            sourceLineId: $line->id,
                            movementDate: $deliveryNote->delivery_date,
                            productId: $line->product_id,
                            unitOfMeasureId: $line->unit_of_measure_id,
                            currency: $salesOrder->currency,
                            quantityE6: $line->quantity_e6,
                            fiscalYearId: $period->fiscal_year_id,
                            financialPeriodId: $period->id,
                            actorId: $actorId,
                        );
                    }
                }
            }

            $before = $deliveryNote->toArray();

            $number = $deliveryNote->number;
            if (! $number) {
                $orderYear = Carbon::parse($deliveryNote->delivery_date)->format('Y');
                $seq = $this->numberAllocator->nextValue('delivery.note');
                $number = 'DN-'.$orderYear.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $deliveryNote->update([
                'number' => $number,
                'status' => 'confirmed',
                'confirmed_by' => $actorId,
                'confirmed_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $deliveryNote->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'delivery_note.confirm',
                entityType: 'delivery_note',
                entityId: $deliveryNote->id,
                before: $before,
                after: $deliveryNote->fresh(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $deliveryNote->fresh(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function resolveFinancialPeriodForDate(string $date): FinancialPeriod
    {
        return $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($date);
    }

    public function cancel(string $id, ?int $actorId = null): DeliveryNote
    {
        return DB::transaction(function () use ($id, $actorId): DeliveryNote {
            /** @var DeliveryNote $deliveryNote */
            $deliveryNote = DeliveryNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($deliveryNote->status === 'confirmed') {
                throw ValidationException::withMessages(['status' => ['Confirmed delivery notes cannot be cancelled in this slice.']]);
            }

            if ($deliveryNote->status === 'cancelled') {
                return $deliveryNote->load(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            $before = $deliveryNote->toArray();

            $deliveryNote->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $deliveryNote->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'delivery_note.cancel',
                entityType: 'delivery_note',
                entityId: $deliveryNote->id,
                before: $before,
                after: $deliveryNote->fresh(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $deliveryNote->fresh(['salesOrder.customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function validateAndLockFulfillmentLines(SalesOrder $salesOrder, array $lines, ?string $currentNoteId = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => ['At least one line item is required.']]);
        }

        // Lock SalesOrderLines in deterministic order
        $orderLines = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->id)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $solId = $line['sales_order_line_id'] ?? null;
            if (! $solId || ! isset($orderLines[$solId])) {
                throw ValidationException::withMessages(["lines.{$index}.sales_order_line_id" => ["Line {$lineIndex} does not belong to the selected Sales Order."]]);
            }

            /** @var SalesOrderLine $soLine */
            $soLine = $orderLines[$solId];

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => ["Quantity on line {$lineIndex} must be greater than zero."]]);
            }

            // Calculate cumulative delivered quantity from active (non-cancelled) DeliveryNotes
            $alreadyDeliveredQuery = DeliveryNoteLine::query()
                ->where('sales_order_line_id', $solId)
                ->whereHas('deliveryNote', function ($q): void {
                    $q->where('status', '!=', 'cancelled');
                });

            if ($currentNoteId) {
                $alreadyDeliveredQuery->where('delivery_note_id', '!=', $currentNoteId);
            }

            $alreadyDeliveredE6 = (int) $alreadyDeliveredQuery->sum('quantity_e6');

            if ($alreadyDeliveredE6 + $quantityE6 > $soLine->quantity_e6) {
                $maxAllowedE6 = $soLine->quantity_e6 - $alreadyDeliveredE6;
                $whole = intdiv($maxAllowedE6, 1000000);
                $fraction = str_pad((string) intdiv($maxAllowedE6 % 1000000, 10000), 2, '0', STR_PAD_LEFT);
                $maxAllowedDecimal = "{$whole}.{$fraction}";
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity_e6" => ["Delivered quantity on line {$lineIndex} exceeds remaining Sales Order quantity. Maximum remaining allowed is {$maxAllowedDecimal}."],
                ]);
            }

            $validatedLines[] = [
                'sales_order_line_id' => $solId,
                'product_id' => $soLine->product_id,
                'unit_of_measure_id' => $soLine->unit_of_measure_id,
                'description' => $line['description'] ?? $soLine->description,
                'quantity_e6' => $quantityE6,
            ];
        }

        return $validatedLines;
    }
}
