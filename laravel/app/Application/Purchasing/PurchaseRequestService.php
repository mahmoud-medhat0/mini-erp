<?php

namespace App\Application\Purchasing;

use App\Domain\Audit\AuditLogger;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 27 - Purchase Requests. An internal pre-commitment document ahead of
 * the Purchase Order (per PHASE_25_GAP_CLOSURE_DECISION_PACK.md §6): draft ->
 * submit -> internal approve/reject (gated by the existing `purchasing.approve`
 * permission, same as Purchase Order confirmation) -> approved requests
 * convert into a real Purchase Order via PurchaseOrderService, never posting
 * anything themselves. Supplier and exact pricing are optional at request
 * time since the point of a request is often to ask before a supplier or
 * price is settled.
 */
class PurchaseRequestService
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'converted'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    public function create(array $data, int|string|null $actorId = null): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $actorId) {
            $header = $this->validateHeader($data);
            $lines = $this->validateLines($data['lines'] ?? []);
            $subtotalMinor = array_sum(array_column($lines, 'line_total_minor'));

            /** @var PurchaseRequest $request */
            $request = PurchaseRequest::query()->create([
                ...$header,
                'status' => 'draft',
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $subtotalMinor,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($request, $lines);

            $this->auditLogger->record($actorId, 'purchase_request.create', 'purchase_request', $request->id, after: $request->fresh($this->relations())->toArray());

            return $request->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): PurchaseRequest
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $request = $this->lockRequest($id);

            if (isset($data['lock_version'])) {
                $this->assertCurrentVersion($request, (int) $data['lock_version']);
            }
            if ($request->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Purchase request in status [:status] cannot be updated.', ['status' => $request->status])]]);
            }

            $before = $request->toArray();
            $header = $this->validateHeader($data, $request);
            $lines = $this->validateLines($data['lines'] ?? []);
            $subtotalMinor = array_sum(array_column($lines, 'line_total_minor'));

            $this->conditionalUpdate($request, 'draft', [
                ...$header,
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $subtotalMinor,
                'updated_by' => $actorId,
            ]);

            $this->replaceLines($request, $lines);

            $this->auditLogger->record($actorId, 'purchase_request.update', 'purchase_request', $request->id, before: $before, after: $request->fresh($this->relations())->toArray());

            return $request->fresh($this->relations());
        });
    }

    public function submit(string $id, int|string|null $actorId = null): PurchaseRequest
    {
        return DB::transaction(function () use ($id, $actorId) {
            $request = $this->lockRequest($id);

            if ($request->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Purchase request in status [:status] cannot be submitted.', ['status' => $request->status])]]);
            }
            if ($request->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Purchase request must have at least one line before submission.')]]);
            }

            $before = $request->toArray();

            if (! $request->number) {
                $request->number = $this->numberSequenceAllocator->nextNumber('purchasing.request', 'PR', $request->requested_date);
            }

            $this->conditionalUpdate($request, 'draft', [
                'number' => $request->number,
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'purchase_request.submit', 'purchase_request', $request->id, before: $before, after: $request->fresh($this->relations())->toArray());

            return $request->fresh($this->relations());
        });
    }

    public function approve(string $id, int|string|null $actorId = null): PurchaseRequest
    {
        return $this->decide($id, 'approved', $actorId);
    }

    public function reject(string $id, int|string|null $actorId = null): PurchaseRequest
    {
        return $this->decide($id, 'rejected', $actorId);
    }

    public function cancel(string $id, int|string|null $actorId = null): PurchaseRequest
    {
        return DB::transaction(function () use ($id, $actorId) {
            $request = $this->lockRequest($id);

            if (! in_array($request->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted purchase requests can be cancelled.')]]);
            }

            $before = $request->toArray();
            $this->conditionalUpdate($request, $request->status, [
                'status' => 'rejected',
                'decided_by' => $actorId,
                'decided_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'purchase_request.cancel', 'purchase_request', $request->id, before: $before, after: $request->fresh($this->relations())->toArray());

            return $request->fresh($this->relations());
        });
    }

    /**
     * Converts an approved request into a real Purchase Order by reusing
     * PurchaseOrderService::create(). Supplier and final unit prices must be
     * supplied at conversion time if the request left them open.
     */
    public function convertToPurchaseOrder(string $id, array $overrides, int|string|null $actorId = null): PurchaseRequest
    {
        return DB::transaction(function () use ($id, $overrides, $actorId) {
            $request = $this->lockRequest($id);

            if ($request->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved purchase requests can be converted to a Purchase Order.')]]);
            }

            $supplierId = $overrides['supplier_id'] ?? $request->supplier_id;
            if (! $supplierId) {
                throw ValidationException::withMessages(['supplier_id' => [__('A supplier must be selected to convert this request to a Purchase Order.')]]);
            }

            $overridePrices = $overrides['unit_price_minor_by_line'] ?? [];

            $purchaseOrder = $this->purchaseOrderService->create([
                'supplier_id' => $supplierId,
                'order_date' => $overrides['order_date'] ?? now()->format('Y-m-d'),
                'expected_receipt_date' => $overrides['expected_receipt_date'] ?? null,
                'currency' => $request->currency,
                'fx_rate_e6' => $overrides['fx_rate_e6'] ?? 1_000_000,
                'reference' => $request->number,
                'notes' => $request->notes,
                'lines' => $request->lines->map(function (PurchaseRequestLine $line) use ($overridePrices) {
                    $unitPrice = $overridePrices[$line->id] ?? $line->estimated_unit_price_minor;
                    if ((int) $unitPrice <= 0) {
                        throw ValidationException::withMessages(['unit_price_minor_by_line' => [__('A unit price is required for every line to create the Purchase Order.')]]);
                    }

                    return [
                        'product_id' => $line->product_id,
                        'unit_of_measure_id' => $line->unit_of_measure_id,
                        'description' => $line->description,
                        'quantity_e6' => $line->quantity_e6,
                        'unit_price_minor' => (int) $unitPrice,
                    ];
                })->all(),
            ], $actorId);

            $before = $request->toArray();
            $this->conditionalUpdate($request, 'approved', [
                'status' => 'converted',
                'converted_purchase_order_id' => $purchaseOrder->id,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'purchase_request.convert', 'purchase_request', $request->id, before: $before, after: $request->fresh($this->relations())->toArray());

            return $request->fresh($this->relations());
        });
    }

    private function decide(string $id, string $decision, int|string|null $actorId): PurchaseRequest
    {
        return DB::transaction(function () use ($id, $decision, $actorId) {
            $request = $this->lockRequest($id);

            if ($request->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Only submitted purchase requests can be approved or rejected.')]]);
            }

            $before = $request->toArray();
            $this->conditionalUpdate($request, 'submitted', [
                'status' => $decision,
                'decided_by' => $actorId,
                'decided_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record($actorId, "purchase_request.{$decision}", 'purchase_request', $request->id, before: $before, after: $request->fresh($this->relations())->toArray());

            return $request->fresh($this->relations());
        });
    }

    private function replaceLines(PurchaseRequest $request, array $lines): void
    {
        $request->lines()->delete();
        $lineNo = 1;
        foreach ($lines as $line) {
            PurchaseRequestLine::query()->create([
                'purchase_request_id' => $request->id,
                'line_no' => $lineNo++,
                ...$line,
            ]);
        }
    }

    private function lockRequest(string $id): PurchaseRequest
    {
        /** @var PurchaseRequest $request */
        $request = PurchaseRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $request->load('lines');
    }

    private function assertCurrentVersion(PurchaseRequest $request, int $expectedVersion): void
    {
        if ((int) $request->lock_version !== $expectedVersion) {
            $this->throwConcurrencyValidationException();
        }
    }

    private function conditionalUpdate(PurchaseRequest $request, string $expectedStatus, array $attributes): void
    {
        $expectedVersion = (int) $request->lock_version;

        $affected = PurchaseRequest::query()
            ->whereKey($request->getKey())
            ->where('status', $expectedStatus)
            ->where('lock_version', $expectedVersion)
            ->update([...$attributes, 'lock_version' => $expectedVersion + 1]);

        if ($affected !== 1) {
            $this->throwConcurrencyValidationException();
        }

        $request->refresh();
    }

    private function throwConcurrencyValidationException(): never
    {
        throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
    }

    private function validateHeader(array $data, ?PurchaseRequest $existing = null): array
    {
        $supplierId = array_key_exists('supplier_id', $data) ? $data['supplier_id'] : $existing?->supplier_id;
        if ($supplierId) {
            $supplier = Supplier::query()->find($supplierId);
            if (! $supplier || $supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => [__('Selected Supplier is invalid or inactive.')]]);
            }
        }

        $currency = $data['currency'] ?? $existing?->currency;
        if (! $currency || ! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected Currency is invalid.')]]);
        }

        $requestedDate = $data['requested_date'] ?? $existing?->requested_date;
        if (! $requestedDate) {
            throw ValidationException::withMessages(['requested_date' => [__('Requested date is required.')]]);
        }

        $neededByDate = array_key_exists('needed_by_date', $data) ? $data['needed_by_date'] : $existing?->needed_by_date;
        if ($neededByDate && Carbon::parse($neededByDate)->lt(Carbon::parse($requestedDate))) {
            throw ValidationException::withMessages(['needed_by_date' => [__('Needed-by date must be on or after the requested date.')]]);
        }

        return [
            'supplier_id' => $supplierId,
            'requested_date' => $requestedDate,
            'needed_by_date' => $neededByDate,
            'currency' => $currency,
            'reference' => $data['reference'] ?? $existing?->reference,
            'notes' => $data['notes'] ?? $existing?->notes,
        ];
    }

    private function validateLines(array $lines): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => [__('At least one request line is required.')]]);
        }

        $validated = [];
        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $productId = $line['product_id'] ?? null;
            if (! $productId) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Product is required on line :line.', ['line' => $lineIndex])]]);
            }

            $product = Product::query()->find($productId);
            if (! $product || $product->status !== 'active' || ! $product->is_purchase_enabled) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Selected Product on line :line is invalid, inactive, or not purchase-enabled.', ['line' => $lineIndex])]]);
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
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity on line :line must be greater than zero.', ['line' => $lineIndex])]]);
            }

            $estimatedUnitPriceMinor = (int) ($line['estimated_unit_price_minor'] ?? 0);
            if ($estimatedUnitPriceMinor < 0) {
                throw ValidationException::withMessages(["lines.{$index}.estimated_unit_price_minor" => [__('Estimated unit price on line :line cannot be negative.', ['line' => $lineIndex])]]);
            }
            if ($quantityE6 > intdiv(PHP_INT_MAX, max(1, $estimatedUnitPriceMinor))) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity and estimated unit price product exceeds maximum integer capacity on line :line.', ['line' => $lineIndex])]]);
            }

            $product2 = $quantityE6 * $estimatedUnitPriceMinor;
            if ($product2 % 1_000_000 !== 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Line total produces a fractional minor unit and must be an exact integer minor amount.')]]);
            }

            $validated[] = [
                'product_id' => $productId,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? null,
                'quantity_e6' => $quantityE6,
                'estimated_unit_price_minor' => $estimatedUnitPriceMinor,
                'line_total_minor' => intdiv($product2, 1_000_000),
            ];
        }

        return $validated;
    }

    private function relations(): array
    {
        return ['supplier', 'lines.product', 'lines.unitOfMeasure', 'convertedPurchaseOrder'];
    }
}
