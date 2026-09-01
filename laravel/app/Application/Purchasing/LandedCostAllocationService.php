<?php

namespace App\Application\Purchasing;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Application\Taxes\TaxPeriodGuard;
use App\Domain\Audit\AuditLogger;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\LandedCostAllocation;
use App\Models\LandedCostAllocationLine;
use App\Models\PayableEntry;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandedCostAllocationService
{
    private const QUANTITY_SCALE = 1000000;

    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public const ALLOCATION_METHODS = ['by_value', 'by_quantity', 'manual'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
        private readonly TaxPeriodGuard $taxPeriodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): LandedCostAllocation
    {
        return DB::transaction(function () use ($data, $actorId): LandedCostAllocation {
            $validated = $this->validatedDraftData($data);
            $supplier = $this->activeSupplier($validated['supplier_id']);
            $goodsReceipt = $this->confirmedGoodsReceipt($validated['goods_receipt_id']);
            $period = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($validated['allocation_date']);
            $receiptCurrency = (string) $goodsReceipt->purchaseOrder->currency;

            if ($validated['currency'] !== $receiptCurrency) {
                throw ValidationException::withMessages(['currency' => [__('Landed cost currency must match the Goods Receipt purchase currency.')]]);
            }

            $lines = $this->buildLines($goodsReceipt, $validated);

            /** @var LandedCostAllocation $allocation */
            $allocation = LandedCostAllocation::query()->create([
                'goods_receipt_id' => $goodsReceipt->id,
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'allocation_date' => $validated['allocation_date'],
                'due_date' => $validated['due_date'],
                'currency' => $validated['currency'],
                'fx_rate_e6' => 1000000,
                'allocation_method' => $validated['allocation_method'],
                'cost_amount_minor' => $validated['cost_amount_minor'],
                'tax_amount_minor' => $validated['tax_amount_minor'],
                'total_amount_minor' => $validated['cost_amount_minor'] + $validated['tax_amount_minor'],
                'status' => 'draft',
                'reference' => $validated['reference'],
                'description' => $validated['description'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($lines as $index => $line) {
                $allocation->lines()->create([
                    'line_no' => $index + 1,
                    ...$line,
                ]);
            }

            $allocation->load($this->defaultRelations());

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'landed_cost_allocation.create',
                entityType: 'landed_cost_allocation',
                entityId: $allocation->id,
                before: null,
                after: $allocation->toArray(),
            );

            return $allocation;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): LandedCostAllocation
    {
        return DB::transaction(function () use ($id, $data, $actorId): LandedCostAllocation {
            /** @var LandedCostAllocation $allocation */
            $allocation = LandedCostAllocation::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($allocation->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft landed cost allocations can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $allocation->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $payload = [
                ...$allocation->only([
                    'goods_receipt_id',
                    'supplier_id',
                    'allocation_date',
                    'due_date',
                    'currency',
                    'allocation_method',
                    'cost_amount_minor',
                    'tax_amount_minor',
                    'reference',
                    'description',
                ]),
                ...$data,
            ];

            $validated = $this->validatedDraftData($payload);
            $supplier = $this->activeSupplier($validated['supplier_id']);
            $goodsReceipt = $this->confirmedGoodsReceipt($validated['goods_receipt_id']);
            $period = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($validated['allocation_date']);
            $receiptCurrency = (string) $goodsReceipt->purchaseOrder->currency;

            if ($validated['currency'] !== $receiptCurrency) {
                throw ValidationException::withMessages(['currency' => [__('Landed cost currency must match the Goods Receipt purchase currency.')]]);
            }

            $lines = $this->buildLines($goodsReceipt, $validated);
            $before = $allocation->toArray();

            $allocation->update([
                'goods_receipt_id' => $goodsReceipt->id,
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'allocation_date' => $validated['allocation_date'],
                'due_date' => $validated['due_date'],
                'currency' => $validated['currency'],
                'allocation_method' => $validated['allocation_method'],
                'cost_amount_minor' => $validated['cost_amount_minor'],
                'tax_amount_minor' => $validated['tax_amount_minor'],
                'total_amount_minor' => $validated['cost_amount_minor'] + $validated['tax_amount_minor'],
                'reference' => $validated['reference'],
                'description' => $validated['description'],
                'updated_by' => $actorId,
                'lock_version' => $allocation->lock_version + 1,
            ]);

            $allocation->lines()->delete();

            foreach ($lines as $index => $line) {
                $allocation->lines()->create([
                    'line_no' => $index + 1,
                    ...$line,
                ]);
            }

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'landed_cost_allocation.update',
                entityType: 'landed_cost_allocation',
                entityId: $allocation->id,
                before: $before,
                after: $allocation->fresh($this->defaultRelations())->toArray(),
            );

            return $allocation->fresh($this->defaultRelations());
        });
    }

    public function submit(string $id, ?int $actorId = null): LandedCostAllocation
    {
        return $this->transition($id, 'draft', 'submitted', 'submitted_by', 'submitted_at', 'landed_cost_allocation.submit', $actorId);
    }

    public function approve(string $id, ?int $actorId = null): LandedCostAllocation
    {
        return $this->transition($id, 'submitted', 'approved', 'approved_by', 'approved_at', 'landed_cost_allocation.approve', $actorId);
    }

    public function post(string $id, ?int $actorId = null): LandedCostAllocation
    {
        return DB::transaction(function () use ($id, $actorId): LandedCostAllocation {
            /** @var LandedCostAllocation $allocation */
            $allocation = LandedCostAllocation::query()
                ->with(['lines.goodsReceiptLine', 'goodsReceipt.purchaseOrder', 'goodsReceipt.warehouse', 'supplier'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($allocation->status === 'posted') {
                return $allocation->load($this->defaultRelations());
            }

            if ($allocation->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved landed cost allocations can be posted.')]]);
            }

            if ($allocation->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Cannot post landed cost allocation without line items.')]]);
            }

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $allocation->financial_period_id, (string) $allocation->allocation_date);
            $this->taxPeriodGuard->ensureDateNotFiled((string) $allocation->allocation_date);

            if ($period->fiscal_year_id !== $allocation->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => [__('Financial period does not belong to the landed cost fiscal year.')]]);
            }

            if ($allocation->fx_rate_e6 !== 1000000) {
                throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
            }

            $goodsReceipt = $allocation->goodsReceipt;
            if (! $goodsReceipt || $goodsReceipt->status !== 'confirmed') {
                throw ValidationException::withMessages(['goods_receipt_id' => [__('Landed cost can only be posted against a confirmed Goods Receipt.')]]);
            }

            $branchId = $goodsReceipt->warehouse?->branch_id ? (string) $goodsReceipt->warehouse->branch_id : null;
            $splits = $this->splitCapitalization($allocation);
            $inventoryAmountMinor = array_sum(array_column($splits, 'capitalized_amount_minor'));
            $cogsAmountMinor = array_sum(array_column($splits, 'expensed_amount_minor'));
            $taxAmountMinor = (int) $allocation->tax_amount_minor;
            $totalAmountMinor = (int) $allocation->total_amount_minor;

            if ($inventoryAmountMinor + $cogsAmountMinor !== (int) $allocation->cost_amount_minor) {
                throw ValidationException::withMessages(['lines' => [__('Allocated landed cost split does not equal the header cost amount.')]]);
            }

            $inventoryAccount = $inventoryAmountMinor > 0 ? $this->mappingService->getAccount('inventory_asset', $branchId) : null;
            $cogsAccount = $cogsAmountMinor > 0 ? $this->mappingService->getAccount('cogs', $branchId) : null;
            $inputTaxAccount = $taxAmountMinor > 0 ? $this->mappingService->getAccount('input_tax_receivable', $branchId) : null;
            $apAccount = $this->mappingService->getAccount('ap_control', $branchId);

            foreach ([$inventoryAccount, $cogsAccount, $inputTaxAccount, $apAccount] as $account) {
                if ($account && $account->currency !== $allocation->currency) {
                    throw ValidationException::withMessages(['currency' => [__('Mapped landed cost GL account currencies must match allocation currency.')]]);
                }
            }

            $number = $allocation->number;
            if (! $number) {
                $number = $this->numberAllocator->nextNumber('purchasing.landed_cost', 'LC', $allocation->allocation_date);
            }

            $before = $allocation->toArray();

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $allocation->allocation_date,
                'financial_period_id' => $allocation->financial_period_id,
                'branch_id' => $branchId,
                'source_type' => 'landed_cost_allocation',
                'source_id' => $allocation->id,
                'description' => "Landed Cost {$number}",
                'reference' => $allocation->reference,
                'currency' => $allocation->currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $lineNo = 1;

            if ($inventoryAccount && $inventoryAmountMinor > 0) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $inventoryAccount->id,
                    'branch_id' => $branchId,
                    'memo' => "Capitalized landed cost {$number}",
                    'debit_minor' => $inventoryAmountMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $inventoryAmountMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $allocation->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            if ($cogsAccount && $cogsAmountMinor > 0) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $cogsAccount->id,
                    'branch_id' => $branchId,
                    'memo' => "Expensed landed cost {$number}",
                    'debit_minor' => $cogsAmountMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $cogsAmountMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $allocation->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            if ($inputTaxAccount && $taxAmountMinor > 0) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $inputTaxAccount->id,
                    'branch_id' => $branchId,
                    'memo' => "Input tax on landed cost {$number}",
                    'debit_minor' => $taxAmountMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $taxAmountMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $allocation->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            $apLine = $journalEntry->lines()->create([
                'line_no' => $lineNo,
                'account_id' => $apAccount->id,
                'branch_id' => $branchId,
                'memo' => "AP Control - Landed cost {$number}",
                'debit_minor' => 0,
                'credit_minor' => $totalAmountMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $totalAmountMinor,
                'currency' => $allocation->currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var PayableEntry $payableEntry */
            $payableEntry = PayableEntry::query()->create([
                'supplier_id' => $allocation->supplier_id,
                'source_type' => 'landed_cost_allocation',
                'source_id' => $allocation->id,
                'journal_entry_id' => $postedJournal->id,
                'journal_line_id' => $apLine->id,
                'financial_period_id' => $allocation->financial_period_id,
                'entry_date' => $allocation->allocation_date,
                'due_date' => $allocation->due_date ?? $allocation->allocation_date,
                'description' => "Landed Cost {$number}",
                'currency' => $allocation->currency,
                'debit_minor' => 0,
                'credit_minor' => $totalAmountMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $totalAmountMinor,
                'fx_rate_e6' => 1000000,
                'created_by' => $actorId,
            ]);

            foreach ($splits as $lineId => $split) {
                /** @var LandedCostAllocationLine $line */
                $line = $allocation->lines->firstWhere('id', $lineId);
                $movementId = null;

                if ($split['capitalized_amount_minor'] > 0) {
                    $movement = $this->inventoryService->recordLandedCostValueAdjustment(
                        sourceType: 'landed_cost_allocation',
                        sourceId: $allocation->id,
                        sourceLineId: $line->id,
                        movementDate: (string) $allocation->allocation_date,
                        productId: $line->product_id,
                        unitOfMeasureId: $line->unit_of_measure_id,
                        currency: $allocation->currency,
                        valueMinor: $split['capitalized_amount_minor'],
                        journalEntryId: $postedJournal->id,
                        actorId: $actorId,
                        warehouseId: (string) $goodsReceipt->warehouse_id,
                    );
                    $movementId = $movement->id;
                }

                $line->update([
                    'capitalized_amount_minor' => $split['capitalized_amount_minor'],
                    'expensed_amount_minor' => $split['expensed_amount_minor'],
                    'stock_movement_id' => $movementId,
                ]);
            }

            $allocation->update([
                'number' => $number,
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'payable_entry_id' => $payableEntry->id,
                'posted_by' => $actorId,
                'posted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $allocation->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'landed_cost_allocation.post',
                entityType: 'landed_cost_allocation',
                entityId: $allocation->id,
                before: $before,
                after: $allocation->fresh($this->defaultRelations())->toArray(),
            );

            return $allocation->fresh($this->defaultRelations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): LandedCostAllocation
    {
        return DB::transaction(function () use ($id, $actorId): LandedCostAllocation {
            /** @var LandedCostAllocation $allocation */
            $allocation = LandedCostAllocation::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($allocation->status === 'posted') {
                throw ValidationException::withMessages(['status' => [__('Posted landed cost allocations cannot be cancelled.')]]);
            }

            if ($allocation->status === 'cancelled') {
                return $allocation->load($this->defaultRelations());
            }

            $before = $allocation->toArray();
            $allocation->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $allocation->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'landed_cost_allocation.cancel',
                entityType: 'landed_cost_allocation',
                entityId: $allocation->id,
                before: $before,
                after: $allocation->fresh($this->defaultRelations())->toArray(),
            );

            return $allocation->fresh($this->defaultRelations());
        });
    }

    private function transition(
        string $id,
        string $from,
        string $to,
        string $actorColumn,
        string $timestampColumn,
        string $auditAction,
        ?int $actorId = null,
    ): LandedCostAllocation {
        return DB::transaction(function () use ($id, $from, $to, $actorColumn, $timestampColumn, $auditAction, $actorId): LandedCostAllocation {
            /** @var LandedCostAllocation $allocation */
            $allocation = LandedCostAllocation::query()->with('lines')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($allocation->status === $to) {
                return $allocation->load($this->defaultRelations());
            }

            if ($allocation->status !== $from) {
                throw ValidationException::withMessages(['status' => [__('Only :from landed cost allocations can be moved to :to.', [
                    'from' => $from,
                    'to' => $to,
                ])]]);
            }

            if ($allocation->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Cannot submit landed cost allocation without line items.')]]);
            }

            $number = $allocation->number;
            if (! $number) {
                $number = $this->numberAllocator->nextNumber('purchasing.landed_cost', 'LC', $allocation->allocation_date);
            }

            $before = $allocation->toArray();
            $allocation->update([
                'number' => $number,
                'status' => $to,
                $actorColumn => $actorId,
                $timestampColumn => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $allocation->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: $auditAction,
                entityType: 'landed_cost_allocation',
                entityId: $allocation->id,
                before: $before,
                after: $allocation->fresh($this->defaultRelations())->toArray(),
            );

            return $allocation->fresh($this->defaultRelations());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDraftData(array $data): array
    {
        $allocationMethod = (string) ($data['allocation_method'] ?? 'by_value');
        if (! in_array($allocationMethod, self::ALLOCATION_METHODS, true)) {
            throw ValidationException::withMessages(['allocation_method' => [__('Selected allocation method is not supported.')]]);
        }

        $costAmountMinor = (int) ($data['cost_amount_minor'] ?? 0);
        $taxAmountMinor = (int) ($data['tax_amount_minor'] ?? 0);

        if ($costAmountMinor <= 0) {
            throw ValidationException::withMessages(['cost_amount_minor' => [__('Landed cost amount must be greater than zero.')]]);
        }

        if ($taxAmountMinor < 0) {
            throw ValidationException::withMessages(['tax_amount_minor' => [__('Tax amount cannot be negative.')]]);
        }

        $fxRateE6 = (int) ($data['fx_rate_e6'] ?? 1000000);
        if ($fxRateE6 !== 1000000) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
        }

        return [
            'goods_receipt_id' => (string) ($data['goods_receipt_id'] ?? ''),
            'supplier_id' => (string) ($data['supplier_id'] ?? ''),
            'allocation_date' => Carbon::parse($data['allocation_date'] ?? now())->toDateString(),
            'due_date' => isset($data['due_date']) && $data['due_date'] !== '' ? Carbon::parse($data['due_date'])->toDateString() : null,
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'allocation_method' => $allocationMethod,
            'cost_amount_minor' => $costAmountMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'lines' => $data['lines'] ?? [],
        ];
    }

    private function activeSupplier(string $supplierId): Supplier
    {
        /** @var Supplier|null $supplier */
        $supplier = Supplier::query()->where('id', $supplierId)->first();

        if (! $supplier || $supplier->status !== 'active') {
            throw ValidationException::withMessages(['supplier_id' => [__('Supplier must be active.')]]);
        }

        return $supplier;
    }

    private function confirmedGoodsReceipt(string $goodsReceiptId): GoodsReceipt
    {
        /** @var GoodsReceipt|null $goodsReceipt */
        $goodsReceipt = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'warehouse.branch', 'lines.product', 'lines.unitOfMeasure', 'lines.purchaseOrderLine'])
            ->where('id', $goodsReceiptId)
            ->lockForUpdate()
            ->first();

        if (! $goodsReceipt || $goodsReceipt->status !== 'confirmed') {
            throw ValidationException::withMessages(['goods_receipt_id' => [__('Landed cost can only reference a confirmed Goods Receipt.')]]);
        }

        if (! $goodsReceipt->purchaseOrder) {
            throw ValidationException::withMessages(['goods_receipt_id' => [__('Goods Receipt purchase order is missing.')]]);
        }

        return $goodsReceipt;
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function buildLines(GoodsReceipt $goodsReceipt, array $validated): array
    {
        $eligibleReceiptLines = $goodsReceipt->lines
            ->filter(fn (GoodsReceiptLine $line): bool => $line->product?->type === 'stock')
            ->values();

        if ($eligibleReceiptLines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('Goods Receipt does not contain stock product lines eligible for landed cost capitalization.')]]);
        }

        $requestedLines = collect($validated['lines']);
        $selectedIds = $requestedLines->pluck('goods_receipt_line_id')->filter()->map(fn ($id): string => (string) $id)->values();

        if ($selectedIds->isEmpty()) {
            $selectedIds = $eligibleReceiptLines->pluck('id')->map(fn ($id): string => (string) $id)->values();
        }

        if ($selectedIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('Each Goods Receipt line can only appear once.')]]);
        }

        $receiptLinesById = $eligibleReceiptLines->keyBy('id');
        $selectedLines = $selectedIds->map(function (string $lineId) use ($receiptLinesById): GoodsReceiptLine {
            /** @var GoodsReceiptLine|null $line */
            $line = $receiptLinesById->get($lineId);
            if (! $line) {
                throw ValidationException::withMessages(['lines' => [__('Selected Goods Receipt line is not eligible for landed cost allocation.')]]);
            }

            return $line;
        });

        $manualAmounts = $requestedLines
            ->filter(fn ($line): bool => isset($line['goods_receipt_line_id']))
            ->mapWithKeys(fn ($line): array => [(string) $line['goods_receipt_line_id'] => (int) ($line['allocated_cost_minor'] ?? 0)]);

        $weights = $selectedLines->map(function (GoodsReceiptLine $line) use ($validated): int {
            if ($validated['allocation_method'] === 'by_quantity') {
                return (int) $line->quantity_e6;
            }

            return $this->receiptLineValueMinor($line);
        })->all();

        $allocatedAmounts = $validated['allocation_method'] === 'manual'
            ? $this->manualAllocations($selectedLines, $manualAmounts, $validated['cost_amount_minor'])
            : $this->allocateByWeights($validated['cost_amount_minor'], $weights);

        $lines = [];
        foreach ($selectedLines as $index => $line) {
            $lines[] = [
                'goods_receipt_line_id' => $line->id,
                'product_id' => $line->product_id,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'quantity_e6_snapshot' => (int) $line->quantity_e6,
                'receipt_value_minor_snapshot' => $this->receiptLineValueMinor($line),
                'allocated_cost_minor' => $allocatedAmounts[$index],
                'capitalized_amount_minor' => 0,
                'expensed_amount_minor' => 0,
                'stock_movement_id' => null,
            ];
        }

        return $lines;
    }

    /**
     * @param  Collection<int, GoodsReceiptLine>  $selectedLines
     * @param  Collection<string, int>  $manualAmounts
     * @return list<int>
     */
    private function manualAllocations(Collection $selectedLines, Collection $manualAmounts, int $costAmountMinor): array
    {
        $amounts = [];
        $sum = 0;

        foreach ($selectedLines as $line) {
            $amount = (int) ($manualAmounts->get((string) $line->id) ?? 0);
            if ($amount < 0) {
                throw ValidationException::withMessages(['lines' => [__('Manual landed cost allocations cannot be negative.')]]);
            }
            $amounts[] = $amount;
            $sum += $amount;
        }

        if ($sum !== $costAmountMinor) {
            throw ValidationException::withMessages(['lines' => [__('Manual landed cost line amounts must equal the header cost amount.')]]);
        }

        return $amounts;
    }

    /**
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function allocateByWeights(int $amountMinor, array $weights): array
    {
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            throw ValidationException::withMessages(['lines' => [__('Selected receipt lines do not have positive allocation weight.')]]);
        }

        $allocated = [];
        $running = 0;
        $lastIndex = count($weights) - 1;

        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex) {
                $share = $amountMinor - $running;
            } else {
                $share = intdiv($amountMinor * $weight, $totalWeight);
                $running += $share;
            }

            $allocated[] = $share;
        }

        return $allocated;
    }

    /**
     * @return array<string, array{capitalized_amount_minor:int, expensed_amount_minor:int}>
     */
    private function splitCapitalization(LandedCostAllocation $allocation): array
    {
        $goodsReceipt = $allocation->goodsReceipt;
        $warehouseId = (string) $goodsReceipt->warehouse_id;
        $productIds = $allocation->lines->pluck('product_id')->unique()->values()->all();

        $balances = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('currency', $allocation->currency)
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        $availableByProduct = [];
        foreach ($balances as $productId => $balance) {
            $availableByProduct[$productId] = max(0, (int) $balance->quantity_e6);
        }

        $splits = [];
        foreach ($allocation->lines as $line) {
            $availableQty = min($availableByProduct[$line->product_id] ?? 0, (int) $line->quantity_e6_snapshot);
            $allocated = (int) $line->allocated_cost_minor;
            $capitalized = $availableQty <= 0
                ? 0
                : intdiv($allocated * $availableQty, (int) $line->quantity_e6_snapshot);
            $expensed = $allocated - $capitalized;

            $availableByProduct[$line->product_id] = max(0, ($availableByProduct[$line->product_id] ?? 0) - $availableQty);

            $splits[(string) $line->id] = [
                'capitalized_amount_minor' => $capitalized,
                'expensed_amount_minor' => $expensed,
            ];
        }

        return $splits;
    }

    private function receiptLineValueMinor(GoodsReceiptLine $line): int
    {
        $unitCostMinor = (int) ($line->purchaseOrderLine?->unit_price_minor ?? 0);
        if ($unitCostMinor <= 0) {
            throw ValidationException::withMessages(['lines' => [__('Goods Receipt line is missing a positive purchase unit cost.')]]);
        }

        $product = (int) $line->quantity_e6 * $unitCostMinor;
        if ($product % self::QUANTITY_SCALE !== 0) {
            throw ValidationException::withMessages(['lines' => [__('Goods Receipt line value contains fractional minor units.')]]);
        }

        return intdiv($product, self::QUANTITY_SCALE);
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return [
            'supplier',
            'goodsReceipt.purchaseOrder.supplier',
            'goodsReceipt.warehouse.branch',
            'lines.product',
            'lines.unitOfMeasure',
            'lines.goodsReceiptLine',
            'lines.stockMovement',
            'journalEntry',
            'payableEntry',
        ];
    }
}
