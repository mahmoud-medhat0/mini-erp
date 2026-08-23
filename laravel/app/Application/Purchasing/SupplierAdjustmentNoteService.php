<?php

namespace App\Application\Purchasing;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\Supplier;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierAdjustmentNoteService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public const ALLOWED_DIRECTIONS = ['decrease_payable', 'increase_payable'];

    public const ALLOWED_TAX_MODES = ['none', 'manual_rate', 'manual_amount'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): SupplierAdjustmentNote
    {
        return DB::transaction(function () use ($data, $actorId): SupplierAdjustmentNote {
            $supplierId = $data['supplier_id'] ?? null;
            if (! $supplierId) {
                throw ValidationException::withMessages(['supplier_id' => ['Supplier is required.']]);
            }

            /** @var Supplier|null $supplier */
            $supplier = Supplier::query()->where('id', $supplierId)->first();
            if (! $supplier || $supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => ['Supplier must be active.']]);
            }

            $direction = $this->validateDirection($data['direction'] ?? null);
            [$taxMode, $taxRateBps, $manualTaxAmountMinor] = $this->resolveTaxConfiguration($data, 'none', 0, 0);

            $currency = $data['currency'] ?? 'USD';

            $adjustmentDate = $data['adjustment_date'] ?? null;
            if (! $adjustmentDate) {
                throw ValidationException::withMessages(['adjustment_date' => ['Adjustment date is required.']]);
            }

            $period = $this->resolveFinancialPeriodForDate($adjustmentDate);

            $supplierBillId = $data['supplier_bill_id'] ?? null;
            if ($supplierBillId) {
                $this->validateSupplierBillSource($supplierBillId, $supplier->id, $currency);
            }

            $purchaseReturnId = $data['purchase_return_id'] ?? null;
            if ($purchaseReturnId) {
                $this->validatePurchaseReturnSource($purchaseReturnId, $supplier->id, $currency);
            }

            $validatedLines = $this->validateAndCalculateLines(
                $data['lines'] ?? [],
                $taxMode,
                $taxRateBps,
                $supplierBillId,
                $purchaseReturnId
            );

            $subtotalMinor = array_sum(array_column($validatedLines, 'line_subtotal_minor'));
            $computedTaxMinor = array_sum(array_column($validatedLines, 'tax_minor'));
            $taxMinor = $taxMode === 'manual_amount' ? $manualTaxAmountMinor : $computedTaxMinor;
            $headerTaxRateBps = $taxMode === 'manual_rate' ? $taxRateBps : 0;
            $totalMinor = $subtotalMinor + $taxMinor;

            /** @var SupplierAdjustmentNote $note */
            $note = SupplierAdjustmentNote::query()->create([
                'supplier_id' => $supplier->id,
                'supplier_bill_id' => $supplierBillId,
                'purchase_return_id' => $purchaseReturnId,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'adjustment_date' => $adjustmentDate,
                'direction' => $direction,
                'ui_label' => $data['ui_label'] ?? null,
                'status' => 'draft',
                'currency' => $currency,
                'subtotal_minor' => $subtotalMinor,
                'tax_rate_bps' => $headerTaxRateBps,
                'tax_minor' => $taxMinor,
                'total_minor' => $totalMinor,
                'tax_mode' => $taxMode,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($validatedLines as $index => $line) {
                $note->lines()->create([
                    'line_no' => $index + 1,
                    'supplier_bill_line_id' => $line['supplier_bill_line_id'],
                    'purchase_return_line_id' => $line['purchase_return_line_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_cost_minor' => $line['unit_cost_minor'],
                    'line_subtotal_minor' => $line['line_subtotal_minor'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_minor' => $line['tax_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            $note->load(['supplier', 'supplierBill', 'purchaseReturn', 'lines']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_adjustment_note.create',
                entityType: 'supplier_adjustment_note',
                entityId: $note->id,
                before: null,
                after: $note->toArray(),
            );

            return $note;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): SupplierAdjustmentNote
    {
        return DB::transaction(function () use ($id, $data, $actorId): SupplierAdjustmentNote {
            /** @var SupplierAdjustmentNote $note */
            $note = SupplierAdjustmentNote::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft supplier adjustment notes can be updated.']]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $note->lock_version) {
                throw ValidationException::withMessages(['lock_version' => ['The record has been modified by another user. Please refresh and try again.']]);
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->where('id', $note->supplier_id)->firstOrFail();

            $direction = $this->validateDirection($data['direction'] ?? $note->direction);
            [$taxMode, $taxRateBps, $manualTaxAmountMinor] = $this->resolveTaxConfiguration(
                $data,
                $note->tax_mode,
                $note->tax_mode === 'manual_rate' ? (int) $note->tax_rate_bps : 0,
                (int) $note->tax_minor
            );

            $supplierBillId = $data['supplier_bill_id'] ?? $note->supplier_bill_id;
            if ($supplierBillId) {
                $this->validateSupplierBillSource($supplierBillId, $supplier->id, $note->currency);
            }

            $purchaseReturnId = $data['purchase_return_id'] ?? $note->purchase_return_id;
            if ($purchaseReturnId) {
                $this->validatePurchaseReturnSource($purchaseReturnId, $supplier->id, $note->currency);
            }

            $adjustmentDate = $data['adjustment_date'] ?? $note->adjustment_date;
            $period = $this->resolveFinancialPeriodForDate($adjustmentDate);

            $validatedLines = $this->validateAndCalculateLines(
                $data['lines'] ?? [],
                $taxMode,
                $taxRateBps,
                $supplierBillId,
                $purchaseReturnId
            );

            $subtotalMinor = array_sum(array_column($validatedLines, 'line_subtotal_minor'));
            $computedTaxMinor = array_sum(array_column($validatedLines, 'tax_minor'));
            $taxMinor = $taxMode === 'manual_amount' ? $manualTaxAmountMinor : $computedTaxMinor;
            $headerTaxRateBps = $taxMode === 'manual_rate' ? $taxRateBps : 0;
            $totalMinor = $subtotalMinor + $taxMinor;

            $before = $note->toArray();

            $note->update([
                'supplier_bill_id' => $supplierBillId,
                'purchase_return_id' => $purchaseReturnId,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'adjustment_date' => $adjustmentDate,
                'direction' => $direction,
                'ui_label' => $data['ui_label'] ?? $note->ui_label,
                'subtotal_minor' => $subtotalMinor,
                'tax_rate_bps' => $headerTaxRateBps,
                'tax_minor' => $taxMinor,
                'total_minor' => $totalMinor,
                'tax_mode' => $taxMode,
                'reason' => $data['reason'] ?? $note->reason,
                'notes' => $data['notes'] ?? $note->notes,
                'updated_by' => $actorId,
                'lock_version' => $note->lock_version + 1,
            ]);

            $note->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $note->lines()->create([
                    'line_no' => $index + 1,
                    'supplier_bill_line_id' => $line['supplier_bill_line_id'],
                    'purchase_return_line_id' => $line['purchase_return_line_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_cost_minor' => $line['unit_cost_minor'],
                    'line_subtotal_minor' => $line['line_subtotal_minor'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_minor' => $line['tax_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            $note->load(['supplier', 'supplierBill', 'purchaseReturn', 'lines']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_adjustment_note.update',
                entityType: 'supplier_adjustment_note',
                entityId: $note->id,
                before: $before,
                after: $note->toArray(),
            );

            return $note;
        });
    }

    public function submit(string $id, ?int $actorId = null): SupplierAdjustmentNote
    {
        return DB::transaction(function () use ($id, $actorId): SupplierAdjustmentNote {
            /** @var SupplierAdjustmentNote $note */
            $note = SupplierAdjustmentNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft supplier adjustment notes can be submitted.']]);
            }

            if ($note->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Supplier adjustment note must have at least one line item before submitting.']]);
            }

            $before = $note->toArray();

            $note->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $note->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_adjustment_note.submit',
                entityType: 'supplier_adjustment_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['supplier', 'lines'])->toArray(),
            );

            return $note->fresh(['supplier', 'lines']);
        });
    }

    public function approve(string $id, ?int $actorId = null): SupplierAdjustmentNote
    {
        return DB::transaction(function () use ($id, $actorId): SupplierAdjustmentNote {
            /** @var SupplierAdjustmentNote $note */
            $note = SupplierAdjustmentNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status === 'approved') {
                return $note->load(['supplier', 'lines']);
            }

            if (! in_array($note->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or submitted supplier adjustment notes can be approved.']]);
            }

            if ($note->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Supplier adjustment note must have at least one line item before approving.']]);
            }

            $before = $note->toArray();

            $note->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $note->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_adjustment_note.approve',
                entityType: 'supplier_adjustment_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['supplier', 'lines'])->toArray(),
            );

            return $note->fresh(['supplier', 'lines']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): SupplierAdjustmentNote
    {
        return DB::transaction(function () use ($id, $actorId): SupplierAdjustmentNote {
            /** @var SupplierAdjustmentNote $note */
            $note = SupplierAdjustmentNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status === 'posted') {
                throw ValidationException::withMessages(['status' => ['Posted supplier adjustment notes cannot be cancelled in this slice.']]);
            }

            if ($note->status === 'cancelled') {
                return $note->load(['supplier', 'lines']);
            }

            $before = $note->toArray();

            $note->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $note->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_adjustment_note.cancel',
                entityType: 'supplier_adjustment_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['supplier', 'lines'])->toArray(),
            );

            return $note->fresh(['supplier', 'lines']);
        });
    }

    public function post(string $id, ?int $actorId = null): SupplierAdjustmentNote
    {
        return DB::transaction(function () use ($id, $actorId): SupplierAdjustmentNote {
            /** @var SupplierAdjustmentNote $note */
            $note = SupplierAdjustmentNote::query()->with(['lines', 'supplier'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status === 'posted') {
                return $note->load(['supplier', 'lines', 'journalEntry', 'payableEntry']);
            }

            if ($note->status !== 'approved') {
                throw ValidationException::withMessages(['status' => ['Only approved supplier adjustment notes can be posted.']]);
            }

            if ($note->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['Cannot post supplier adjustment note without line items.']]);
            }

            $noteDate = $note->adjustment_date->format('Y-m-d');
            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $note->financial_period_id, $noteDate);
            if ($period->fiscal_year_id !== $note->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period does not belong to the note fiscal year.']]);
            }

            $isDecrease = $note->direction === 'decrease_payable';

            $subtotalMinor = (int) $note->subtotal_minor;
            $taxMinor = (int) $note->tax_minor;
            $totalMinor = (int) $note->total_minor;

            if ($totalMinor <= 0) {
                throw ValidationException::withMessages(['total_minor' => ['Cannot post a supplier adjustment note with a zero or negative total.']]);
            }

            $apAccount = $this->mappingService->getAccount('ap_control');
            if ($apAccount->currency !== $note->currency) {
                throw ValidationException::withMessages(['currency' => ['Mapped AP Control account currency must match note currency.']]);
            }

            $contraAccount = null;
            if ($subtotalMinor > 0) {
                $contraAccount = $this->mappingService->getAccount($isDecrease ? 'purchase_returns_allowances' : 'purchase_expense');
                if ($contraAccount->currency !== $note->currency) {
                    throw ValidationException::withMessages(['currency' => ['Mapped contra account currency must match note currency.']]);
                }
            }

            $taxAccount = null;
            if ($taxMinor > 0) {
                $taxAccount = $this->mappingService->getAccount('input_tax_receivable');
                if ($taxAccount->currency !== $note->currency) {
                    throw ValidationException::withMessages(['currency' => ['Mapped Input Tax Receivable account currency must match note currency.']]);
                }
            }

            $number = $note->number;
            if (! $number) {
                $year = Carbon::parse($note->adjustment_date)->format('Y');
                $seq = $this->numberAllocator->nextValue('supplier.adjustment_note');
                $number = 'SAN-'.$year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $before = $note->toArray();

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $note->adjustment_date,
                'financial_period_id' => $note->financial_period_id,
                'source_type' => 'supplier_adjustment_note',
                'source_id' => $note->id,
                'description' => "Supplier Adjustment Note {$number} - {$note->supplier->name}",
                'currency' => $note->currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $lineNo = 1;

            if ($subtotalMinor > 0 && $contraAccount) {
                $contraMemo = $isDecrease ? 'Purchase Returns & Allowances' : 'Purchase Expense';

                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $contraAccount->id,
                    'memo' => "{$contraMemo} - Note {$number}",
                    'debit_minor' => $subtotalMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $subtotalMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $note->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            if ($taxMinor > 0 && $taxAccount) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $taxAccount->id,
                    'memo' => "Input Tax Receivable - Note {$number}",
                    'debit_minor' => $taxMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $taxMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $note->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            $apLine = $journalEntry->lines()->create([
                'line_no' => $lineNo++,
                'account_id' => $apAccount->id,
                'memo' => "AP Control - Note {$number}",
                'debit_minor' => 0,
                'credit_minor' => $totalMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $totalMinor,
                'currency' => $note->currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var PayableEntry $payableEntry */
            $payableEntry = PayableEntry::query()->create([
                'supplier_id' => $note->supplier_id,
                'source_type' => 'supplier_adjustment_note',
                'source_id' => $note->id,
                'journal_entry_id' => $postedJournal->id,
                'journal_line_id' => $apLine->id,
                'financial_period_id' => $note->financial_period_id,
                'entry_date' => $note->adjustment_date,
                'description' => "Supplier Adjustment Note {$number}",
                'currency' => $note->currency,
                'debit_minor' => $isDecrease ? $totalMinor : 0,
                'credit_minor' => $isDecrease ? 0 : $totalMinor,
                'debit_txn_minor' => $isDecrease ? $totalMinor : 0,
                'credit_txn_minor' => $isDecrease ? 0 : $totalMinor,
                'fx_rate_e6' => 1000000,
                'created_by' => $actorId,
            ]);

            $note->number = $number;
            $note->status = 'posted';
            $note->journal_entry_id = $postedJournal->id;
            $note->payable_entry_id = $payableEntry->id;
            $note->posted_by = $actorId;
            $note->posted_at = Carbon::now();
            $note->updated_by = $actorId;
            $note->lock_version = $note->lock_version + 1;
            $note->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_adjustment_note.post',
                entityType: 'supplier_adjustment_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['supplier', 'lines', 'journalEntry', 'payableEntry'])->toArray(),
            );

            return $note->fresh(['supplier', 'lines', 'journalEntry', 'payableEntry']);
        });
    }

    private function resolveFinancialPeriodForDate(string $date): FinancialPeriod
    {
        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereIn('status', ['open', 'reopened'])
            ->first();

        if (! $period) {
            throw ValidationException::withMessages(['adjustment_date' => ["No open financial period covers date {$date}."]]);
        }

        return $period;
    }

    private function validateDirection(?string $direction): string
    {
        if (! $direction || ! in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw ValidationException::withMessages(['direction' => ['Direction must be one of: '.implode(', ', self::ALLOWED_DIRECTIONS).'.']]);
        }

        return $direction;
    }

    private function resolveTaxConfiguration(array $data, string $fallbackMode, int $fallbackRateBps, int $fallbackTaxMinor): array
    {
        $taxMode = $data['tax_mode'] ?? $fallbackMode;
        if (! in_array($taxMode, self::ALLOWED_TAX_MODES, true)) {
            throw ValidationException::withMessages(['tax_mode' => ['Tax mode must be one of: '.implode(', ', self::ALLOWED_TAX_MODES).'.']]);
        }

        $taxRateBps = 0;
        if ($taxMode === 'manual_rate') {
            $taxRateBps = (int) ($data['tax_rate_bps'] ?? $fallbackRateBps);
            if ($taxRateBps < 0) {
                throw ValidationException::withMessages(['tax_rate_bps' => ['Tax rate cannot be negative.']]);
            }
        }

        $manualTaxAmountMinor = 0;
        if ($taxMode === 'manual_amount') {
            $manualTaxAmountMinor = (int) ($data['tax_amount_minor'] ?? $fallbackTaxMinor);
            if ($manualTaxAmountMinor < 0) {
                throw ValidationException::withMessages(['tax_amount_minor' => ['Manual tax amount cannot be negative.']]);
            }
        }

        return [$taxMode, $taxRateBps, $manualTaxAmountMinor];
    }

    private function validateSupplierBillSource(string $supplierBillId, string $supplierId, string $currency): void
    {
        /** @var SupplierBill|null $supplierBill */
        $supplierBill = SupplierBill::query()->where('id', $supplierBillId)->first();
        if (! $supplierBill || $supplierBill->supplier_id !== $supplierId) {
            throw ValidationException::withMessages(['supplier_bill_id' => ['Supplier Bill does not belong to the selected supplier.']]);
        }
        if ($supplierBill->status !== 'posted') {
            throw ValidationException::withMessages(['supplier_bill_id' => ['Supplier Bill must be posted.']]);
        }
        if ($supplierBill->currency !== $currency) {
            throw ValidationException::withMessages(['supplier_bill_id' => ['Currency must match the Supplier Bill currency.']]);
        }
    }

    private function validatePurchaseReturnSource(string $purchaseReturnId, string $supplierId, string $currency): void
    {
        /** @var PurchaseReturn|null $purchaseReturn */
        $purchaseReturn = PurchaseReturn::query()->where('id', $purchaseReturnId)->first();
        if (! $purchaseReturn || $purchaseReturn->supplier_id !== $supplierId) {
            throw ValidationException::withMessages(['purchase_return_id' => ['Purchase Return does not belong to the selected supplier.']]);
        }
        if ($purchaseReturn->currency !== $currency) {
            throw ValidationException::withMessages(['purchase_return_id' => ['Currency must match the Purchase Return currency.']]);
        }
    }

    private function validateAndCalculateLines(array $lines, string $taxMode, int $taxRateBps, ?string $supplierBillId, ?string $purchaseReturnId): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => ['At least one line item is required.']]);
        }

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;

            $description = $line['description'] ?? null;
            if (! is_string($description) || trim($description) === '') {
                throw ValidationException::withMessages(["lines.{$index}.description" => ["Description on line {$lineIndex} is required."]]);
            }

            $quantityE6 = null;
            if (isset($line['quantity_e6']) && $line['quantity_e6'] !== null && $line['quantity_e6'] !== '') {
                $quantityE6 = (int) $line['quantity_e6'];
                if ($quantityE6 <= 0) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => ["Quantity on line {$lineIndex} must be greater than zero."]]);
                }
            }

            $unitCostMinor = (int) ($line['unit_cost_minor'] ?? 0);
            if ($unitCostMinor < 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_cost_minor" => ["Unit cost on line {$lineIndex} cannot be negative."]]);
            }

            if ($quantityE6 !== null) {
                $this->assertMultiplyWithinRange($quantityE6, $unitCostMinor, "lines.{$index}.line_subtotal_minor");
                $lineSubtotalMinor = intdiv($quantityE6 * $unitCostMinor, 1000000);
            } else {
                $lineSubtotalMinor = $unitCostMinor;
            }

            $lineTaxRateBps = $taxMode === 'manual_rate' ? $taxRateBps : 0;
            $lineTaxMinor = $taxMode === 'manual_rate'
                ? $this->calculateTaxMinor($lineSubtotalMinor, $taxRateBps, "lines.{$index}.tax_minor")
                : 0;

            $supplierBillLineId = $line['supplier_bill_line_id'] ?? null;
            if ($supplierBillLineId) {
                if (! $supplierBillId) {
                    throw ValidationException::withMessages(["lines.{$index}.supplier_bill_line_id" => ["Line {$lineIndex} references a Supplier Bill line but no Supplier Bill was selected."]]);
                }

                /** @var SupplierBillLine|null $billLine */
                $billLine = SupplierBillLine::query()->where('id', $supplierBillLineId)->first();
                if (! $billLine || $billLine->supplier_bill_id !== $supplierBillId) {
                    throw ValidationException::withMessages(["lines.{$index}.supplier_bill_line_id" => ["Line {$lineIndex} does not belong to the selected Supplier Bill."]]);
                }
            }

            $purchaseReturnLineId = $line['purchase_return_line_id'] ?? null;
            if ($purchaseReturnLineId) {
                if (! $purchaseReturnId) {
                    throw ValidationException::withMessages(["lines.{$index}.purchase_return_line_id" => ["Line {$lineIndex} references a Purchase Return line but no Purchase Return was selected."]]);
                }

                /** @var PurchaseReturnLine|null $returnLine */
                $returnLine = PurchaseReturnLine::query()->where('id', $purchaseReturnLineId)->first();
                if (! $returnLine || $returnLine->purchase_return_id !== $purchaseReturnId) {
                    throw ValidationException::withMessages(["lines.{$index}.purchase_return_line_id" => ["Line {$lineIndex} does not belong to the selected Purchase Return."]]);
                }
            }

            $validatedLines[] = [
                'supplier_bill_line_id' => $supplierBillLineId,
                'purchase_return_line_id' => $purchaseReturnLineId,
                'description' => $description,
                'quantity_e6' => $quantityE6,
                'unit_cost_minor' => $unitCostMinor,
                'line_subtotal_minor' => $lineSubtotalMinor,
                'tax_rate_bps' => $lineTaxRateBps,
                'tax_minor' => $lineTaxMinor,
                'line_total_minor' => $this->checkedAdd($lineSubtotalMinor, $lineTaxMinor),
            ];
        }

        return $validatedLines;
    }

    private function calculateTaxMinor(int $baseMinor, int $rateBps, string $field): int
    {
        if ($baseMinor === 0 || $rateBps === 0) {
            return 0;
        }

        $this->assertMultiplyWithinRange($baseMinor, $rateBps, $field);

        return intdiv(($baseMinor * $rateBps) + 5000, 10000);
    }

    private function checkedAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw ValidationException::withMessages(['line_total_minor' => ['Calculation exceeds supported integer range.']]);
        }

        return $left + $right;
    }

    private function assertMultiplyWithinRange(int $left, int $right, string $field): void
    {
        if ($left !== 0 && $right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw ValidationException::withMessages([$field => ['Calculation exceeds supported integer range.']]);
        }
    }
}
