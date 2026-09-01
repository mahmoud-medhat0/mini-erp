<?php

namespace App\Application\Inventory;

use App\Application\Approvals\BranchApprovalRuleService;
use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Warehouse;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly StockAdjustmentService $stockAdjustmentService,
        private readonly AuditLogger $auditLogger,
        private readonly BranchApprovalRuleService $branchApprovalRuleService,
    ) {}

    public function create(array $data, ?int $actorId = null): StockCount
    {
        return DB::transaction(function () use ($data, $actorId): StockCount {
            $header = $this->validatedHeader($data);
            $lines = $this->validatedLines($header['warehouse_id'], $header['currency'], $data['lines'] ?? []);

            /** @var StockCount $count */
            $count = StockCount::query()->create([
                ...$header,
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($count, $lines);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_count.create',
                entityType: 'stock_count',
                entityId: $count->id,
                before: null,
                after: $this->freshCount($count)->toArray(),
            );

            return $this->freshCount($count);
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): StockCount
    {
        return DB::transaction(function () use ($id, $data, $actorId): StockCount {
            /** @var StockCount $count */
            $count = StockCount::query()
                ->with(['lines', 'warehouse'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($count->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft stock counts can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $count->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $before = $this->freshCount($count)->toArray();
            $header = $this->validatedHeader($data);
            $lines = $this->validatedLines($header['warehouse_id'], $header['currency'], $data['lines'] ?? []);

            $count->update([
                ...$header,
                'updated_by' => $actorId,
                'lock_version' => $count->lock_version + 1,
            ]);

            $this->replaceLines($count, $lines);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_count.update',
                entityType: 'stock_count',
                entityId: $count->id,
                before: $before,
                after: $this->freshCount($count)->toArray(),
            );

            return $this->freshCount($count);
        });
    }

    public function submit(string $id, ?int $actorId = null): StockCount
    {
        return $this->transition($id, $actorId, ['draft'], 'submitted', 'stock_count.submit', [
            'submitted_by' => $actorId,
            'submitted_at' => now(),
        ]);
    }

    public function approve(string $id, ?int $actorId = null): StockCount
    {
        return DB::transaction(function () use ($id, $actorId): StockCount {
            /** @var StockCount $count */
            $count = StockCount::query()->with('lines')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($count->status === 'approved') {
                return $this->freshCount($count);
            }

            if (! in_array($count->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted stock counts can be approved.')]]);
            }

            if ($count->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Stock count must have at least one line.')]]);
            }

            $this->branchApprovalRuleService->assertUserMayApprove('stock_count', [
                'document' => $count->warehouse?->branch_id ? (string) $count->warehouse->branch_id : null,
            ], $actorId);

            $before = $this->freshCount($count)->toArray();
            $number = $count->number;

            if (! $number) {
                $number = $this->numberAllocator->nextNumber('stock.count', 'SC', $count->count_date);
            }

            $count->update([
                'number' => $number,
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $count->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_count.approve',
                entityType: 'stock_count',
                entityId: $count->id,
                before: $before,
                after: $this->freshCount($count)->toArray(),
            );

            return $this->freshCount($count);
        });
    }

    public function post(string $id, ?int $actorId = null): StockCount
    {
        return DB::transaction(function () use ($id, $actorId): StockCount {
            /** @var StockCount $count */
            $count = StockCount::query()->with(['lines.product'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($count->status === 'posted') {
                return $this->freshCount($count);
            }

            if ($count->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved stock counts can be posted.')]]);
            }

            $before = $this->freshCount($count)->toArray();
            $varianceLines = $count->lines
                ->filter(fn (StockCountLine $line): bool => (int) $line->variance_quantity_e6 !== 0)
                ->values();

            $adjustmentId = null;

            if ($varianceLines->isNotEmpty()) {
                $adjustment = $this->stockAdjustmentService->createApprovedFromStockCount([
                    'adjustment_date' => $count->count_date->format('Y-m-d'),
                    'warehouse_id' => $count->warehouse_id,
                    'currency' => $count->currency,
                    'source_id' => $count->id,
                    'reference' => $count->number,
                    'reason' => $count->notes,
                    'lines' => $varianceLines->map(fn (StockCountLine $line): array => [
                        'product_id' => $line->product_id,
                        'quantity_delta_e6' => (int) $line->variance_quantity_e6,
                        'unit_cost_minor' => $line->unit_cost_minor,
                        'reason' => $line->notes,
                    ])->all(),
                ], $actorId);

                $adjustment = $this->stockAdjustmentService->post($adjustment->id, $actorId);
                $adjustmentId = $adjustment->id;
            }

            $count->update([
                'status' => 'posted',
                'stock_adjustment_id' => $adjustmentId,
                'posted_by' => $actorId,
                'posted_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $count->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_count.post',
                entityType: 'stock_count',
                entityId: $count->id,
                before: $before,
                after: $this->freshCount($count)->toArray(),
            );

            return $this->freshCount($count);
        });
    }

    public function cancel(string $id, ?int $actorId = null): StockCount
    {
        return $this->transition($id, $actorId, ['draft', 'submitted', 'approved'], 'cancelled', 'stock_count.cancel', [
            'cancelled_by' => $actorId,
            'cancelled_at' => now(),
        ]);
    }

    private function transition(string $id, ?int $actorId, array $allowedFrom, string $targetStatus, string $auditAction, array $extra): StockCount
    {
        return DB::transaction(function () use ($id, $actorId, $allowedFrom, $targetStatus, $auditAction, $extra): StockCount {
            /** @var StockCount $count */
            $count = StockCount::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($count->status === $targetStatus) {
                return $this->freshCount($count);
            }

            if (! in_array($count->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => [__('Stock count cannot move from [:from] to [:to].', ['from' => $count->status, 'to' => $targetStatus])]]);
            }

            $before = $this->freshCount($count)->toArray();

            $count->update([
                ...$extra,
                'status' => $targetStatus,
                'updated_by' => $actorId,
                'lock_version' => $count->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: $auditAction,
                entityType: 'stock_count',
                entityId: $count->id,
                before: $before,
                after: $this->freshCount($count)->toArray(),
            );

            return $this->freshCount($count);
        });
    }

    private function validatedHeader(array $data): array
    {
        $countDate = (string) ($data['count_date'] ?? '');
        if ($countDate === '') {
            throw ValidationException::withMessages(['count_date' => [__('Count date is required.')]]);
        }

        return [
            'count_date' => $countDate,
            'warehouse_id' => $this->activeWarehouse($data['warehouse_id'] ?? null)->id,
            'currency' => CurrencyInput::required($data['currency'] ?? null),
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function validatedLines(string $warehouseId, string $currency, array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => [__('Stock count must have at least one line.')]]);
        }

        $validated = [];
        $seenProducts = [];

        foreach (array_values($lines) as $index => $line) {
            $product = $this->activeStockProduct($line['product_id'] ?? null, "lines.{$index}.product_id");

            if (isset($seenProducts[$product->id])) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Product is already listed in this count.')]]);
            }
            $seenProducts[$product->id] = true;

            $expectedQuantityE6 = $this->expectedQuantity($warehouseId, $currency, $product->id, $line['expected_quantity_e6'] ?? null);
            $countedQuantityE6 = (int) ($line['counted_quantity_e6'] ?? 0);

            if ($expectedQuantityE6 < 0 || $countedQuantityE6 < 0) {
                throw ValidationException::withMessages(["lines.{$index}.counted_quantity_e6" => [__('Quantities must be greater than or equal to zero.')]]);
            }

            $unitCostMinor = isset($line['unit_cost_minor']) && $line['unit_cost_minor'] !== ''
                ? (int) $line['unit_cost_minor']
                : $this->currentUnitCost($warehouseId, $currency, $product->id);

            if ($unitCostMinor !== null && $unitCostMinor <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_cost_minor" => [__('Unit cost must be greater than zero when provided.')]]);
            }

            $validated[] = [
                'product_id' => $product->id,
                'unit_of_measure_id' => $product->unit_of_measure_id,
                'expected_quantity_e6' => $expectedQuantityE6,
                'counted_quantity_e6' => $countedQuantityE6,
                'variance_quantity_e6' => $countedQuantityE6 - $expectedQuantityE6,
                'unit_cost_minor' => $unitCostMinor,
                'notes' => $line['notes'] ?? null,
            ];
        }

        return $validated;
    }

    private function replaceLines(StockCount $count, array $lines): void
    {
        $count->lines()->delete();

        foreach ($lines as $index => $line) {
            StockCountLine::query()->create([
                'stock_count_id' => $count->id,
                'line_no' => $index + 1,
                ...$line,
            ]);
        }
    }

    private function expectedQuantity(string $warehouseId, string $currency, string $productId, mixed $explicitExpected): int
    {
        if ($explicitExpected !== null && $explicitExpected !== '') {
            return (int) $explicitExpected;
        }

        return (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('currency', $currency)
            ->where('product_id', $productId)
            ->value('quantity_e6');
    }

    private function currentUnitCost(string $warehouseId, string $currency, string $productId): ?int
    {
        $value = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('currency', $currency)
            ->where('product_id', $productId)
            ->value('avg_unit_cost_e6');

        return $value && (int) $value > 0 ? (int) $value : null;
    }

    private function activeWarehouse(?string $warehouseId): Warehouse
    {
        /** @var Warehouse|null $warehouse */
        $warehouse = $warehouseId ? Warehouse::query()->where('id', $warehouseId)->first() : null;
        if (! $warehouse || ! $warehouse->is_active) {
            throw ValidationException::withMessages(['warehouse_id' => [__('Selected warehouse is invalid or inactive.')]]);
        }

        return $warehouse;
    }

    private function activeStockProduct(?string $productId, string $field): Product
    {
        /** @var Product|null $product */
        $product = $productId ? Product::query()->where('id', $productId)->first() : null;
        if (! $product || $product->status !== 'active' || $product->type !== 'stock') {
            throw ValidationException::withMessages([$field => [__('Selected product must be an active stock item.')]]);
        }

        return $product;
    }

    private function freshCount(StockCount $count): StockCount
    {
        return $count->fresh([
            'warehouse.branch',
            'adjustment.lines',
            'lines.product',
            'lines.unitOfMeasure',
        ]);
    }
}
