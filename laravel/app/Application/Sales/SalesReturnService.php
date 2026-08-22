<?php

namespace App\Application\Sales;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PostingEngine;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Domain\Audit\AuditLogger;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    private const ALLOWED_DISPOSITIONS = ['restock_original_cost', 'restock_manual_value', 'scrap_no_restock'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly MovingWeightedAverageInventoryService $inventoryService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($data, $actorId): SalesReturn {
            $customerId = $data['customer_id'] ?? null;
            if (! $customerId) {
                throw ValidationException::withMessages(['customer_id' => ['Customer is required.']]);
            }

            /** @var Customer|null $customer */
            $customer = Customer::query()->where('id', $customerId)->first();
            if (! $customer || $customer->status !== 'active') {
                throw ValidationException::withMessages(['customer_id' => ['Customer must be active.']]);
            }

            $deliveryNoteId = $data['delivery_note_id'] ?? null;
            if (! $deliveryNoteId) {
                throw ValidationException::withMessages(['delivery_note_id' => ['Delivery Note is required.']]);
            }

            /** @var DeliveryNote|null $deliveryNote */
            $deliveryNote = DeliveryNote::query()->with('salesOrder')->where('id', $deliveryNoteId)->lockForUpdate()->first();
            if (! $deliveryNote || $deliveryNote->status !== 'confirmed') {
                throw ValidationException::withMessages(['delivery_note_id' => ['Sales returns can only reference confirmed Delivery Notes.']]);
            }
            if ($deliveryNote->salesOrder->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['customer_id' => ['Customer must match the Delivery Note customer.']]);
            }

            $currency = $deliveryNote->salesOrder->currency;

            $returnDate = $data['return_date'] ?? null;
            if (! $returnDate) {
                throw ValidationException::withMessages(['return_date' => ['Return date is required.']]);
            }

            $period = $this->resolveFinancialPeriodForDate($returnDate);

            $customerInvoiceId = $data['customer_invoice_id'] ?? null;
            if ($customerInvoiceId) {
                /** @var CustomerInvoice|null $customerInvoice */
                $customerInvoice = CustomerInvoice::query()->where('id', $customerInvoiceId)->first();
                if (! $customerInvoice || $customerInvoice->customer_id !== $customer->id) {
                    throw ValidationException::withMessages(['customer_invoice_id' => ['Customer Invoice must belong to this customer.']]);
                }
            }

            $validatedLines = $this->validateAndCalculateLines(
                $data['lines'] ?? [],
                $deliveryNote,
                $customerInvoiceId,
                null,
            );

            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()->create([
                'customer_id' => $customer->id,
                'delivery_note_id' => $deliveryNote->id,
                'customer_invoice_id' => $customerInvoiceId,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'return_date' => $returnDate,
                'status' => 'draft',
                'currency' => $currency,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($validatedLines as $index => $line) {
                $salesReturn->lines()->create([
                    'line_no' => $index + 1,
                    'delivery_note_line_id' => $line['delivery_note_line_id'],
                    'customer_invoice_line_id' => $line['customer_invoice_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'disposition' => $line['disposition'],
                    'original_issue_cost_minor' => $line['original_issue_cost_minor'],
                    'manual_restock_value_minor' => $line['manual_restock_value_minor'],
                    'stock_value_minor' => $line['stock_value_minor'],
                    'variance_minor' => $line['variance_minor'],
                ]);
            }

            $salesReturn->load(['customer', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_return.create',
                entityType: 'sales_return',
                entityId: $salesReturn->id,
                before: null,
                after: $salesReturn->toArray(),
            );

            return $salesReturn;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($id, $data, $actorId): SalesReturn {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($salesReturn->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft sales returns can be updated.']]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $salesReturn->lock_version) {
                throw ValidationException::withMessages(['lock_version' => ['The record has been modified by another user. Please refresh and try again.']]);
            }

            /** @var DeliveryNote|null $deliveryNote */
            $deliveryNote = DeliveryNote::query()->with('salesOrder')->where('id', $salesReturn->delivery_note_id)->lockForUpdate()->first();

            $returnDate = $data['return_date'] ?? $salesReturn->return_date->format('Y-m-d');
            $period = $this->resolveFinancialPeriodForDate($returnDate);

            $customerInvoiceId = $data['customer_invoice_id'] ?? $salesReturn->customer_invoice_id;
            if ($customerInvoiceId) {
                /** @var CustomerInvoice|null $customerInvoice */
                $customerInvoice = CustomerInvoice::query()->where('id', $customerInvoiceId)->first();
                if (! $customerInvoice || $customerInvoice->customer_id !== $salesReturn->customer_id) {
                    throw ValidationException::withMessages(['customer_invoice_id' => ['Customer Invoice must belong to this customer.']]);
                }
            }

            $validatedLines = $this->validateAndCalculateLines(
                $data['lines'] ?? [],
                $deliveryNote,
                $customerInvoiceId,
                $salesReturn->id,
            );

            $before = $salesReturn->toArray();

            $salesReturn->update([
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'return_date' => $returnDate,
                'customer_invoice_id' => $customerInvoiceId,
                'reason' => $data['reason'] ?? $salesReturn->reason,
                'notes' => $data['notes'] ?? $salesReturn->notes,
                'updated_by' => $actorId,
                'lock_version' => $salesReturn->lock_version + 1,
            ]);

            $salesReturn->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $salesReturn->lines()->create([
                    'line_no' => $index + 1,
                    'delivery_note_line_id' => $line['delivery_note_line_id'],
                    'customer_invoice_line_id' => $line['customer_invoice_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'disposition' => $line['disposition'],
                    'original_issue_cost_minor' => $line['original_issue_cost_minor'],
                    'manual_restock_value_minor' => $line['manual_restock_value_minor'],
                    'stock_value_minor' => $line['stock_value_minor'],
                    'variance_minor' => $line['variance_minor'],
                ]);
            }

            $salesReturn->load(['customer', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_return.update',
                entityType: 'sales_return',
                entityId: $salesReturn->id,
                before: $before,
                after: $salesReturn->toArray(),
            );

            return $salesReturn;
        });
    }

    public function submit(string $id, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($id, $actorId): SalesReturn {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($salesReturn->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft sales returns can be submitted.']]);
            }

            if ($salesReturn->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Sales return must have at least one line item before submitting.']]);
            }

            $before = $salesReturn->toArray();

            $salesReturn->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $salesReturn->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_return.submit',
                entityType: 'sales_return',
                entityId: $salesReturn->id,
                before: $before,
                after: $salesReturn->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesReturn->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function approve(string $id, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($id, $actorId): SalesReturn {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($salesReturn->status === 'approved') {
                return $salesReturn->load(['customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            if (! in_array($salesReturn->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or submitted sales returns can be approved.']]);
            }

            $before = $salesReturn->toArray();

            $salesReturn->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $salesReturn->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_return.approve',
                entityType: 'sales_return',
                entityId: $salesReturn->id,
                before: $before,
                after: $salesReturn->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesReturn->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($id, $actorId): SalesReturn {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($salesReturn->status === 'posted') {
                throw ValidationException::withMessages(['status' => ['Posted sales returns cannot be cancelled.']]);
            }

            if ($salesReturn->status === 'cancelled') {
                return $salesReturn->load(['customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            $before = $salesReturn->toArray();

            $salesReturn->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $salesReturn->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_return.cancel',
                entityType: 'sales_return',
                entityId: $salesReturn->id,
                before: $before,
                after: $salesReturn->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesReturn->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function post(string $id, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($id, $actorId): SalesReturn {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()->with(['lines.product'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($salesReturn->status === 'posted') {
                return $salesReturn->load(['customer', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure']);
            }

            if ($salesReturn->status !== 'approved') {
                throw ValidationException::withMessages(['status' => ['Only approved sales returns can be posted.']]);
            }

            if ($salesReturn->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['Sales return must have at least one line item before posting.']]);
            }

            $period = FinancialPeriod::query()->where('id', $salesReturn->financial_period_id)->lockForUpdate()->firstOrFail();
            if (! $period->isOpen()) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period is closed.']]);
            }
            if ($period->fiscal_year_id !== $salesReturn->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period does not belong to the sales return fiscal year.']]);
            }
            $returnDate = $salesReturn->return_date->format('Y-m-d');
            if ($returnDate < $period->start_date || $returnDate > $period->end_date) {
                throw ValidationException::withMessages(['return_date' => ['Return date must fall within the financial period.']]);
            }

            $before = $salesReturn->toArray();

            foreach ($salesReturn->lines as $line) {
                $disposition = $line->disposition;

                if ($disposition === 'restock_original_cost') {
                    $this->inventoryService->recordReturn(
                        sourceType: 'sales_return',
                        sourceId: $salesReturn->id,
                        sourceLineId: $line->id,
                        movementDate: $returnDate,
                        productId: $line->product_id,
                        unitOfMeasureId: $line->unit_of_measure_id,
                        currency: $salesReturn->currency,
                        quantityE6: $line->quantity_e6,
                        unitCostMinor: intdiv((int) $line->original_issue_cost_minor * 1000000, $line->quantity_e6),
                        fiscalYearId: $salesReturn->fiscal_year_id,
                        financialPeriodId: $salesReturn->financial_period_id,
                        actorId: $actorId,
                    );
                } elseif ($disposition === 'restock_manual_value') {
                    $this->inventoryService->recordReturn(
                        sourceType: 'sales_return',
                        sourceId: $salesReturn->id,
                        sourceLineId: $line->id,
                        movementDate: $returnDate,
                        productId: $line->product_id,
                        unitOfMeasureId: $line->unit_of_measure_id,
                        currency: $salesReturn->currency,
                        quantityE6: $line->quantity_e6,
                        unitCostMinor: intdiv((int) $line->stock_value_minor * 1000000, $line->quantity_e6),
                        fiscalYearId: $salesReturn->fiscal_year_id,
                        financialPeriodId: $salesReturn->financial_period_id,
                        actorId: $actorId,
                    );

                    if ((int) $line->variance_minor !== 0) {
                        $this->postVarianceJournal($salesReturn, $line, $returnDate, $actorId);
                    }
                } elseif ($disposition === 'scrap_no_restock') {
                    $this->inventoryService->recordScrap(
                        sourceType: 'sales_return',
                        sourceId: $salesReturn->id,
                        sourceLineId: $line->id,
                        movementDate: $returnDate,
                        productId: $line->product_id,
                        unitOfMeasureId: $line->unit_of_measure_id,
                        currency: $salesReturn->currency,
                        quantityE6: $line->quantity_e6,
                        fiscalYearId: $salesReturn->fiscal_year_id,
                        financialPeriodId: $salesReturn->financial_period_id,
                        actorId: $actorId,
                    );
                }
            }

            $number = $salesReturn->number;
            if (! $number) {
                $orderYear = Carbon::parse($returnDate)->format('Y');
                $seq = $this->numberAllocator->nextValue('sales.return');
                $number = 'SR-'.$orderYear.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $salesReturn->number = $number;
            $salesReturn->status = 'posted';
            $salesReturn->posted_by = $actorId;
            $salesReturn->posted_at = Carbon::now();
            $salesReturn->updated_by = $actorId;
            $salesReturn->lock_version = $salesReturn->lock_version + 1;
            $salesReturn->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'sales_return.post',
                entityType: 'sales_return',
                entityId: $salesReturn->id,
                before: $before,
                after: $salesReturn->fresh(['customer', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $salesReturn->fresh(['customer', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    private function postVarianceJournal(SalesReturn $salesReturn, SalesReturnLine $line, string $returnDate, ?int $actorId): void
    {
        $varianceAccount = $this->mappingService->getAccount('inventory_return_variance');
        $cogsAccount = $this->mappingService->getAccount('cogs');

        $varianceMinor = abs((int) $line->variance_minor);

        /** @var JournalEntry $journalEntry */
        $journalEntry = JournalEntry::query()->create([
            'entry_date' => $returnDate,
            'financial_period_id' => $salesReturn->financial_period_id,
            'source_type' => 'sales_return_variance',
            'source_id' => $salesReturn->id,
            'description' => "Inventory Return Variance - Sales Return {$salesReturn->number}",
            'currency' => $salesReturn->currency,
            'fx_rate_e6' => 1000000,
            'status' => 'approved',
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'approved_by' => $actorId,
            'approved_at' => Carbon::now(),
            'lock_version' => 1,
        ]);

        if ((int) $line->variance_minor > 0) {
            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $varianceAccount->id,
                'memo' => 'Inventory Return Variance',
                'debit_minor' => $varianceMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $varianceMinor,
                'credit_txn_minor' => 0,
                'currency' => $salesReturn->currency,
                'fx_rate_e6' => 1000000,
            ]);
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $cogsAccount->id,
                'memo' => 'Cost of Goods Sold Adjustment',
                'debit_minor' => 0,
                'credit_minor' => $varianceMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $varianceMinor,
                'currency' => $salesReturn->currency,
                'fx_rate_e6' => 1000000,
            ]);
        } else {
            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $cogsAccount->id,
                'memo' => 'Cost of Goods Sold Adjustment',
                'debit_minor' => $varianceMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $varianceMinor,
                'credit_txn_minor' => 0,
                'currency' => $salesReturn->currency,
                'fx_rate_e6' => 1000000,
            ]);
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $varianceAccount->id,
                'memo' => 'Inventory Return Variance',
                'debit_minor' => 0,
                'credit_minor' => $varianceMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $varianceMinor,
                'currency' => $salesReturn->currency,
                'fx_rate_e6' => 1000000,
            ]);
        }

        $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);
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
            throw ValidationException::withMessages(['return_date' => ["No open financial period covers date {$date}."]]);
        }

        return $period;
    }

    private function validateAndCalculateLines(array $lines, DeliveryNote $deliveryNote, ?string $customerInvoiceId, ?string $currentReturnId): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => ['At least one line item is required.']]);
        }

        /** @var CustomerInvoice|null $customerInvoice */
        $customerInvoice = null;
        if ($customerInvoiceId) {
            /** @var CustomerInvoice|null $customerInvoice */
            $customerInvoice = CustomerInvoice::query()->where('id', $customerInvoiceId)->first();
            if (! $customerInvoice || $customerInvoice->customer_id !== $deliveryNote->salesOrder->customer_id) {
                throw ValidationException::withMessages(['customer_invoice_id' => ['Customer Invoice must belong to the Delivery Note customer.']]);
            }
        }

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;

            $dnlId = $line['delivery_note_line_id'] ?? null;
            if (! $dnlId) {
                throw ValidationException::withMessages(["lines.{$index}.delivery_note_line_id" => ["Line {$lineIndex} must reference a Delivery Note line."]]);
            }

            /** @var DeliveryNoteLine|null $dnLine */
            $dnLine = DeliveryNoteLine::query()->where('id', $dnlId)->where('delivery_note_id', $deliveryNote->id)->lockForUpdate()->first();
            if (! $dnLine) {
                throw ValidationException::withMessages(["lines.{$index}.delivery_note_line_id" => ["Line {$lineIndex} does not belong to the selected Delivery Note."]]);
            }

            $productId = $line['product_id'] ?? null;
            if (! $productId) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product on line {$lineIndex} is required."]]);
            }

            /** @var Product|null $product */
            $product = Product::query()->where('id', $productId)->first();
            if (! $product) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product on line {$lineIndex} does not exist."]]);
            }
            if ($product->status !== 'active') {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product [{$product->code}] is inactive."]]);
            }

            if ($dnLine->product_id !== $product->id) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product on line {$lineIndex} must match the selected Delivery Note line."]]);
            }

            $uomId = $dnLine->unit_of_measure_id;

            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => ["Quantity on line {$lineIndex} must be greater than zero."]]);
            }

            $disposition = $line['disposition'] ?? null;
            if (! $disposition || ! in_array($disposition, self::ALLOWED_DISPOSITIONS, true)) {
                throw ValidationException::withMessages(["lines.{$index}.disposition" => ["Disposition on line {$lineIndex} must be one of: ".implode(', ', self::ALLOWED_DISPOSITIONS).'.']]);
            }

            $manualRestockValueMinor = null;
            if ($disposition === 'restock_manual_value') {
                if (! isset($line['manual_restock_value_minor']) || (int) $line['manual_restock_value_minor'] < 0) {
                    throw ValidationException::withMessages(["lines.{$index}.manual_restock_value_minor" => ["Manual restock value on line {$lineIndex} is required and must be >= 0."]]);
                }
                $manualRestockValueMinor = (int) $line['manual_restock_value_minor'];
            }

            $proportionalOriginalCostMinor = $this->inventoryService->calculateIssueCostForReturn(
                sourceType: 'sales_return',
                sourceLineId: $dnLine->id,
                returnQuantityE6: $quantityE6,
            );

            $stockValueMinor = match ($disposition) {
                'restock_original_cost' => $proportionalOriginalCostMinor,
                'restock_manual_value' => $manualRestockValueMinor,
                default => 0,
            };

            $varianceMinor = $disposition === 'restock_manual_value'
                ? $stockValueMinor - $proportionalOriginalCostMinor
                : 0;

            $alreadyReturnedQuery = SalesReturnLine::query()
                ->where('delivery_note_line_id', $dnLine->id)
                ->whereHas('salesReturn', fn ($q) => $q->where('status', '!=', 'cancelled'));

            if ($currentReturnId) {
                $alreadyReturnedQuery->where('sales_return_id', '!=', $currentReturnId);
            }

            $alreadyReturnedE6 = (int) $alreadyReturnedQuery->sum('quantity_e6');
            if ($alreadyReturnedE6 + $quantityE6 > (int) $dnLine->quantity_e6) {
                $maxAllowedE6 = (int) $dnLine->quantity_e6 - $alreadyReturnedE6;
                $whole = intdiv($maxAllowedE6, 1000000);
                $fraction = str_pad((string) abs($maxAllowedE6 % 1000000), 6, '0', STR_PAD_LEFT);
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity_e6" => ["Returned quantity on line {$lineIndex} exceeds remaining Delivery Note line quantity. Maximum remaining allowed is {$whole}.{$fraction}."],
                ]);
            }

            $customerInvoiceLineId = $line['customer_invoice_line_id'] ?? null;
            if ($customerInvoiceLineId) {
                /** @var CustomerInvoiceLine|null $cil */
                $cil = CustomerInvoiceLine::query()->where('id', $customerInvoiceLineId)->first();
                if (! $cil) {
                    throw ValidationException::withMessages(["lines.{$index}.customer_invoice_line_id" => ["Customer Invoice line on line {$lineIndex} does not exist."]]);
                }
                if ($customerInvoice && $cil->customer_invoice_id !== $customerInvoice->id) {
                    throw ValidationException::withMessages(["lines.{$index}.customer_invoice_line_id" => ["Customer Invoice line on line {$lineIndex} does not belong to the selected Customer Invoice."]]);
                }
                if ($cil->product_id !== $product->id) {
                    throw ValidationException::withMessages(["lines.{$index}.customer_invoice_line_id" => ["Product on line {$lineIndex} must match the selected Customer Invoice line."]]);
                }
                if ($cil->unit_of_measure_id !== $uomId) {
                    throw ValidationException::withMessages(["lines.{$index}.customer_invoice_line_id" => ["Unit of measure on line {$lineIndex} must match the selected Customer Invoice line."]]);
                }
            }

            $validatedLines[] = [
                'delivery_note_line_id' => $dnLine->id,
                'customer_invoice_line_id' => $customerInvoiceLineId,
                'product_id' => $product->id,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? $dnLine->description ?? (is_array($product->name) ? ($product->name['en'] ?? '') : (string) $product->name),
                'quantity_e6' => $quantityE6,
                'disposition' => $disposition,
                'original_issue_cost_minor' => $proportionalOriginalCostMinor,
                'manual_restock_value_minor' => $manualRestockValueMinor,
                'stock_value_minor' => $stockValueMinor,
                'variance_minor' => $varianceMinor,
            ];
        }

        return $validatedLines;
    }
}
