<?php

namespace App\Application\Purchasing;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Support\CurrencyInput;
use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxPeriodGuard;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierBillService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
        private readonly TaxCalculationService $taxCalcService,
        private readonly TaxPeriodGuard $taxPeriodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): SupplierBill
    {
        return DB::transaction(function () use ($data, $actorId): SupplierBill {
            $supplierId = $data['supplier_id'] ?? null;
            if (! $supplierId) {
                throw ValidationException::withMessages(['supplier_id' => [__('Supplier is required.')]]);
            }

            /** @var Supplier|null $supplier */
            $supplier = Supplier::query()->where('id', $supplierId)->first();
            if (! $supplier || $supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => [__('Supplier must be active.')]]);
            }

            $currency = CurrencyInput::required($data['currency'] ?? null);
            $fxRateE6 = (int) ($data['fx_rate_e6'] ?? 1000000);
            if ($fxRateE6 !== 1000000) {
                throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
            }

            $billDate = $data['bill_date'] ?? null;
            if (! $billDate) {
                throw ValidationException::withMessages(['bill_date' => [__('Bill date is required.')]]);
            }

            $dueDate = $data['due_date'] ?? null;

            // Resolve FiscalYear & FinancialPeriod for bill_date
            $period = $this->resolveFinancialPeriodForDate($billDate);

            if (! empty($data['purchase_order_id']) && ! empty($data['goods_receipt_id'])) {
                throw ValidationException::withMessages([
                    'source' => [__('Supplier bill can reference either a Purchase Order or a Goods Receipt, not both.')],
                ]);
            }

            // Optional source models
            $purchaseOrder = null;
            if (! empty($data['purchase_order_id'])) {
                /** @var PurchaseOrder|null $purchaseOrder */
                $purchaseOrder = PurchaseOrder::query()->where('id', $data['purchase_order_id'])->lockForUpdate()->first();
                if (! $purchaseOrder || $purchaseOrder->status !== 'confirmed') {
                    throw ValidationException::withMessages(['purchase_order_id' => [__('Supplier bills can only reference confirmed Purchase Orders.')]]);
                }
                if ($purchaseOrder->supplier_id !== $supplier->id) {
                    throw ValidationException::withMessages(['supplier_id' => [__('Supplier must match the Purchase Order supplier.')]]);
                }
                if ($purchaseOrder->currency !== $currency) {
                    throw ValidationException::withMessages(['currency' => [__('Currency must match the Purchase Order currency.')]]);
                }
            }

            $goodsReceipt = null;
            if (! empty($data['goods_receipt_id'])) {
                /** @var GoodsReceipt|null $goodsReceipt */
                $goodsReceipt = GoodsReceipt::query()->with('purchaseOrder')->where('id', $data['goods_receipt_id'])->lockForUpdate()->first();
                if (! $goodsReceipt || $goodsReceipt->status !== 'confirmed') {
                    throw ValidationException::withMessages(['goods_receipt_id' => [__('Supplier bills can only reference confirmed Goods Receipts.')]]);
                }
                $grSupplierId = $goodsReceipt->purchaseOrder?->supplier_id;
                if ($grSupplierId && $grSupplierId !== $supplier->id) {
                    throw ValidationException::withMessages(['supplier_id' => [__('Supplier must match the Goods Receipt supplier.')]]);
                }
                $grCurrency = CurrencyInput::related($goodsReceipt->purchaseOrder?->currency, 'currency', 'Goods Receipt');
                if ($grCurrency !== $currency) {
                    throw ValidationException::withMessages(['currency' => [__('Currency must match the Goods Receipt currency.')]]);
                }
            }

            $validatedLines = $this->validateAndCalculateLines($data['lines'] ?? [], $purchaseOrder, $goodsReceipt, null, $billDate);

            $subtotalMinor = array_sum(array_column($validatedLines, 'line_total_minor'));
            $taxAmountMinor = array_sum(array_column($validatedLines, 'tax_amount_minor'));
            $totalMinor = $subtotalMinor + $taxAmountMinor;

            /** @var SupplierBill $bill */
            $bill = SupplierBill::query()->create([
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'goods_receipt_id' => $goodsReceipt?->id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'currency' => $currency,
                'fx_rate_e6' => $fxRateE6,
                'subtotal_minor' => $subtotalMinor,
                'tax_amount_minor' => $taxAmountMinor,
                'total_minor' => $totalMinor,
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($validatedLines as $index => $line) {
                $bill->lines()->create([
                    'line_no' => $index + 1,
                    'purchase_order_line_id' => $line['purchase_order_line_id'],
                    'goods_receipt_line_id' => $line['goods_receipt_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_cost_minor' => $line['unit_cost_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'tax_code_id' => $line['tax_code_id'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount_minor' => $line['tax_amount_minor'],
                    'gross_amount_minor' => $line['gross_amount_minor'],
                ]);
            }

            $bill->load(['supplier', 'purchaseOrder', 'goodsReceipt', 'lines.product', 'lines.unitOfMeasure', 'lines.taxCode']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_bill.create',
                entityType: 'supplier_bill',
                entityId: $bill->id,
                before: null,
                after: $bill->toArray(),
            );

            return $bill;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): SupplierBill
    {
        return DB::transaction(function () use ($id, $data, $actorId): SupplierBill {
            /** @var SupplierBill $bill */
            $bill = SupplierBill::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft supplier bills can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $bill->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $billDate = $data['bill_date'] ?? $bill->bill_date;
            $period = $this->resolveFinancialPeriodForDate($billDate);

            $purchaseOrder = $bill->purchase_order_id ? PurchaseOrder::query()->where('id', $bill->purchase_order_id)->lockForUpdate()->first() : null;
            $goodsReceipt = $bill->goods_receipt_id ? GoodsReceipt::query()->where('id', $bill->goods_receipt_id)->lockForUpdate()->first() : null;

            $validatedLines = $this->validateAndCalculateLines($data['lines'] ?? [], $purchaseOrder, $goodsReceipt, $bill->id, $billDate);

            $subtotalMinor = array_sum(array_column($validatedLines, 'line_total_minor'));
            $taxAmountMinor = array_sum(array_column($validatedLines, 'tax_amount_minor'));
            $totalMinor = $subtotalMinor + $taxAmountMinor;

            $before = $bill->toArray();

            $bill->update([
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'bill_date' => $billDate,
                'due_date' => $data['due_date'] ?? $bill->due_date,
                'supplier_reference' => $data['supplier_reference'] ?? $bill->supplier_reference,
                'reference' => $data['reference'] ?? $bill->reference,
                'description' => $data['description'] ?? $bill->description,
                'subtotal_minor' => $subtotalMinor,
                'tax_amount_minor' => $taxAmountMinor,
                'total_minor' => $totalMinor,
                'updated_by' => $actorId,
                'lock_version' => $bill->lock_version + 1,
            ]);

            $bill->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $bill->lines()->create([
                    'line_no' => $index + 1,
                    'purchase_order_line_id' => $line['purchase_order_line_id'],
                    'goods_receipt_line_id' => $line['goods_receipt_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_cost_minor' => $line['unit_cost_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'tax_code_id' => $line['tax_code_id'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount_minor' => $line['tax_amount_minor'],
                    'gross_amount_minor' => $line['gross_amount_minor'],
                ]);
            }

            $bill->load(['supplier', 'purchaseOrder', 'goodsReceipt', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_bill.update',
                entityType: 'supplier_bill',
                entityId: $bill->id,
                before: $before,
                after: $bill->toArray(),
            );

            return $bill;
        });
    }

    public function submit(string $id, ?int $actorId = null): SupplierBill
    {
        return DB::transaction(function () use ($id, $actorId): SupplierBill {
            /** @var SupplierBill $bill */
            $bill = SupplierBill::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft supplier bills can be submitted.')]]);
            }

            $before = $bill->toArray();

            $bill->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $bill->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_bill.submit',
                entityType: 'supplier_bill',
                entityId: $bill->id,
                before: $before,
                after: $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function approve(string $id, ?int $actorId = null): SupplierBill
    {
        return DB::transaction(function () use ($id, $actorId): SupplierBill {
            /** @var SupplierBill $bill */
            $bill = SupplierBill::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Only submitted supplier bills can be approved.')]]);
            }

            $before = $bill->toArray();

            $bill->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $bill->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_bill.approve',
                entityType: 'supplier_bill',
                entityId: $bill->id,
                before: $before,
                after: $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function post(string $id, ?int $actorId = null): SupplierBill
    {
        return DB::transaction(function () use ($id, $actorId): SupplierBill {
            /** @var SupplierBill $bill */
            $bill = SupplierBill::query()->with(['lines.product', 'supplier'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($bill->status === 'posted') {
                return $bill->load(['supplier', 'lines.product', 'lines.unitOfMeasure', 'journalEntry', 'payableEntry']);
            }

            if ($bill->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved supplier bills can be posted.')]]);
            }

            if ($bill->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Cannot post supplier bill without line items.')]]);
            }

            // Verify stock product source rules on post
            foreach ($bill->lines as $line) {
                if ($line->product && $line->product->type === 'stock') {
                    if (! $line->goods_receipt_line_id) {
                        throw ValidationException::withMessages(['lines' => [__('Stock product lines on supplier bills must be sourced from a Goods Receipt.')]]);
                    }
                }
            }

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $bill->financial_period_id, (string) $bill->bill_date);
            $this->taxPeriodGuard->ensureDateNotFiled((string) $bill->bill_date);
            if ($period->fiscal_year_id !== $bill->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => [__('Financial period does not belong to the bill fiscal year.')]]);
            }
            if ($bill->bill_date < $period->start_date || $bill->bill_date > $period->end_date) {
                throw ValidationException::withMessages(['bill_date' => [__('Bill date must fall within the financial period.')]]);
            }

            if ($bill->fx_rate_e6 !== 1000000) {
                throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
            }

            // Fetch GL Accounts
            $apAccount = $this->mappingService->getAccount('ap_control');
            $expenseAccount = null;
            $grniAccount = null;

            $stockTotalMinor = 0;
            $expenseTotalMinor = 0;

            foreach ($bill->lines as $line) {
                if ($line->product && $line->product->type === 'stock') {
                    $stockTotalMinor += $line->line_total_minor;
                } else {
                    $expenseTotalMinor += $line->line_total_minor;
                }
            }

            if ($expenseTotalMinor > 0) {
                $expenseAccount = $this->mappingService->getAccount('purchase_expense');
                if ($expenseAccount->currency !== $bill->currency) {
                    throw ValidationException::withMessages(['currency' => [__('Mapped Purchase Expense account currency must match bill currency.')]]);
                }
            }

            if ($stockTotalMinor > 0) {
                $grniAccount = $this->mappingService->getAccount('grni_clearing');
                if ($grniAccount->currency !== $bill->currency) {
                    throw ValidationException::withMessages(['currency' => [__('Mapped GRNI Clearing account currency must match bill currency.')]]);
                }
            }

            if ($apAccount->currency !== $bill->currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped AP Control account currency must match bill currency.')]]);
            }

            // Allocate bill number sequence if missing
            $number = $bill->number;
            if (! $number) {
                $number = $this->numberAllocator->nextNumber('supplier.bill', 'BILL', $bill->bill_date);
            }

            $subtotalMinor = (int) ($bill->subtotal_minor ?: $bill->lines->sum('line_total_minor'));
            $taxAmountMinor = (int) ($bill->tax_amount_minor ?: $bill->lines->sum('tax_amount_minor'));
            $billTotalMinor = $subtotalMinor + $taxAmountMinor;

            $inputTaxAccount = $taxAmountMinor > 0 ? $this->mappingService->getAccount('input_tax_receivable') : null;

            if ($inputTaxAccount && $inputTaxAccount->currency !== $bill->currency) {
                throw ValidationException::withMessages(['currency' => [__('Mapped Input Tax Receivable account currency must match bill currency.')]]);
            }

            $before = $bill->toArray();

            // Create approved Journal Entry
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $bill->bill_date,
                'financial_period_id' => $bill->financial_period_id,
                'source_type' => 'supplier_bill',
                'source_id' => $bill->id,
                'description' => "Supplier Bill {$number} - {$bill->supplier->name}",
                'currency' => $bill->currency,
                'fx_rate_e6' => $bill->fx_rate_e6,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $lineNo = 1;

            if ($stockTotalMinor > 0 && $grniAccount) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $grniAccount->id,
                    'memo' => "GRNI Clearing - Bill {$number}",
                    'debit_minor' => $stockTotalMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $stockTotalMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $bill->currency,
                    'fx_rate_e6' => $bill->fx_rate_e6,
                ]);
            }

            if ($expenseTotalMinor > 0 && $expenseAccount) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $expenseAccount->id,
                    'memo' => "Purchase Expense - Bill {$number}",
                    'debit_minor' => $expenseTotalMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $expenseTotalMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $bill->currency,
                    'fx_rate_e6' => $bill->fx_rate_e6,
                ]);
            }

            if ($taxAmountMinor > 0 && $inputTaxAccount) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $inputTaxAccount->id,
                    'memo' => "Input Tax Receivable - Bill {$number}",
                    'debit_minor' => $taxAmountMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $taxAmountMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $bill->currency,
                    'fx_rate_e6' => $bill->fx_rate_e6,
                ]);
            }

            // Cr AP Control
            $apLine = $journalEntry->lines()->create([
                'line_no' => $lineNo++,
                'account_id' => $apAccount->id,
                'memo' => "AP Control - Bill {$number}",
                'debit_minor' => 0,
                'credit_minor' => $billTotalMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $billTotalMinor,
                'currency' => $bill->currency,
                'fx_rate_e6' => $bill->fx_rate_e6,
            ]);

            // Post journal entry via PostingEngine with system posting to control accounts
            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            // Create PayableEntry
            /** @var PayableEntry $payableEntry */
            $payableEntry = PayableEntry::query()->create([
                'supplier_id' => $bill->supplier_id,
                'source_type' => 'supplier_bill',
                'source_id' => $bill->id,
                'journal_entry_id' => $postedJournal->id,
                'journal_line_id' => $apLine->id,
                'financial_period_id' => $bill->financial_period_id,
                'entry_date' => $bill->bill_date,
                'due_date' => $bill->due_date ?? $bill->bill_date,
                'description' => "Supplier Bill {$number}",
                'currency' => $bill->currency,
                'debit_minor' => 0,
                'credit_minor' => $billTotalMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $billTotalMinor,
                'fx_rate_e6' => $bill->fx_rate_e6,
                'created_by' => $actorId,
            ]);

            $bill->number = $number;
            $bill->status = 'posted';
            $bill->journal_entry_id = $postedJournal->id;
            $bill->payable_entry_id = $payableEntry->id;
            $bill->posted_by = $actorId;
            $bill->posted_at = Carbon::now();
            $bill->updated_by = $actorId;
            $bill->lock_version = $bill->lock_version + 1;
            $bill->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_bill.post',
                entityType: 'supplier_bill',
                entityId: $bill->id,
                before: $before,
                after: $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure', 'journalEntry', 'payableEntry'])->toArray(),
            );

            return $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure', 'journalEntry', 'payableEntry']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): SupplierBill
    {
        return DB::transaction(function () use ($id, $actorId): SupplierBill {
            /** @var SupplierBill $bill */
            $bill = SupplierBill::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($bill->status === 'posted') {
                throw ValidationException::withMessages(['status' => [__('Posted supplier bills cannot be cancelled in this slice.')]]);
            }

            if ($bill->status === 'cancelled') {
                return $bill->load(['supplier', 'lines.product', 'lines.unitOfMeasure']);
            }

            $before = $bill->toArray();

            $bill->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $bill->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'supplier_bill.cancel',
                entityType: 'supplier_bill',
                entityId: $bill->id,
                before: $before,
                after: $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $bill->fresh(['supplier', 'lines.product', 'lines.unitOfMeasure']);
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
            throw ValidationException::withMessages(['bill_date' => [__('No open financial period covers date :date.', ['date' => $date])]]);
        }

        return $period;
    }

    private function validateAndCalculateLines(array $lines, ?PurchaseOrder $purchaseOrder, ?GoodsReceipt $goodsReceipt, ?string $currentBillId = null, string $billDate = ''): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => [__('At least one line item is required.')]]);
        }

        if ($purchaseOrder && $goodsReceipt) {
            throw ValidationException::withMessages([
                'source' => [__('Supplier bill can reference either a Purchase Order or a Goods Receipt, not both.')],
            ]);
        }

        $poLineIds = [];
        $grLineIds = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $polId = $line['purchase_order_line_id'] ?? null;
            $grlId = $line['goods_receipt_line_id'] ?? null;

            if ($polId && $grlId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.source" => [__('Line :line cannot reference both a Purchase Order line and a Goods Receipt line.', ['line' => $lineIndex])],
                ]);
            }

            if ($purchaseOrder) {
                if (! $polId) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.purchase_order_line_id" => [__('Line :line must reference a Purchase Order line for this Purchase Order bill.', ['line' => $lineIndex])],
                    ]);
                }
                if ($grlId) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.goods_receipt_line_id" => [__('Line :line cannot reference a Goods Receipt line for a Purchase Order bill.', ['line' => $lineIndex])],
                    ]);
                }
            }

            if ($goodsReceipt) {
                if (! $grlId) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.goods_receipt_line_id" => [__('Line :line must reference a Goods Receipt line for this Goods Receipt bill.', ['line' => $lineIndex])],
                    ]);
                }
                if ($polId) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.purchase_order_line_id" => [__('Line :line cannot reference a Purchase Order line for a Goods Receipt bill.', ['line' => $lineIndex])],
                    ]);
                }
            }

            if (! $purchaseOrder && ! $goodsReceipt) {
                if ($polId || $grlId) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.source" => [__('Line :line cannot reference a source line without a matching bill source header.', ['line' => $lineIndex])],
                    ]);
                }
            }

            if ($polId) {
                $poLineIds[] = $polId;
            }
            if ($grlId) {
                $grLineIds[] = $grlId;
            }
        }

        // Lock referenced source lines
        $poLines = [];
        if (! empty($poLineIds)) {
            $poLines = PurchaseOrderLine::query()
                ->whereIn('id', array_unique($poLineIds))
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
        }

        $grLines = [];
        if (! empty($grLineIds)) {
            $grLines = GoodsReceiptLine::query()
                ->with('purchaseOrderLine')
                ->whereIn('id', array_unique($grLineIds))
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
        }

        $validatedLines = [];
        $requestedPoQuantitiesE6 = [];
        $requestedGrQuantitiesE6 = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $productId = $line['product_id'] ?? null;
            if (! $productId) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Line :line product is required.', ['line' => $lineIndex])]]);
            }

            /** @var Product|null $product */
            $product = Product::query()->where('id', $productId)->first();
            if (! $product || $product->status !== 'active' || ! $product->is_purchase_enabled) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Line :line product must be active and purchase-enabled.', ['line' => $lineIndex])]]);
            }

            // STOCK PRODUCT BOUNDARY CHECK
            if ($product->type === 'stock') {
                $grlIdCheck = $line['goods_receipt_line_id'] ?? null;
                if (! $grlIdCheck || ! $goodsReceipt) {
                    throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Line :line stock product must be sourced from a Goods Receipt.', ['line' => $lineIndex])]]);
                }
            }

            $uomId = $line['unit_of_measure_id'] ?? $product->unit_of_measure_id;
            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => [__('Line :line quantity must be greater than zero.', ['line' => $lineIndex])]]);
            }

            $unitCostMinor = (int) ($line['unit_cost_minor'] ?? 0);
            if ($unitCostMinor < 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_cost" => [__('Line :line unit cost cannot be negative.', ['line' => $lineIndex])]]);
            }

            $polId = $line['purchase_order_line_id'] ?? null;
            if ($polId && $purchaseOrder) {
                /** @var PurchaseOrderLine|null $poLine */
                $poLine = $poLines->get($polId);
                if (! $poLine) {
                    throw ValidationException::withMessages(["lines.{$index}.purchase_order_line_id" => [__('Line :line does not belong to the selected Purchase Order.', ['line' => $lineIndex])]]);
                }

                if ($poLine->product_id !== $product->id) {
                    throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Line :line product does not match Purchase Order line product.', ['line' => $lineIndex])]]);
                }

                if ($poLine->unit_of_measure_id !== $uomId) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Line :line UOM does not match Purchase Order line UOM.', ['line' => $lineIndex])]]);
                }

                if ((int) $poLine->unit_price_minor !== $unitCostMinor) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_cost_minor" => [__('Line :line unit cost must match the selected Purchase Order line.', ['line' => $lineIndex])]]);
                }

                // Cumulative over-billing check for Purchase Order line
                $alreadyBilledQuery = SupplierBillLine::query()
                    ->where('purchase_order_line_id', $polId)
                    ->whereHas('supplierBill', fn ($q) => $q->where('status', '!=', 'cancelled'));

                if ($currentBillId) {
                    $alreadyBilledQuery->where('supplier_bill_id', '!=', $currentBillId);
                }

                $alreadyBilledE6 = (int) $alreadyBilledQuery->sum('quantity_e6');
                $requestedPoQuantitiesE6[$polId] = ($requestedPoQuantitiesE6[$polId] ?? 0) + $quantityE6;
                if ($alreadyBilledE6 + $requestedPoQuantitiesE6[$polId] > $poLine->quantity_e6) {
                    $maxAllowedE6 = $poLine->quantity_e6 - $alreadyBilledE6;
                    $whole = intdiv($maxAllowedE6, 1000000);
                    $frac = sprintf('%06d', abs($maxAllowedE6 % 1000000));
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => [__('Line :line quantity exceeds remaining unbilled Purchase Order line quantity (:maximum).', [
                            'line' => $lineIndex,
                            'maximum' => "{$whole}.{$frac}",
                        ])],
                    ]);
                }

                $unitCostMinor = $poLine->unit_price_minor;
            }

            $grlId = $line['goods_receipt_line_id'] ?? null;
            if ($grlId && $goodsReceipt) {
                /** @var GoodsReceiptLine|null $grLine */
                $grLine = $grLines->get($grlId);
                if (! $grLine) {
                    throw ValidationException::withMessages(["lines.{$index}.goods_receipt_line_id" => [__('Line :line does not belong to the selected Goods Receipt.', ['line' => $lineIndex])]]);
                }

                if ($grLine->product_id !== $product->id) {
                    throw ValidationException::withMessages(["lines.{$index}.product_id" => [__('Line :line product does not match Goods Receipt line product.', ['line' => $lineIndex])]]);
                }

                if ($grLine->unit_of_measure_id !== $uomId) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => [__('Line :line UOM does not match Goods Receipt line UOM.', ['line' => $lineIndex])]]);
                }

                if (! $grLine->purchaseOrderLine) {
                    throw ValidationException::withMessages(["lines.{$index}.goods_receipt_line_id" => [__('Goods Receipt line :line is not linked to a Purchase Order line.', ['line' => $lineIndex])]]);
                }

                if ((int) $grLine->purchaseOrderLine->unit_price_minor !== $unitCostMinor) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_cost_minor" => [__('Line :line unit cost must match the Goods Receipt source Purchase Order line.', ['line' => $lineIndex])]]);
                }

                // Cumulative over-billing check for Goods Receipt line
                $alreadyBilledQuery = SupplierBillLine::query()
                    ->where('goods_receipt_line_id', $grlId)
                    ->whereHas('supplierBill', fn ($q) => $q->where('status', '!=', 'cancelled'));

                if ($currentBillId) {
                    $alreadyBilledQuery->where('supplier_bill_id', '!=', $currentBillId);
                }

                $alreadyBilledE6 = (int) $alreadyBilledQuery->sum('quantity_e6');
                $requestedGrQuantitiesE6[$grlId] = ($requestedGrQuantitiesE6[$grlId] ?? 0) + $quantityE6;
                if ($alreadyBilledE6 + $requestedGrQuantitiesE6[$grlId] > $grLine->quantity_e6) {
                    $maxAllowedE6 = $grLine->quantity_e6 - $alreadyBilledE6;
                    $whole = intdiv($maxAllowedE6, 1000000);
                    $frac = sprintf('%06d', abs($maxAllowedE6 % 1000000));
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => [__('Line :line quantity exceeds remaining unbilled Goods Receipt line quantity (:maximum).', [
                            'line' => $lineIndex,
                            'maximum' => "{$whole}.{$frac}",
                        ])],
                    ]);
                }

                // Unit cost derived from linked PO line if available, else from input
                if ($grLine->purchaseOrderLine) {
                    $sourceUnitCost = $grLine->purchaseOrderLine->unit_price_minor;
                    if ($product->type === 'stock' && $unitCostMinor !== $sourceUnitCost) {
                        throw ValidationException::withMessages(["lines.{$index}.unit_cost" => [__('Line :line stock product bill unit cost must match Goods Receipt source unit cost.', ['line' => $lineIndex])]]);
                    }
                    $unitCostMinor = $sourceUnitCost;
                }
            }

            $lineTotalMinor = $this->calculateLineTotalMinor($quantityE6, $unitCostMinor, $lineIndex);

            $taxCodeId = $line['tax_code_id'] ?? null;
            $taxRateBps = 0;
            $taxAmountMinor = 0;
            $grossAmountMinor = $lineTotalMinor;

            if ($taxCodeId) {
                $calcDate = $billDate ?: now()->format('Y-m-d');
                $taxResult = $this->taxCalcService->calculateTax($taxCodeId, $lineTotalMinor, $calcDate);
                $taxRateBps = $taxResult['rate_bps'];
                $taxAmountMinor = $taxResult['tax_minor'];
                $grossAmountMinor = $taxResult['gross_minor'];
            }

            $validatedLines[] = [
                'purchase_order_line_id' => $polId,
                'goods_receipt_line_id' => $grlId,
                'product_id' => $product->id,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? (is_array($product->name) ? ($product->name['en'] ?? '') : (string) $product->name),
                'quantity_e6' => $quantityE6,
                'unit_cost_minor' => $unitCostMinor,
                'line_total_minor' => $lineTotalMinor,
                'tax_code_id' => $taxCodeId,
                'tax_rate_bps' => $taxRateBps,
                'tax_amount_minor' => $taxAmountMinor,
                'gross_amount_minor' => $grossAmountMinor,
            ];
        }

        return $validatedLines;
    }

    private function calculateLineTotalMinor(int $quantityE6, int $unitCostMinor, int $lineIndex): int
    {
        if ($unitCostMinor > 0 && $quantityE6 > intdiv(PHP_INT_MAX, $unitCostMinor)) {
            throw ValidationException::withMessages([
                "lines.{$lineIndex}.line_total" => [__('Line :line amount exceeds maximum allowable integer limit.', ['line' => $lineIndex])],
            ]);
        }

        $product = $quantityE6 * $unitCostMinor;
        if ($product % 1000000 !== 0) {
            throw ValidationException::withMessages([
                "lines.{$lineIndex}.quantity" => [__('Line :line quantity and unit cost result in fractional minor units.', ['line' => $lineIndex])],
            ]);
        }

        return intdiv($product, 1000000);
    }
}
