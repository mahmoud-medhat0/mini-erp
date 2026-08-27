<?php

namespace App\Application\Purchasing;

use App\Domain\Audit\AuditLogger;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'confirmed', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, ?int $actorId = null): PurchaseOrder
    {
        $headerData = $this->validateHeader($data);
        $linesData = $this->validateLines($data['lines'] ?? []);

        return DB::transaction(function () use ($headerData, $linesData, $actorId): PurchaseOrder {
            $subtotalMinor = 0;
            foreach ($linesData as $line) {
                $subtotalMinor += $line['line_total_minor'];
            }
            $totalMinor = $subtotalMinor;

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->create([
                ...$headerData,
                'status' => 'draft',
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $totalMinor,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($linesData as $index => $line) {
                $purchaseOrder->lines()->create([
                    'line_no' => $index + 1,
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            $purchaseOrder->load(['supplier', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_order.create',
                entityType: 'purchase_order',
                entityId: $purchaseOrder->id,
                before: null,
                after: $purchaseOrder->toArray(),
            );

            return $purchaseOrder;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()->with(['lines'])->findOrFail($id);

        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => [__('Only draft purchase orders can be updated.')]]);
        }

        if (isset($data['lock_version']) && (int) $data['lock_version'] !== $purchaseOrder->lock_version) {
            throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
        }

        $headerData = $this->validateHeader($data, $purchaseOrder);
        $linesData = $this->validateLines($data['lines'] ?? []);

        return DB::transaction(function () use ($purchaseOrder, $headerData, $linesData, $actorId): PurchaseOrder {
            $before = $purchaseOrder->toArray();

            $subtotalMinor = 0;
            foreach ($linesData as $line) {
                $subtotalMinor += $line['line_total_minor'];
            }
            $totalMinor = $subtotalMinor;

            $purchaseOrder->update([
                ...$headerData,
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $totalMinor,
                'updated_by' => $actorId,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ]);

            $purchaseOrder->lines()->delete();

            foreach ($linesData as $index => $line) {
                $purchaseOrder->lines()->create([
                    'line_no' => $index + 1,
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            $purchaseOrder->load(['supplier', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_order.update',
                entityType: 'purchase_order',
                entityId: $purchaseOrder->id,
                before: $before,
                after: $purchaseOrder->toArray(),
            );

            return $purchaseOrder;
        });
    }

    public function submit(string $id, ?int $actorId = null): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()->with(['lines'])->findOrFail($id);

        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => [__('Only draft purchase orders can be submitted.')]]);
        }

        if ($purchaseOrder->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('Cannot submit a purchase order without line items.')]]);
        }

        return DB::transaction(function () use ($purchaseOrder, $actorId): PurchaseOrder {
            $before = $purchaseOrder->toArray();

            $purchaseOrder->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_order.submit',
                entityType: 'purchase_order',
                entityId: $purchaseOrder->id,
                before: $before,
                after: $purchaseOrder->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $purchaseOrder->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function confirm(string $id, ?int $actorId = null): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()->with(['lines'])->findOrFail($id);

        // Idempotency replay check
        if ($purchaseOrder->status === 'confirmed') {
            return $purchaseOrder->load(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        }

        if (! in_array($purchaseOrder->status, ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages(['status' => [__('Only draft or submitted purchase orders can be confirmed.')]]);
        }

        if ($purchaseOrder->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('Cannot confirm a purchase order without line items.')]]);
        }

        return DB::transaction(function () use ($purchaseOrder, $actorId): PurchaseOrder {
            $before = $purchaseOrder->toArray();

            $number = $purchaseOrder->number;
            if (! $number) {
                $orderYear = Carbon::parse($purchaseOrder->order_date)->format('Y');
                $seq = $this->numberAllocator->nextValue('purchase.order');
                $number = 'PO-'.$orderYear.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $purchaseOrder->update([
                'number' => $number,
                'status' => 'confirmed',
                'confirmed_by' => $actorId,
                'confirmed_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_order.confirm',
                entityType: 'purchase_order',
                entityId: $purchaseOrder->id,
                before: $before,
                after: $purchaseOrder->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $purchaseOrder->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()->findOrFail($id);

        if ($purchaseOrder->status === 'confirmed') {
            throw ValidationException::withMessages(['status' => [__('Confirmed purchase orders cannot be cancelled in this slice.')]]);
        }

        if ($purchaseOrder->status === 'cancelled') {
            return $purchaseOrder->load(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        }

        return DB::transaction(function () use ($purchaseOrder, $actorId): PurchaseOrder {
            $before = $purchaseOrder->toArray();

            $purchaseOrder->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'purchase_order.cancel',
                entityType: 'purchase_order',
                entityId: $purchaseOrder->id,
                before: $before,
                after: $purchaseOrder->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $purchaseOrder->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function validateHeader(array $data, ?PurchaseOrder $existingOrder = null): array
    {
        $supplierId = $data['supplier_id'] ?? $existingOrder?->supplier_id;
        if (! $supplierId) {
            throw ValidationException::withMessages(['supplier_id' => [__('Supplier is required.')]]);
        }

        /** @var Supplier|null $supplier */
        $supplier = Supplier::query()->find($supplierId);
        if (! $supplier || $supplier->status !== 'active') {
            throw ValidationException::withMessages(['supplier_id' => [__('Selected Supplier is invalid or inactive.')]]);
        }

        $currency = $data['currency'] ?? $existingOrder?->currency;
        if (! $currency) {
            throw ValidationException::withMessages(['currency' => [__('Currency is required.')]]);
        }

        /** @var Currency|null $currencyModel */
        $currencyModel = Currency::query()->where('code', $currency)->first();
        if (! $currencyModel) {
            throw ValidationException::withMessages(['currency' => [__('Selected Currency is invalid.')]]);
        }

        $orderDate = $data['order_date'] ?? $existingOrder?->order_date;
        if (! $orderDate) {
            throw ValidationException::withMessages(['order_date' => [__('Order date is required.')]]);
        }

        $expectedReceiptDate = $data['expected_receipt_date'] ?? $existingOrder?->expected_receipt_date;
        if ($expectedReceiptDate && Carbon::parse($expectedReceiptDate)->lt(Carbon::parse($orderDate))) {
            throw ValidationException::withMessages(['expected_receipt_date' => [__('Expected receipt date must be on or after order date.')]]);
        }

        $fxRateE6 = (int) ($data['fx_rate_e6'] ?? $existingOrder?->fx_rate_e6 ?? 1000000);
        if ($fxRateE6 <= 0) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be a positive integer.')]]);
        }

        return [
            'supplier_id' => $supplierId,
            'order_date' => $orderDate,
            'expected_receipt_date' => $expectedReceiptDate,
            'currency' => $currency,
            'fx_rate_e6' => $fxRateE6,
            'reference' => $data['reference'] ?? $existingOrder?->reference,
            'notes' => $data['notes'] ?? $existingOrder?->notes,
        ];
    }

    private function validateLines(array $lines): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => [__('At least one order line is required.')]]);
        }

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $productId = $line['product_id'] ?? null;
            if (! $productId) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Product is required on line :line.', ['line' => $lineIndex])]]);
            }

            /** @var Product|null $product */
            $product = Product::query()->find($productId);
            if (! $product || $product->status !== 'active' || ! $product->is_purchase_enabled) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Selected Product on line :line is invalid, inactive, or not purchase-enabled.', ['line' => $lineIndex])]]);
            }

            $uomId = $line['unit_of_measure_id'] ?? $product->unit_of_measure_id;
            /** @var UnitOfMeasure|null $uom */
            $uom = UnitOfMeasure::query()->find($uomId);
            if (! $uom || ! $uom->is_active) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Unit of Measure on line :line is invalid or inactive.', ['line' => $lineIndex])]]);
            }

            if ($uomId !== $product->unit_of_measure_id) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Unit of Measure on line :line must match product default UOM.', ['line' => $lineIndex])]]);
            }

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            $unitPriceMinor = (int) ($line['unit_price_minor'] ?? 0);

            $lineTotalMinor = $this->calculateLineTotalMinor($quantityE6, $unitPriceMinor, $index);

            $validatedLines[] = [
                'product_id' => $productId,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? null,
                'quantity_e6' => $quantityE6,
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => $lineTotalMinor,
            ];
        }

        return $validatedLines;
    }

    private function calculateLineTotalMinor(int $quantityE6, int $unitPriceMinor, int $lineIndex): int
    {
        if ($quantityE6 <= 0) {
            throw ValidationException::withMessages(["lines.{$lineIndex}.quantity_e6" => [__('Quantity on line :line must be greater than zero.', ['line' => $lineIndex + 1])]]);
        }

        if ($unitPriceMinor <= 0) {
            throw ValidationException::withMessages(["lines.{$lineIndex}.unit_price_minor" => [__('Unit price on line :line must be greater than zero.', ['line' => $lineIndex + 1])]]);
        }

        if ($quantityE6 > intdiv(PHP_INT_MAX, $unitPriceMinor)) {
            throw ValidationException::withMessages(["lines.{$lineIndex}.quantity_e6" => [__('Quantity and unit price product exceeds maximum integer capacity on line :line.', ['line' => $lineIndex + 1])]]);
        }

        $product = $quantityE6 * $unitPriceMinor;

        if ($product % 1000000 !== 0) {
            throw ValidationException::withMessages(["lines.{$lineIndex}.quantity_e6" => [__('Line total produces a fractional minor unit and must be an exact integer minor amount.')]]);
        }

        return intdiv($product, 1000000);
    }
}
