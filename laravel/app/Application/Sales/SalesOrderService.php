<?php

namespace App\Application\Sales;

use App\Domain\Audit\AuditLogger;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'confirmed', 'cancelled'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
    ) {}

    public function create(array $data, int|string|null $actorId = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $actorId) {
            $validatedHeader = $this->validateHeader($data);
            $validatedLines = $this->validateLines($data['lines'] ?? []);

            $subtotalMinor = 0;
            foreach ($validatedLines as $line) {
                $subtotalMinor += $line['line_total_minor'];
            }
            $totalMinor = $subtotalMinor;

            /** @var SalesOrder $salesOrder */
            $salesOrder = SalesOrder::query()->create([
                'customer_id' => $validatedHeader['customer_id'],
                'order_date' => $validatedHeader['order_date'],
                'expected_delivery_date' => $validatedHeader['expected_delivery_date'],
                'currency' => $validatedHeader['currency'],
                'fx_rate_e6' => $validatedHeader['fx_rate_e6'],
                'status' => 'draft',
                'reference' => $validatedHeader['reference'],
                'notes' => $validatedHeader['notes'],
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $totalMinor,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $lineNo = 1;
            foreach ($validatedLines as $lineData) {
                SalesOrderLine::query()->create([
                    'sales_order_id' => $salesOrder->id,
                    'line_no' => $lineNo++,
                    'product_id' => $lineData['product_id'],
                    'unit_of_measure_id' => $lineData['unit_of_measure_id'],
                    'description' => $lineData['description'],
                    'quantity_e6' => $lineData['quantity_e6'],
                    'unit_price_minor' => $lineData['unit_price_minor'],
                    'line_total_minor' => $lineData['line_total_minor'],
                ]);
            }

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_order.create',
                entityType: 'sales_order',
                entityId: $salesOrder->id,
                before: null,
                after: $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): SalesOrder
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $salesOrder = $this->lockSalesOrder($id);

            if (isset($data['lock_version'])) {
                $this->assertCurrentVersion($salesOrder, (int) $data['lock_version']);
            }

            if ($salesOrder->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('Sales Order in status [:status] cannot be updated.', ['status' => $salesOrder->status])],
                ]);
            }

            $before = $salesOrder->toArray();

            $validatedHeader = $this->validateHeader($data, $salesOrder);
            $validatedLines = $this->validateLines($data['lines'] ?? []);

            $subtotalMinor = 0;
            foreach ($validatedLines as $line) {
                $subtotalMinor += $line['line_total_minor'];
            }
            $totalMinor = $subtotalMinor;

            $this->conditionalUpdateLockedOrder($salesOrder, 'draft', [
                'customer_id' => $validatedHeader['customer_id'],
                'order_date' => $validatedHeader['order_date'],
                'expected_delivery_date' => $validatedHeader['expected_delivery_date'],
                'currency' => $validatedHeader['currency'],
                'fx_rate_e6' => $validatedHeader['fx_rate_e6'],
                'reference' => $validatedHeader['reference'],
                'notes' => $validatedHeader['notes'],
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $totalMinor,
                'updated_by' => $actorId,
            ]);

            // Re-create lines
            $salesOrder->lines()->delete();

            $lineNo = 1;
            foreach ($validatedLines as $lineData) {
                SalesOrderLine::query()->create([
                    'sales_order_id' => $salesOrder->id,
                    'line_no' => $lineNo++,
                    'product_id' => $lineData['product_id'],
                    'unit_of_measure_id' => $lineData['unit_of_measure_id'],
                    'description' => $lineData['description'],
                    'quantity_e6' => $lineData['quantity_e6'],
                    'unit_price_minor' => $lineData['unit_price_minor'],
                    'line_total_minor' => $lineData['line_total_minor'],
                ]);
            }

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_order.update',
                entityType: 'sales_order',
                entityId: $salesOrder->id,
                before: $before,
                after: $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function submit(string $id, int|string|null $actorId = null): SalesOrder
    {
        return DB::transaction(function () use ($id, $actorId) {
            $salesOrder = $this->lockSalesOrder($id);

            if ($salesOrder->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('Sales Order in status [:status] cannot be submitted.', ['status' => $salesOrder->status])],
                ]);
            }

            if ($salesOrder->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => [__('Sales Order must have at least one line before submission.')],
                ]);
            }

            $before = $salesOrder->toArray();

            $this->transitionLockedOrder($salesOrder, 'draft', 'submitted', [
                'submitted_by' => $actorId,
                'submitted_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_order.submit',
                entityType: 'sales_order',
                entityId: $salesOrder->id,
                before: $before,
                after: $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function confirm(string $id, int|string|null $actorId = null): SalesOrder
    {
        return DB::transaction(function () use ($id, $actorId) {
            $salesOrder = $this->lockSalesOrder($id);

            // Idempotency check: if already confirmed, return without error
            if ($salesOrder->status === 'confirmed') {
                return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            if (! in_array($salesOrder->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('Sales Order in status [:status] cannot be confirmed.', ['status' => $salesOrder->status])],
                ]);
            }

            if ($salesOrder->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => [__('Sales Order must have at least one line before confirmation.')],
                ]);
            }

            $before = $salesOrder->toArray();

            if (! $salesOrder->number) {
                $salesOrder->number = $this->numberSequenceAllocator->nextNumber('sales.order', 'SO', $salesOrder->order_date);
            }

            $this->transitionLockedOrder($salesOrder, $salesOrder->status, 'confirmed', [
                'number' => $salesOrder->number,
                'confirmed_by' => $actorId,
                'confirmed_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_order.confirm',
                entityType: 'sales_order',
                entityId: $salesOrder->id,
                before: $before,
                after: $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function cancel(string $id, int|string|null $actorId = null): SalesOrder
    {
        return DB::transaction(function () use ($id, $actorId) {
            $salesOrder = $this->lockSalesOrder($id);

            if ($salesOrder->status === 'cancelled') {
                return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            if ($salesOrder->status === 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => [__('Confirmed Sales Orders cannot be cancelled in this slice.')],
                ]);
            }

            $before = $salesOrder->toArray();

            $this->transitionLockedOrder($salesOrder, $salesOrder->status, 'cancelled', [
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_order.cancel',
                entityType: 'sales_order',
                entityId: $salesOrder->id,
                before: $before,
                after: $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesOrder->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function lockSalesOrder(string $id): SalesOrder
    {
        /** @var SalesOrder $salesOrder */
        $salesOrder = SalesOrder::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();

        return $salesOrder->load('lines');
    }

    private function assertCurrentVersion(SalesOrder $salesOrder, int $expectedVersion): void
    {
        if ((int) $salesOrder->lock_version !== $expectedVersion) {
            $this->throwConcurrencyValidationException();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transitionLockedOrder(
        SalesOrder $salesOrder,
        string $expectedStatus,
        string $nextStatus,
        array $attributes,
    ): void {
        $this->conditionalUpdateLockedOrder($salesOrder, $expectedStatus, [
            ...$attributes,
            'status' => $nextStatus,
        ]);
    }

    /**
     * Persist against the exact state read under the row lock. The predicates are
     * a final guard against stale state if this method is reused outside that lock.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function conditionalUpdateLockedOrder(
        SalesOrder $salesOrder,
        string $expectedStatus,
        array $attributes,
    ): void {
        $expectedVersion = (int) $salesOrder->lock_version;

        $affected = SalesOrder::query()
            ->whereKey($salesOrder->getKey())
            ->where('status', $expectedStatus)
            ->where('lock_version', $expectedVersion)
            ->update([
                ...$attributes,
                'lock_version' => $expectedVersion + 1,
            ]);

        if ($affected !== 1) {
            $this->throwConcurrencyValidationException();
        }

        $salesOrder->refresh();
    }

    private function throwConcurrencyValidationException(): never
    {
        throw ValidationException::withMessages([
            'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
        ]);
    }

    private function validateHeader(array $data, ?SalesOrder $existingOrder = null): array
    {
        $customerId = $data['customer_id'] ?? $existingOrder?->customer_id;
        if (! $customerId) {
            throw ValidationException::withMessages(['customer_id' => [__('Customer is required.')]]);
        }

        /** @var Customer|null $customer */
        $customer = Customer::query()->find($customerId);
        if (! $customer || $customer->status !== 'active') {
            throw ValidationException::withMessages(['customer_id' => [__('Selected Customer is invalid or inactive.')]]);
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

        $expectedDeliveryDate = $data['expected_delivery_date'] ?? $existingOrder?->expected_delivery_date;
        if ($expectedDeliveryDate && Carbon::parse($expectedDeliveryDate)->lt(Carbon::parse($orderDate))) {
            throw ValidationException::withMessages(['expected_delivery_date' => [__('Expected delivery date must be on or after order date.')]]);
        }

        $fxRateE6 = (int) ($data['fx_rate_e6'] ?? $existingOrder?->fx_rate_e6 ?? 1000000);
        if ($fxRateE6 <= 0) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be a positive integer.')]]);
        }

        return [
            'customer_id' => $customerId,
            'order_date' => $orderDate,
            'expected_delivery_date' => $expectedDeliveryDate,
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
            if (! $product || $product->status !== 'active' || ! $product->is_sales_enabled) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Selected Product on line :line is invalid, inactive, or not sales-enabled.', ['line' => $lineIndex])]]);
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
