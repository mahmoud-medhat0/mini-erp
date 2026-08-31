<?php

namespace App\Application\Inventory;

use App\Application\Accounting\PeriodGuard;
use App\Application\Approvals\BranchApprovalRuleService;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StockTransferReceipt;
use App\Models\StockTransferReceiptLine;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'issued', 'partially_received', 'received', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly PeriodGuard $periodGuard,
        private readonly AuditLogger $auditLogger,
        private readonly BranchApprovalRuleService $branchApprovalRuleService,
    ) {}

    public function create(array $data, ?int $actorId = null): StockTransfer
    {
        return DB::transaction(function () use ($data, $actorId): StockTransfer {
            $header = $this->validatedHeader($data);
            $lines = $this->validatedLines($data['lines'] ?? []);

            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::query()->create([
                ...$header,
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($transfer, $lines);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_transfer.create',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                before: null,
                after: $this->freshTransfer($transfer)->toArray(),
            );

            return $this->freshTransfer($transfer);
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): StockTransfer
    {
        return DB::transaction(function () use ($id, $data, $actorId): StockTransfer {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::query()
                ->with(['lines', 'sourceWarehouse', 'destinationWarehouse'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft stock transfers can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $transfer->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $before = $this->freshTransfer($transfer)->toArray();
            $header = $this->validatedHeader($data);
            $lines = $this->validatedLines($data['lines'] ?? []);

            $transfer->update([
                ...$header,
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->replaceLines($transfer, $lines);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_transfer.update',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                before: $before,
                after: $this->freshTransfer($transfer)->toArray(),
            );

            return $this->freshTransfer($transfer);
        });
    }

    public function submit(string $id, ?int $actorId = null): StockTransfer
    {
        return $this->transition($id, $actorId, ['draft'], 'submitted', 'stock_transfer.submit', [
            'submitted_by' => $actorId,
            'submitted_at' => now(),
        ]);
    }

    public function approve(string $id, ?int $actorId = null): StockTransfer
    {
        return DB::transaction(function () use ($id, $actorId): StockTransfer {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::query()->with('lines')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($transfer->status === 'approved') {
                return $this->freshTransfer($transfer);
            }

            if (! in_array($transfer->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted stock transfers can be approved.')]]);
            }

            if ($transfer->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Stock transfer must have at least one line.')]]);
            }

            $this->branchApprovalRuleService->assertUserMayApprove('stock_transfer', [
                'source' => $transfer->sourceWarehouse?->branch_id ? (string) $transfer->sourceWarehouse->branch_id : null,
                'destination' => $transfer->destinationWarehouse?->branch_id ? (string) $transfer->destinationWarehouse->branch_id : null,
            ], $actorId);

            $before = $this->freshTransfer($transfer)->toArray();
            $number = $transfer->number;
            if (! $number) {
                $number = $this->numberAllocator->nextNumber('stock.transfer', 'ST', $transfer->transfer_date);
            }

            $transfer->update([
                'number' => $number,
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_transfer.approve',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                before: $before,
                after: $this->freshTransfer($transfer)->toArray(),
            );

            return $this->freshTransfer($transfer);
        });
    }

    public function issue(string $id, ?int $actorId = null): StockTransfer
    {
        return DB::transaction(function () use ($id, $actorId): StockTransfer {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::query()
                ->with(['lines.product', 'lines.unitOfMeasure'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($transfer->status, ['issued', 'partially_received', 'received'], true)) {
                return $this->freshTransfer($transfer);
            }

            if ($transfer->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved stock transfers can be issued.')]]);
            }

            $period = $this->periodForDate($transfer->transfer_date->format('Y-m-d'));
            $before = $this->freshTransfer($transfer)->toArray();

            foreach ($transfer->lines as $line) {
                if ($line->issued_quantity_e6 > 0) {
                    continue;
                }

                $movement = $this->inventoryService->recordTransferOut(
                    sourceType: 'stock_transfer',
                    sourceId: $transfer->id,
                    sourceLineId: $line->id,
                    movementDate: $transfer->transfer_date->format('Y-m-d'),
                    productId: $line->product_id,
                    unitOfMeasureId: $line->unit_of_measure_id,
                    currency: $this->currencyForProduct($line->product_id, $transfer->source_warehouse_id),
                    quantityE6: $line->quantity_e6,
                    fiscalYearId: $period->fiscal_year_id,
                    financialPeriodId: $period->id,
                    actorId: $actorId,
                    warehouseId: $transfer->source_warehouse_id,
                );

                $line->update([
                    'issued_quantity_e6' => $line->quantity_e6,
                    'issued_value_minor' => abs((int) $movement->value_delta_minor),
                    'source_movement_id' => $movement->id,
                ]);
            }

            $transfer->update([
                'status' => 'issued',
                'issued_by' => $actorId,
                'issued_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_transfer.issue',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                before: $before,
                after: $this->freshTransfer($transfer)->toArray(),
            );

            return $this->freshTransfer($transfer);
        });
    }

    public function receive(string $id, array $data = [], ?int $actorId = null): StockTransfer
    {
        return DB::transaction(function () use ($id, $data, $actorId): StockTransfer {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::query()
                ->with(['lines.product', 'lines.unitOfMeasure', 'lines.receiptLines'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status === 'received') {
                return $this->freshTransfer($transfer);
            }

            if (! in_array($transfer->status, ['issued', 'partially_received'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only issued stock transfers can be received.')]]);
            }

            $receiptDate = (string) ($data['receipt_date'] ?? $transfer->transfer_date->format('Y-m-d'));
            $period = $this->periodForDate($receiptDate);
            $receiptLines = $this->receiveLinePayload($transfer, $data['lines'] ?? []);

            if ($receiptLines === []) {
                return $this->freshTransfer($transfer);
            }

            $before = $this->freshTransfer($transfer)->toArray();

            /** @var StockTransferReceipt $receipt */
            $receipt = StockTransferReceipt::query()->create([
                'stock_transfer_id' => $transfer->id,
                'receipt_date' => $receiptDate,
                'status' => 'posted',
                'created_by' => $actorId,
            ]);

            foreach ($receiptLines as $payload) {
                /** @var StockTransferLine $line */
                $line = $payload['line'];
                $quantityE6 = $payload['quantity_e6'];
                $valueMinor = $this->allocatedReceiptValue($line, $quantityE6);

                /** @var StockTransferReceiptLine $receiptLine */
                $receiptLine = StockTransferReceiptLine::query()->create([
                    'stock_transfer_receipt_id' => $receipt->id,
                    'stock_transfer_line_id' => $line->id,
                    'quantity_e6' => $quantityE6,
                    'value_minor' => $valueMinor,
                ]);

                $movement = $this->inventoryService->recordTransferIn(
                    sourceType: 'stock_transfer_receipt',
                    sourceId: $receipt->id,
                    sourceLineId: $receiptLine->id,
                    movementDate: $receiptDate,
                    productId: $line->product_id,
                    unitOfMeasureId: $line->unit_of_measure_id,
                    currency: $this->currencyForProduct($line->product_id, $transfer->source_warehouse_id),
                    quantityE6: $quantityE6,
                    valueMinor: $valueMinor,
                    fiscalYearId: $period->fiscal_year_id,
                    financialPeriodId: $period->id,
                    actorId: $actorId,
                    warehouseId: $transfer->destination_warehouse_id,
                );

                $receiptLine->update(['destination_movement_id' => $movement->id]);
                $line->increment('received_quantity_e6', $quantityE6);
            }

            $transfer->refresh();
            $status = $this->allLinesFullyReceived($transfer) ? 'received' : 'partially_received';

            $transfer->update([
                'status' => $status,
                'received_by' => $actorId,
                'received_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'stock_transfer.receive',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                before: $before,
                after: $this->freshTransfer($transfer)->toArray(),
            );

            return $this->freshTransfer($transfer);
        });
    }

    public function cancel(string $id, ?int $actorId = null): StockTransfer
    {
        return $this->transition($id, $actorId, ['draft', 'submitted', 'approved'], 'cancelled', 'stock_transfer.cancel', [
            'cancelled_by' => $actorId,
            'cancelled_at' => now(),
        ]);
    }

    private function transition(string $id, ?int $actorId, array $allowedFrom, string $targetStatus, string $auditAction, array $extra): StockTransfer
    {
        return DB::transaction(function () use ($id, $actorId, $allowedFrom, $targetStatus, $auditAction, $extra): StockTransfer {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($transfer->status === $targetStatus) {
                return $this->freshTransfer($transfer);
            }

            if (! in_array($transfer->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => [__('Stock transfer cannot move from [:from] to [:to].', ['from' => $transfer->status, 'to' => $targetStatus])]]);
            }

            $before = $this->freshTransfer($transfer)->toArray();

            $transfer->update([
                ...$extra,
                'status' => $targetStatus,
                'updated_by' => $actorId,
                'lock_version' => $transfer->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: $auditAction,
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                before: $before,
                after: $this->freshTransfer($transfer)->toArray(),
            );

            return $this->freshTransfer($transfer);
        });
    }

    private function validatedHeader(array $data): array
    {
        $source = $this->activeWarehouse($data['source_warehouse_id'] ?? null, 'source_warehouse_id');
        $destination = $this->activeWarehouse($data['destination_warehouse_id'] ?? null, 'destination_warehouse_id');

        if ($source->id === $destination->id) {
            throw ValidationException::withMessages(['destination_warehouse_id' => [__('Destination warehouse must be different from source warehouse.')]]);
        }

        $transferDate = (string) ($data['transfer_date'] ?? '');
        if ($transferDate === '') {
            throw ValidationException::withMessages(['transfer_date' => [__('Transfer date is required.')]]);
        }

        return [
            'transfer_date' => $transferDate,
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'reference' => $data['reference'] ?? null,
            'reason' => $data['reason'] ?? null,
        ];
    }

    private function validatedLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => [__('Stock transfer must have at least one line.')]]);
        }

        $validated = [];
        foreach (array_values($lines) as $index => $line) {
            /** @var Product|null $product */
            $product = Product::query()->where('id', $line['product_id'] ?? null)->first();
            if (! $product || $product->status !== 'active' || $product->type !== 'stock') {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Selected product must be an active stock item.')]]);
            }

            /** @var UnitOfMeasure|null $uom */
            $uom = UnitOfMeasure::query()->where('id', $line['unit_of_measure_id'] ?? null)->first();
            if (! $uom || ! $uom->is_active || $uom->id !== $product->unit_of_measure_id) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Selected unit of measure is invalid for this product.')]]);
            }

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Transfer quantity must be greater than zero.')]]);
            }

            $validated[] = [
                'product_id' => $product->id,
                'unit_of_measure_id' => $uom->id,
                'quantity_e6' => $quantityE6,
                'notes' => $line['notes'] ?? null,
            ];
        }

        return $validated;
    }

    private function replaceLines(StockTransfer $transfer, array $lines): void
    {
        $transfer->lines()->delete();

        foreach ($lines as $index => $line) {
            StockTransferLine::query()->create([
                'stock_transfer_id' => $transfer->id,
                'line_no' => $index + 1,
                ...$line,
            ]);
        }
    }

    private function receiveLinePayload(StockTransfer $transfer, array $payloadLines): array
    {
        $transfer->loadMissing('lines.receiptLines');
        $payloadByLine = collect($payloadLines)->keyBy('stock_transfer_line_id');
        $result = [];

        foreach ($transfer->lines as $line) {
            $remaining = (int) $line->issued_quantity_e6 - (int) $line->received_quantity_e6;
            if ($remaining <= 0) {
                continue;
            }

            $requested = $payloadByLine->has($line->id)
                ? (int) ($payloadByLine->get($line->id)['quantity_e6'] ?? 0)
                : ($payloadLines === [] ? $remaining : 0);

            if ($requested === 0) {
                continue;
            }

            if ($requested < 0 || $requested > $remaining) {
                throw ValidationException::withMessages(['lines' => [__('Receipt quantity cannot exceed issued remaining quantity.')]]);
            }

            $result[] = ['line' => $line, 'quantity_e6' => $requested];
        }

        return $result;
    }

    private function allocatedReceiptValue(StockTransferLine $line, int $quantityE6): int
    {
        $sourceQty = (int) $line->issued_quantity_e6;
        $sourceValue = (int) $line->issued_value_minor;
        $remainingQty = $sourceQty - (int) $line->received_quantity_e6;
        $alreadyReceivedValue = (int) StockTransferReceiptLine::query()
            ->where('stock_transfer_line_id', $line->id)
            ->sum('value_minor');

        if ($quantityE6 === $remainingQty) {
            return $sourceValue - $alreadyReceivedValue;
        }

        $value = intdiv($quantityE6 * $sourceValue, $sourceQty);
        if ($value <= 0) {
            throw ValidationException::withMessages(['value' => [__('Transfer value allocation is too small for partial receipt. Receive the remaining quantity together.')]]);
        }

        return $value;
    }

    private function allLinesFullyReceived(StockTransfer $transfer): bool
    {
        $transfer->load('lines');

        return $transfer->lines->every(fn (StockTransferLine $line): bool => (int) $line->received_quantity_e6 === (int) $line->issued_quantity_e6);
    }

    private function activeWarehouse(?string $warehouseId, string $field): Warehouse
    {
        /** @var Warehouse|null $warehouse */
        $warehouse = $warehouseId ? Warehouse::query()->where('id', $warehouseId)->first() : null;
        if (! $warehouse || ! $warehouse->is_active) {
            throw ValidationException::withMessages([$field => [__('Selected warehouse is invalid or inactive.')]]);
        }

        return $warehouse;
    }

    private function periodForDate(string $date): FinancialPeriod
    {
        return $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($date);
    }

    private function currencyForProduct(string $productId, string $warehouseId): string
    {
        $balanceCurrency = DB::table('stock_balance')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('currency');

        if (! $balanceCurrency) {
            throw ValidationException::withMessages(['currency' => [__('No stock valuation currency exists for this product.')]]);
        }

        return (string) $balanceCurrency;
    }

    private function freshTransfer(StockTransfer $transfer): StockTransfer
    {
        return $transfer->fresh([
            'sourceWarehouse.branch',
            'destinationWarehouse.branch',
            'lines.product',
            'lines.unitOfMeasure',
            'lines.sourceMovement',
            'receipts.lines.destinationMovement',
        ]);
    }
}
