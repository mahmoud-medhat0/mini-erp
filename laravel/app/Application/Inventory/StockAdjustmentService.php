<?php

namespace App\Application\Inventory;

use App\Application\Accounting\PeriodGuard;
use App\Application\Approvals\BranchApprovalRuleService;
use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
        private readonly BranchApprovalRuleService $branchApprovalRuleService,
    ) {}

    public function create(array $data, ?int $actorId = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $actorId): StockAdjustment {
            $header = $this->validatedHeader($data);
            $lines = $this->validatedLines($data['lines'] ?? []);

            /** @var StockAdjustment $adjustment */
            $adjustment = StockAdjustment::query()->create([
                ...$header,
                'status' => 'draft',
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($adjustment, $lines);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_adjustment.create',
                entityType: 'stock_adjustment',
                entityId: $adjustment->id,
                before: null,
                after: $this->freshAdjustment($adjustment)->toArray(),
            );

            return $this->freshAdjustment($adjustment);
        });
    }

    public function createApprovedFromStockCount(array $data, ?int $actorId = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $actorId): StockAdjustment {
            $adjustment = $this->create([
                ...$data,
                'source_type' => 'stock_count',
            ], $actorId);

            return $this->approve($adjustment->id, $actorId);
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): StockAdjustment
    {
        return DB::transaction(function () use ($id, $data, $actorId): StockAdjustment {
            /** @var StockAdjustment $adjustment */
            $adjustment = StockAdjustment::query()
                ->with(['lines', 'warehouse'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($adjustment->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft stock adjustments can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $adjustment->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $before = $this->freshAdjustment($adjustment)->toArray();
            $header = $this->validatedHeader($data);
            $lines = $this->validatedLines($data['lines'] ?? []);

            $adjustment->update([
                ...$header,
                'updated_by' => $actorId,
                'lock_version' => $adjustment->lock_version + 1,
            ]);

            $this->replaceLines($adjustment, $lines);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_adjustment.update',
                entityType: 'stock_adjustment',
                entityId: $adjustment->id,
                before: $before,
                after: $this->freshAdjustment($adjustment)->toArray(),
            );

            return $this->freshAdjustment($adjustment);
        });
    }

    public function submit(string $id, ?int $actorId = null): StockAdjustment
    {
        return $this->transition($id, $actorId, ['draft'], 'submitted', 'stock_adjustment.submit', [
            'submitted_by' => $actorId,
            'submitted_at' => now(),
        ]);
    }

    public function approve(string $id, ?int $actorId = null): StockAdjustment
    {
        return DB::transaction(function () use ($id, $actorId): StockAdjustment {
            /** @var StockAdjustment $adjustment */
            $adjustment = StockAdjustment::query()->with('lines')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status === 'approved') {
                return $this->freshAdjustment($adjustment);
            }

            if (! in_array($adjustment->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted stock adjustments can be approved.')]]);
            }

            if ($adjustment->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Stock adjustment must have at least one line.')]]);
            }

            $this->branchApprovalRuleService->assertUserMayApprove('stock_adjustment', [
                'document' => $adjustment->warehouse?->branch_id ? (string) $adjustment->warehouse->branch_id : null,
            ], $actorId);

            $before = $this->freshAdjustment($adjustment)->toArray();
            $number = $adjustment->number;

            if (! $number) {
                $year = Carbon::parse($adjustment->adjustment_date)->format('Y');
                $number = 'ADJ-'.$year.'-'.str_pad((string) $this->numberAllocator->nextValue('stock.adjustment'), 5, '0', STR_PAD_LEFT);
            }

            $adjustment->update([
                'number' => $number,
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $adjustment->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_adjustment.approve',
                entityType: 'stock_adjustment',
                entityId: $adjustment->id,
                before: $before,
                after: $this->freshAdjustment($adjustment)->toArray(),
            );

            return $this->freshAdjustment($adjustment);
        });
    }

    public function post(string $id, ?int $actorId = null): StockAdjustment
    {
        return DB::transaction(function () use ($id, $actorId): StockAdjustment {
            /** @var StockAdjustment $adjustment */
            $adjustment = StockAdjustment::query()
                ->with(['lines.product', 'lines.unitOfMeasure'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($adjustment->status === 'posted') {
                return $this->freshAdjustment($adjustment);
            }

            if ($adjustment->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved stock adjustments can be posted.')]]);
            }

            $period = $this->periodForDate($adjustment->adjustment_date->format('Y-m-d'));
            $before = $this->freshAdjustment($adjustment)->toArray();
            $totalValueDeltaMinor = 0;

            foreach ($adjustment->lines as $line) {
                if ($line->stock_movement_id) {
                    $totalValueDeltaMinor += (int) $line->value_delta_minor;

                    continue;
                }

                $unitCostMinor = $this->unitCostForPosting($adjustment, $line);
                $movement = $this->inventoryService->recordAdjustment(
                    sourceType: 'stock_adjustment',
                    sourceId: $adjustment->id,
                    sourceLineId: $line->id,
                    movementDate: $adjustment->adjustment_date->format('Y-m-d'),
                    productId: $line->product_id,
                    unitOfMeasureId: $line->unit_of_measure_id,
                    currency: $adjustment->currency,
                    quantityDeltaE6: (int) $line->quantity_delta_e6,
                    unitCostMinor: $unitCostMinor,
                    fiscalYearId: $period->fiscal_year_id,
                    financialPeriodId: $period->id,
                    actorId: $actorId,
                    warehouseId: $adjustment->warehouse_id,
                );

                $lineUnitCostMinor = intdiv(abs((int) $movement->value_delta_minor) * 1000000, abs((int) $line->quantity_delta_e6));
                $line->update([
                    'unit_cost_minor' => $unitCostMinor ?: $lineUnitCostMinor,
                    'value_delta_minor' => (int) $movement->value_delta_minor,
                    'stock_movement_id' => $movement->id,
                ]);

                $totalValueDeltaMinor += (int) $movement->value_delta_minor;
            }

            $adjustment->update([
                'status' => 'posted',
                'total_value_delta_minor' => $totalValueDeltaMinor,
                'posted_by' => $actorId,
                'posted_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $adjustment->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_adjustment.post',
                entityType: 'stock_adjustment',
                entityId: $adjustment->id,
                before: $before,
                after: $this->freshAdjustment($adjustment)->toArray(),
            );

            return $this->freshAdjustment($adjustment);
        });
    }

    public function cancel(string $id, ?int $actorId = null): StockAdjustment
    {
        return $this->transition($id, $actorId, ['draft', 'submitted', 'approved'], 'cancelled', 'stock_adjustment.cancel', [
            'cancelled_by' => $actorId,
            'cancelled_at' => now(),
        ]);
    }

    private function transition(string $id, ?int $actorId, array $allowedFrom, string $targetStatus, string $auditAction, array $extra): StockAdjustment
    {
        return DB::transaction(function () use ($id, $actorId, $allowedFrom, $targetStatus, $auditAction, $extra): StockAdjustment {
            /** @var StockAdjustment $adjustment */
            $adjustment = StockAdjustment::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status === $targetStatus) {
                return $this->freshAdjustment($adjustment);
            }

            if (! in_array($adjustment->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => [__('Stock adjustment cannot move from [:from] to [:to].', ['from' => $adjustment->status, 'to' => $targetStatus])]]);
            }

            $before = $this->freshAdjustment($adjustment)->toArray();

            $adjustment->update([
                ...$extra,
                'status' => $targetStatus,
                'updated_by' => $actorId,
                'lock_version' => $adjustment->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: $auditAction,
                entityType: 'stock_adjustment',
                entityId: $adjustment->id,
                before: $before,
                after: $this->freshAdjustment($adjustment)->toArray(),
            );

            return $this->freshAdjustment($adjustment);
        });
    }

    private function validatedHeader(array $data): array
    {
        $adjustmentDate = (string) ($data['adjustment_date'] ?? '');
        if ($adjustmentDate === '') {
            throw ValidationException::withMessages(['adjustment_date' => [__('Adjustment date is required.')]]);
        }

        return [
            'adjustment_date' => $adjustmentDate,
            'warehouse_id' => $this->activeWarehouse($data['warehouse_id'] ?? null)->id,
            'currency' => CurrencyInput::required($data['currency'] ?? null),
            'reference' => $data['reference'] ?? null,
            'reason' => $data['reason'] ?? null,
        ];
    }

    private function validatedLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => [__('Stock adjustment must have at least one line.')]]);
        }

        $validated = [];
        foreach (array_values($lines) as $index => $line) {
            $product = $this->activeStockProduct($line['product_id'] ?? null, "lines.{$index}.product_id");
            $quantityDeltaE6 = (int) ($line['quantity_delta_e6'] ?? 0);
            if ($quantityDeltaE6 === 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_delta_e6" => [__('Adjustment quantity delta must not be zero.')]]);
            }

            $unitCostMinor = isset($line['unit_cost_minor']) && $line['unit_cost_minor'] !== ''
                ? (int) $line['unit_cost_minor']
                : null;

            if ($unitCostMinor !== null && $unitCostMinor <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_cost_minor" => [__('Unit cost must be greater than zero when provided.')]]);
            }

            $validated[] = [
                'product_id' => $product->id,
                'unit_of_measure_id' => $product->unit_of_measure_id,
                'quantity_delta_e6' => $quantityDeltaE6,
                'unit_cost_minor' => $unitCostMinor,
                'reason' => $line['reason'] ?? null,
            ];
        }

        return $validated;
    }

    private function replaceLines(StockAdjustment $adjustment, array $lines): void
    {
        $adjustment->lines()->delete();

        foreach ($lines as $index => $line) {
            StockAdjustmentLine::query()->create([
                'stock_adjustment_id' => $adjustment->id,
                'line_no' => $index + 1,
                ...$line,
            ]);
        }
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

    private function unitCostForPosting(StockAdjustment $adjustment, StockAdjustmentLine $line): ?int
    {
        if ((int) $line->quantity_delta_e6 < 0) {
            return null;
        }

        if ($line->unit_cost_minor && (int) $line->unit_cost_minor > 0) {
            return (int) $line->unit_cost_minor;
        }

        /** @var StockBalance|null $balance */
        $balance = StockBalance::query()
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->where('product_id', $line->product_id)
            ->where('currency', $adjustment->currency)
            ->first();

        return $balance && $balance->avg_unit_cost_e6 > 0 ? (int) $balance->avg_unit_cost_e6 : null;
    }

    private function periodForDate(string $date): FinancialPeriod
    {
        return $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($date);
    }

    private function freshAdjustment(StockAdjustment $adjustment): StockAdjustment
    {
        return $adjustment->fresh([
            'warehouse.branch',
            'lines.product',
            'lines.unitOfMeasure',
            'lines.movement',
        ]);
    }
}
