<?php

namespace App\Application\Sales;

use App\Domain\Audit\AuditLogger;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Models\SalesQuotationLine;
use App\Models\UnitOfMeasure;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 27 - Sales Quotations. A pre-commitment document ahead of the Sales
 * Order (per PHASE_25_GAP_CLOSURE_DECISION_PACK.md §6): draft -> submit ->
 * accept/reject -> (accepted quotations convert into a real Sales Order via
 * SalesOrderService, never posting anything themselves).
 */
class SalesQuotationService
{
    public const STATUSES = ['draft', 'submitted', 'accepted', 'rejected', 'expired', 'converted'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function create(array $data, int|string|null $actorId = null): SalesQuotation
    {
        return DB::transaction(function () use ($data, $actorId) {
            $header = $this->validateHeader($data);
            $lines = $this->validateLines($data['lines'] ?? []);

            $subtotalMinor = array_sum(array_column($lines, 'line_total_minor'));

            /** @var SalesQuotation $quotation */
            $quotation = SalesQuotation::query()->create([
                ...$header,
                'status' => 'draft',
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $subtotalMinor,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($quotation, $lines);

            $this->auditLogger->record($actorId, 'sales_quotation.create', 'sales_quotation', $quotation->id, after: $quotation->fresh($this->relations())->toArray());

            return $quotation->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): SalesQuotation
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $quotation = $this->lockQuotation($id);

            if (isset($data['lock_version'])) {
                $this->assertCurrentVersion($quotation, (int) $data['lock_version']);
            }

            if ($quotation->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Quotation in status [:status] cannot be updated.', ['status' => $quotation->status])]]);
            }

            $before = $quotation->toArray();
            $header = $this->validateHeader($data, $quotation);
            $lines = $this->validateLines($data['lines'] ?? []);
            $subtotalMinor = array_sum(array_column($lines, 'line_total_minor'));

            $this->conditionalUpdate($quotation, 'draft', [
                ...$header,
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $subtotalMinor,
                'updated_by' => $actorId,
            ]);

            $this->replaceLines($quotation, $lines);

            $this->auditLogger->record($actorId, 'sales_quotation.update', 'sales_quotation', $quotation->id, before: $before, after: $quotation->fresh($this->relations())->toArray());

            return $quotation->fresh($this->relations());
        });
    }

    public function submit(string $id, int|string|null $actorId = null): SalesQuotation
    {
        return DB::transaction(function () use ($id, $actorId) {
            $quotation = $this->lockQuotation($id);

            if ($quotation->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Quotation in status [:status] cannot be submitted.', ['status' => $quotation->status])]]);
            }
            if ($quotation->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Quotation must have at least one line before submission.')]]);
            }

            $before = $quotation->toArray();

            if (! $quotation->number) {
                $quotation->number = $this->numberSequenceAllocator->nextNumber('sales.quotation', 'QUO', $quotation->quotation_date);
            }

            $this->conditionalUpdate($quotation, 'draft', [
                'number' => $quotation->number,
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'sales_quotation.submit', 'sales_quotation', $quotation->id, before: $before, after: $quotation->fresh($this->relations())->toArray());

            return $quotation->fresh($this->relations());
        });
    }

    public function accept(string $id, int|string|null $actorId = null): SalesQuotation
    {
        return $this->decide($id, 'accepted', $actorId);
    }

    public function reject(string $id, int|string|null $actorId = null): SalesQuotation
    {
        return $this->decide($id, 'rejected', $actorId);
    }

    public function cancel(string $id, int|string|null $actorId = null): SalesQuotation
    {
        return DB::transaction(function () use ($id, $actorId) {
            $quotation = $this->lockQuotation($id);

            if (! in_array($quotation->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted quotations can be cancelled.')]]);
            }

            $before = $quotation->toArray();
            $this->conditionalUpdate($quotation, $quotation->status, [
                'status' => 'rejected',
                'decided_by' => $actorId,
                'decided_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'sales_quotation.cancel', 'sales_quotation', $quotation->id, before: $before, after: $quotation->fresh($this->relations())->toArray());

            return $quotation->fresh($this->relations());
        });
    }

    /**
     * Converts an accepted quotation into a real Sales Order by reusing
     * SalesOrderService::create() — the quotation never posts anything itself.
     */
    public function convertToSalesOrder(string $id, array $overrides, int|string|null $actorId = null): SalesQuotation
    {
        return DB::transaction(function () use ($id, $overrides, $actorId) {
            $quotation = $this->lockQuotation($id);

            if ($quotation->status !== 'accepted') {
                throw ValidationException::withMessages(['status' => [__('Only accepted quotations can be converted to a Sales Order.')]]);
            }

            $salesOrder = $this->salesOrderService->create([
                'customer_id' => $quotation->customer_id,
                'order_date' => $overrides['order_date'] ?? now()->format('Y-m-d'),
                'expected_delivery_date' => $overrides['expected_delivery_date'] ?? null,
                'currency' => $quotation->currency,
                'fx_rate_e6' => $quotation->fx_rate_e6,
                'reference' => $quotation->number,
                'notes' => $quotation->notes,
                'lines' => $quotation->lines->map(fn (SalesQuotationLine $line) => [
                    'product_id' => $line->product_id,
                    'unit_of_measure_id' => $line->unit_of_measure_id,
                    'description' => $line->description,
                    'quantity_e6' => $line->quantity_e6,
                    'unit_price_minor' => $line->unit_price_minor,
                ])->all(),
            ], $actorId);

            $before = $quotation->toArray();
            $this->conditionalUpdate($quotation, 'accepted', [
                'status' => 'converted',
                'converted_sales_order_id' => $salesOrder->id,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'sales_quotation.convert', 'sales_quotation', $quotation->id, before: $before, after: $quotation->fresh($this->relations())->toArray());

            return $quotation->fresh($this->relations());
        });
    }

    private function decide(string $id, string $decision, int|string|null $actorId): SalesQuotation
    {
        return DB::transaction(function () use ($id, $decision, $actorId) {
            $quotation = $this->lockQuotation($id);

            if ($quotation->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Only submitted quotations can be accepted or rejected.')]]);
            }

            $before = $quotation->toArray();
            $this->conditionalUpdate($quotation, 'submitted', [
                'status' => $decision,
                'decided_by' => $actorId,
                'decided_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, "sales_quotation.{$decision}", 'sales_quotation', $quotation->id, before: $before, after: $quotation->fresh($this->relations())->toArray());

            return $quotation->fresh($this->relations());
        });
    }

    private function replaceLines(SalesQuotation $quotation, array $lines): void
    {
        $quotation->lines()->delete();
        $lineNo = 1;
        foreach ($lines as $line) {
            SalesQuotationLine::query()->create([
                'sales_quotation_id' => $quotation->id,
                'line_no' => $lineNo++,
                ...$line,
            ]);
        }
    }

    private function lockQuotation(string $id): SalesQuotation
    {
        /** @var SalesQuotation $quotation */
        $quotation = SalesQuotation::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $quotation->load('lines');
    }

    private function assertCurrentVersion(SalesQuotation $quotation, int $expectedVersion): void
    {
        if ((int) $quotation->lock_version !== $expectedVersion) {
            $this->throwConcurrencyValidationException();
        }
    }

    private function conditionalUpdate(SalesQuotation $quotation, string $expectedStatus, array $attributes): void
    {
        $expectedVersion = (int) $quotation->lock_version;

        $affected = SalesQuotation::query()
            ->whereKey($quotation->getKey())
            ->where('status', $expectedStatus)
            ->where('lock_version', $expectedVersion)
            ->update([...$attributes, 'lock_version' => $expectedVersion + 1]);

        if ($affected !== 1) {
            $this->throwConcurrencyValidationException();
        }

        $quotation->refresh();
    }

    private function throwConcurrencyValidationException(): never
    {
        throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
    }

    private function validateHeader(array $data, ?SalesQuotation $existing = null): array
    {
        $customerId = $data['customer_id'] ?? $existing?->customer_id;
        if (! $customerId) {
            throw ValidationException::withMessages(['customer_id' => [__('Customer is required.')]]);
        }

        $customer = Customer::query()->find($customerId);
        if (! $customer || $customer->status !== 'active') {
            throw ValidationException::withMessages(['customer_id' => [__('Selected Customer is invalid or inactive.')]]);
        }

        $currency = $data['currency'] ?? $existing?->currency;
        if (! $currency || ! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected Currency is invalid.')]]);
        }

        $quotationDate = $data['quotation_date'] ?? $existing?->quotation_date;
        if (! $quotationDate) {
            throw ValidationException::withMessages(['quotation_date' => [__('Quotation date is required.')]]);
        }

        $validUntil = array_key_exists('valid_until', $data) ? $data['valid_until'] : $existing?->valid_until;
        if ($validUntil && Carbon::parse($validUntil)->lt(Carbon::parse($quotationDate))) {
            throw ValidationException::withMessages(['valid_until' => [__('Valid-until date must be on or after the quotation date.')]]);
        }

        $fxRateE6 = (int) ($data['fx_rate_e6'] ?? $existing?->fx_rate_e6 ?? 1000000);
        if ($fxRateE6 <= 0) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be a positive integer.')]]);
        }

        return [
            'customer_id' => $customerId,
            'quotation_date' => $quotationDate,
            'valid_until' => $validUntil,
            'currency' => $currency,
            'fx_rate_e6' => $fxRateE6,
            'reference' => $data['reference'] ?? $existing?->reference,
            'notes' => $data['notes'] ?? $existing?->notes,
        ];
    }

    private function validateLines(array $lines): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => [__('At least one quotation line is required.')]]);
        }

        $validated = [];
        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $productId = $line['product_id'] ?? null;
            if (! $productId) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Product is required on line :line.', ['line' => $lineIndex])]]);
            }

            $product = Product::query()->find($productId);
            if (! $product || $product->status !== 'active' || ! $product->is_sales_enabled) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Selected Product on line :line is invalid, inactive, or not sales-enabled.', ['line' => $lineIndex])]]);
            }

            $uomId = $line['unit_of_measure_id'] ?? $product->unit_of_measure_id;
            $uom = UnitOfMeasure::query()->find($uomId);
            if (! $uom || ! $uom->is_active) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Unit of Measure on line :line is invalid or inactive.', ['line' => $lineIndex])]]);
            }
            if ($uomId !== $product->unit_of_measure_id) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Unit of Measure on line :line must match product default UOM.', ['line' => $lineIndex])]]);
            }

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            $unitPriceMinor = (int) ($line['unit_price_minor'] ?? 0);

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity on line :line must be greater than zero.', ['line' => $lineIndex])]]);
            }
            if ($unitPriceMinor <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_price_minor" => [__('Unit price on line :line must be greater than zero.', ['line' => $lineIndex])]]);
            }
            if ($quantityE6 > intdiv(PHP_INT_MAX, $unitPriceMinor)) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity and unit price product exceeds maximum integer capacity on line :line.', ['line' => $lineIndex])]]);
            }

            $product = $quantityE6 * $unitPriceMinor;
            if ($product % 1_000_000 !== 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Line total produces a fractional minor unit and must be an exact integer minor amount.')]]);
            }

            $validated[] = [
                'product_id' => $productId,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? null,
                'quantity_e6' => $quantityE6,
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => intdiv($product, 1_000_000),
            ];
        }

        return $validated;
    }

    private function relations(): array
    {
        return ['customer', 'lines.product', 'lines.unitOfMeasure', 'convertedSalesOrder'];
    }
}
