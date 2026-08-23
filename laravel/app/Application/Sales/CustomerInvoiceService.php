<?php

namespace App\Application\Sales;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxPeriodGuard;
use App\Domain\Audit\AuditLogger;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ReceivableEntry;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerInvoiceService
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

    public function create(array $data, ?int $actorId = null): CustomerInvoice
    {
        return DB::transaction(function () use ($data, $actorId): CustomerInvoice {
            $customerId = $data['customer_id'] ?? null;
            if (! $customerId) {
                throw ValidationException::withMessages(['customer_id' => ['Customer is required.']]);
            }

            /** @var Customer|null $customer */
            $customer = Customer::query()->where('id', $customerId)->first();
            if (! $customer || $customer->status !== 'active') {
                throw ValidationException::withMessages(['customer_id' => ['Customer must be active.']]);
            }

            $currency = $data['currency'] ?? 'USD';
            $fxRateE6 = (int) ($data['fx_rate_e6'] ?? 1000000);
            if ($fxRateE6 !== 1000000) {
                throw ValidationException::withMessages(['fx_rate_e6' => ['FX rate must be 1.000000 (1000000) in this slice.']]);
            }

            $invoiceDate = $data['invoice_date'] ?? null;
            if (! $invoiceDate) {
                throw ValidationException::withMessages(['invoice_date' => ['Invoice date is required.']]);
            }

            $dueDate = $data['due_date'] ?? null;

            // Resolve FiscalYear & FinancialPeriod for invoice_date
            $period = $this->resolveFinancialPeriodForDate($invoiceDate);

            if (! empty($data['sales_order_id']) && ! empty($data['delivery_note_id'])) {
                throw ValidationException::withMessages([
                    'source' => ['Customer invoice can reference either a Sales Order or a Delivery Note, not both.'],
                ]);
            }

            // Optional source models
            $salesOrder = null;
            if (! empty($data['sales_order_id'])) {
                /** @var SalesOrder|null $salesOrder */
                $salesOrder = SalesOrder::query()->where('id', $data['sales_order_id'])->lockForUpdate()->first();
                if (! $salesOrder || $salesOrder->status !== 'confirmed') {
                    throw ValidationException::withMessages(['sales_order_id' => ['Customer invoices can only reference confirmed Sales Orders.']]);
                }
                if ($salesOrder->customer_id !== $customer->id) {
                    throw ValidationException::withMessages(['customer_id' => ['Customer must match the Sales Order customer.']]);
                }
                if ($salesOrder->currency !== $currency) {
                    throw ValidationException::withMessages(['currency' => ['Currency must match the Sales Order currency.']]);
                }
            }

            $deliveryNote = null;
            if (! empty($data['delivery_note_id'])) {
                /** @var DeliveryNote|null $deliveryNote */
                $deliveryNote = DeliveryNote::query()->with('salesOrder')->where('id', $data['delivery_note_id'])->lockForUpdate()->first();
                if (! $deliveryNote || $deliveryNote->status !== 'confirmed') {
                    throw ValidationException::withMessages(['delivery_note_id' => ['Customer invoices can only reference confirmed Delivery Notes.']]);
                }
                if ($deliveryNote->salesOrder->customer_id !== $customer->id) {
                    throw ValidationException::withMessages(['customer_id' => ['Customer must match the Delivery Note customer.']]);
                }
                if ($deliveryNote->salesOrder->currency !== $currency) {
                    throw ValidationException::withMessages(['currency' => ['Currency must match the Delivery Note currency.']]);
                }
            }

            $validatedLines = $this->validateAndCalculateLines($data['lines'] ?? [], $salesOrder, $deliveryNote, null, $invoiceDate);

            $subtotalMinor = array_sum(array_column($validatedLines, 'line_total_minor'));
            $taxAmountMinor = array_sum(array_column($validatedLines, 'tax_amount_minor'));
            $totalMinor = $subtotalMinor + $taxAmountMinor;

            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->create([
                'customer_id' => $customer->id,
                'sales_order_id' => $salesOrder?->id,
                'delivery_note_id' => $deliveryNote?->id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
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
                $invoice->lines()->create([
                    'line_no' => $index + 1,
                    'sales_order_line_id' => $line['sales_order_line_id'],
                    'delivery_note_line_id' => $line['delivery_note_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'tax_code_id' => $line['tax_code_id'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount_minor' => $line['tax_amount_minor'],
                    'gross_amount_minor' => $line['gross_amount_minor'],
                ]);
            }

            $invoice->load(['customer', 'salesOrder', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure', 'lines.taxCode']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice.create',
                entityType: 'customer_invoice',
                entityId: $invoice->id,
                before: null,
                after: $invoice->toArray(),
            );

            return $invoice;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): CustomerInvoice
    {
        return DB::transaction(function () use ($id, $data, $actorId): CustomerInvoice {
            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft customer invoices can be updated.']]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $invoice->lock_version) {
                throw ValidationException::withMessages(['lock_version' => ['The record has been modified by another user. Please refresh and try again.']]);
            }

            $invoiceDate = $data['invoice_date'] ?? $invoice->invoice_date;
            $period = $this->resolveFinancialPeriodForDate($invoiceDate);

            $salesOrder = $invoice->sales_order_id ? SalesOrder::query()->where('id', $invoice->sales_order_id)->lockForUpdate()->first() : null;
            $deliveryNote = $invoice->delivery_note_id ? DeliveryNote::query()->where('id', $invoice->delivery_note_id)->lockForUpdate()->first() : null;

            $validatedLines = $this->validateAndCalculateLines($data['lines'] ?? [], $salesOrder, $deliveryNote, $invoice->id, (string) $invoiceDate);

            $subtotalMinor = array_sum(array_column($validatedLines, 'line_total_minor'));
            $taxAmountMinor = array_sum(array_column($validatedLines, 'tax_amount_minor'));
            $totalMinor = $subtotalMinor + $taxAmountMinor;

            $before = $invoice->toArray();

            $invoice->update([
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'reference' => $data['reference'] ?? $invoice->reference,
                'description' => $data['description'] ?? $invoice->description,
                'subtotal_minor' => $subtotalMinor,
                'tax_amount_minor' => $taxAmountMinor,
                'total_minor' => $totalMinor,
                'updated_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);

            $invoice->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $invoice->lines()->create([
                    'line_no' => $index + 1,
                    'sales_order_line_id' => $line['sales_order_line_id'],
                    'delivery_note_line_id' => $line['delivery_note_line_id'],
                    'product_id' => $line['product_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'tax_code_id' => $line['tax_code_id'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount_minor' => $line['tax_amount_minor'],
                    'gross_amount_minor' => $line['gross_amount_minor'],
                ]);
            }

            $invoice->load(['customer', 'salesOrder', 'deliveryNote', 'lines.product', 'lines.unitOfMeasure', 'lines.taxCode']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice.update',
                entityType: 'customer_invoice',
                entityId: $invoice->id,
                before: $before,
                after: $invoice->toArray(),
            );

            return $invoice;
        });
    }

    public function submit(string $id, ?int $actorId = null): CustomerInvoice
    {
        return DB::transaction(function () use ($id, $actorId): CustomerInvoice {
            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft customer invoices can be submitted.']]);
            }

            if ($invoice->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Customer invoice must have at least one line item before submitting.']]);
            }

            $before = $invoice->toArray();

            $invoice->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice.submit',
                entityType: 'customer_invoice',
                entityId: $invoice->id,
                before: $before,
                after: $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function approve(string $id, ?int $actorId = null): CustomerInvoice
    {
        return DB::transaction(function () use ($id, $actorId): CustomerInvoice {
            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'approved') {
                return $invoice->load(['customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            if (! in_array($invoice->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or submitted customer invoices can be approved.']]);
            }

            if ($invoice->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Customer invoice must have at least one line item before approving.']]);
            }

            $before = $invoice->toArray();

            $invoice->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice.approve',
                entityType: 'customer_invoice',
                entityId: $invoice->id,
                before: $before,
                after: $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
        });
    }

    public function post(string $id, ?int $actorId = null): CustomerInvoice
    {
        return DB::transaction(function () use ($id, $actorId): CustomerInvoice {
            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->with(['lines.product', 'customer'])->where('id', $id)->lockForUpdate()->firstOrFail();

            $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $invoice->financial_period_id, (string) $invoice->invoice_date);
            $this->taxPeriodGuard->ensureDateNotFiled((string) $invoice->invoice_date);

            if ($invoice->status === 'posted') {
                return $invoice->load(['customer', 'lines.product', 'lines.unitOfMeasure', 'journalEntry', 'receivableEntry']);
            }

            if ($invoice->status !== 'approved') {
                throw ValidationException::withMessages(['status' => ['Only approved customer invoices can be posted to AR/GL.']]);
            }

            if ($invoice->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['Customer invoice must have at least one line item before posting.']]);
            }

            // Verify stock product source rules on post
            foreach ($invoice->lines as $line) {
                if ($line->product && $line->product->type === 'stock') {
                    if (! $line->delivery_note_line_id) {
                        throw ValidationException::withMessages(['lines' => ['Stock product lines on customer invoices must be sourced from a Delivery Note.']]);
                    }
                }
            }

            // Verify period is open and date falls within range
            $period = FinancialPeriod::query()->where('id', $invoice->financial_period_id)->lockForUpdate()->firstOrFail();
            if (! $period->isOpen()) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period is closed.']]);
            }
            if ($period->fiscal_year_id !== $invoice->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period does not belong to the invoice fiscal year.']]);
            }
            if ($invoice->invoice_date < $period->start_date || $invoice->invoice_date > $period->end_date) {
                throw ValidationException::withMessages(['invoice_date' => ['Invoice date must fall within the financial period.']]);
            }

            // Resolve required accounting mappings
            $arAccount = $this->mappingService->getAccount('ar_control');
            $revenueAccount = $this->mappingService->getAccount('sales_revenue');

            if ($arAccount->currency !== $invoice->currency || $revenueAccount->currency !== $invoice->currency) {
                throw ValidationException::withMessages(['currency' => ["Mapped GL account currency (AR: {$arAccount->currency}, Rev: {$revenueAccount->currency}) must match invoice currency ({$invoice->currency})."]]);
            }

            // Allocate invoice number sequence if missing
            $number = $invoice->number;
            if (! $number) {
                $orderYear = Carbon::parse($invoice->invoice_date)->format('Y');
                $seq = $this->numberAllocator->nextValue('customer.invoice');
                $number = 'INV-'.$orderYear.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $subtotalMinor = (int) ($invoice->subtotal_minor ?: $invoice->lines->sum('line_total_minor'));
            $taxAmountMinor = (int) ($invoice->tax_amount_minor ?: $invoice->lines->sum('tax_amount_minor'));
            $invoiceTotalMinor = $subtotalMinor + $taxAmountMinor;

            $outputTaxAccount = $taxAmountMinor > 0 ? $this->mappingService->getAccount('output_tax_payable') : null;

            if ($outputTaxAccount && $outputTaxAccount->currency !== $invoice->currency) {
                throw ValidationException::withMessages(['currency' => ["Mapped tax account currency ({$outputTaxAccount->currency}) must match invoice currency ({$invoice->currency})."]]);
            }

            $before = $invoice->toArray();

            // Create approved Journal Entry
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $invoice->invoice_date,
                'financial_period_id' => $invoice->financial_period_id,
                'source_type' => 'customer_invoice',
                'source_id' => $invoice->id,
                'description' => "Customer Invoice {$number} - {$invoice->customer->name}",
                'currency' => $invoice->currency,
                'fx_rate_e6' => $invoice->fx_rate_e6,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            // Dr AR Control (Gross Total)
            $arLine = $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $arAccount->id,
                'memo' => "AR Control - Invoice {$number}",
                'debit_minor' => $invoiceTotalMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $invoiceTotalMinor,
                'credit_txn_minor' => 0,
                'currency' => $invoice->currency,
                'fx_rate_e6' => $invoice->fx_rate_e6,
            ]);

            // Cr Sales Revenue (Net Subtotal)
            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $revenueAccount->id,
                'memo' => "Sales Revenue - Invoice {$number}",
                'debit_minor' => 0,
                'credit_minor' => $subtotalMinor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $subtotalMinor,
                'currency' => $invoice->currency,
                'fx_rate_e6' => $invoice->fx_rate_e6,
            ]);

            // Cr Output Tax Payable (Tax Amount)
            if ($outputTaxAccount && $taxAmountMinor > 0) {
                $journalEntry->lines()->create([
                    'line_no' => 3,
                    'account_id' => $outputTaxAccount->id,
                    'memo' => "Output Tax Payable - Invoice {$number}",
                    'debit_minor' => 0,
                    'credit_minor' => $taxAmountMinor,
                    'debit_txn_minor' => 0,
                    'credit_txn_minor' => $taxAmountMinor,
                    'currency' => $invoice->currency,
                    'fx_rate_e6' => $invoice->fx_rate_e6,
                ]);
            }

            // Post journal entry via PostingEngine with system posting to control accounts
            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            // Create ReceivableEntry
            /** @var ReceivableEntry $receivableEntry */
            $receivableEntry = ReceivableEntry::query()->create([
                'customer_id' => $invoice->customer_id,
                'source_type' => 'customer_invoice',
                'source_id' => $invoice->id,
                'journal_entry_id' => $postedJournal->id,
                'journal_line_id' => $arLine->id,
                'financial_period_id' => $invoice->financial_period_id,
                'entry_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date ?? $invoice->invoice_date,
                'description' => "Customer Invoice {$number}",
                'currency' => $invoice->currency,
                'debit_minor' => $invoiceTotalMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $invoiceTotalMinor,
                'credit_txn_minor' => 0,
                'fx_rate_e6' => $invoice->fx_rate_e6,
                'created_by' => $actorId,
            ]);

            $invoice->number = $number;
            $invoice->status = 'posted';
            $invoice->journal_entry_id = $postedJournal->id;
            $invoice->receivable_entry_id = $receivableEntry->id;
            $invoice->posted_by = $actorId;
            $invoice->posted_at = Carbon::now();
            $invoice->updated_by = $actorId;
            $invoice->lock_version = $invoice->lock_version + 1;
            $invoice->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice.post',
                entityType: 'customer_invoice',
                entityId: $invoice->id,
                before: $before,
                after: $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure', 'journalEntry', 'receivableEntry'])->toArray(),
            );

            return $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure', 'journalEntry', 'receivableEntry']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): CustomerInvoice
    {
        return DB::transaction(function () use ($id, $actorId): CustomerInvoice {
            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'posted') {
                throw ValidationException::withMessages(['status' => ['Posted customer invoices cannot be cancelled in this slice.']]);
            }

            if ($invoice->status === 'cancelled') {
                return $invoice->load(['customer', 'lines.product', 'lines.unitOfMeasure']);
            }

            $before = $invoice->toArray();

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice.cancel',
                entityType: 'customer_invoice',
                entityId: $invoice->id,
                before: $before,
                after: $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure'])->toArray(),
            );

            return $invoice->fresh(['customer', 'lines.product', 'lines.unitOfMeasure']);
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
            throw ValidationException::withMessages(['invoice_date' => ["No open financial period covers date {$date}."]]);
        }

        return $period;
    }

    private function validateAndCalculateLines(array $lines, ?SalesOrder $salesOrder, ?DeliveryNote $deliveryNote, ?string $currentInvoiceId = null, string $invoiceDate = ''): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => ['At least one line item is required.']]);
        }

        if ($salesOrder && $deliveryNote) {
            throw ValidationException::withMessages([
                'source' => ['Customer invoice can reference either a Sales Order or a Delivery Note, not both.'],
            ]);
        }

        $salesOrderLineIds = [];
        $deliveryNoteLineIds = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $solId = $line['sales_order_line_id'] ?? null;
            $dnlId = $line['delivery_note_line_id'] ?? null;

            if ($solId && $dnlId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.source" => ["Line {$lineIndex} cannot reference both a Sales Order line and a Delivery Note line."],
                ]);
            }

            if ($solId && ! $salesOrder) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => ["Line {$lineIndex} references a Sales Order line but no Sales Order source was selected."],
                ]);
            }

            if ($dnlId && ! $deliveryNote) {
                throw ValidationException::withMessages([
                    "lines.{$index}.delivery_note_line_id" => ["Line {$lineIndex} references a Delivery Note line but no Delivery Note source was selected."],
                ]);
            }

            if ($salesOrder && ! $solId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => ["Line {$lineIndex} must reference a Sales Order line."],
                ]);
            }

            if ($deliveryNote && ! $dnlId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.delivery_note_line_id" => ["Line {$lineIndex} must reference a Delivery Note line."],
                ]);
            }

            if ($solId) {
                $salesOrderLineIds[] = $solId;
            }

            if ($dnlId) {
                $deliveryNoteLineIds[] = $dnlId;
            }
        }

        $productIds = array_column($lines, 'product_id');
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $salesOrderLines = collect();
        if ($salesOrderLineIds) {
            $salesOrderLines = SalesOrderLine::query()
                ->where('sales_order_id', $salesOrder?->id)
                ->whereIn('id', array_values(array_unique($salesOrderLineIds)))
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
        }

        $deliveryNoteLines = collect();
        if ($deliveryNoteLineIds) {
            $deliveryNoteLines = DeliveryNoteLine::query()
                ->with('salesOrderLine')
                ->where('delivery_note_id', $deliveryNote?->id)
                ->whereIn('id', array_values(array_unique($deliveryNoteLineIds)))
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
        }

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;

            $productId = $line['product_id'] ?? null;
            if (! $productId || ! isset($products[$productId])) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product on line {$lineIndex} does not exist."]]);
            }

            /** @var Product $product */
            $product = $products[$productId];

            // Stock product boundary check: must be sourced from a Delivery Note
            if ($product->type === 'stock') {
                $dnlIdCheck = $line['delivery_note_line_id'] ?? null;
                if (! $dnlIdCheck || ! $deliveryNote) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.product_id" => ["Stock product [{$product->code}] must be sourced from a Delivery Note."],
                    ]);
                }
            }

            if ($product->status !== 'active' || ! $product->is_sales_enabled) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product [{$product->code}] is inactive or not enabled for sales."]]);
            }

            $uomId = $line['unit_of_measure_id'] ?? $product->unit_of_measure_id;
            $quantityE6 = (int) ($line['quantity_e6'] ?? 0);
            $unitPriceMinor = (int) ($line['unit_price_minor'] ?? 0);

            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => ["Quantity on line {$lineIndex} must be greater than zero."]]);
            }
            if ($unitPriceMinor < 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_price_minor" => ["Unit price on line {$lineIndex} cannot be negative."]]);
            }

            $solId = $line['sales_order_line_id'] ?? null;
            $dnlId = $line['delivery_note_line_id'] ?? null;
            $sourceDescription = null;

            if ($solId) {
                /** @var SalesOrderLine|null $soLine */
                $soLine = $salesOrderLines->get($solId);
                if (! $soLine) {
                    throw ValidationException::withMessages(["lines.{$index}.sales_order_line_id" => ["Line {$lineIndex} does not belong to the selected Sales Order."]]);
                }

                if ($soLine->product_id !== $product->id) {
                    throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product on line {$lineIndex} must match the selected Sales Order line."]]);
                }

                if ($soLine->unit_of_measure_id !== $uomId) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => ["Unit of measure on line {$lineIndex} must match the selected Sales Order line."]]);
                }

                if ((int) $soLine->unit_price_minor !== $unitPriceMinor) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_price_minor" => ["Unit price on line {$lineIndex} must match the selected Sales Order line."]]);
                }

                // Cumulative over-invoicing check for Sales Order line
                $alreadyInvoicedQuery = CustomerInvoiceLine::query()
                    ->where('sales_order_line_id', $solId)
                    ->whereHas('customerInvoice', fn ($q) => $q->where('status', '!=', 'cancelled'));

                if ($currentInvoiceId) {
                    $alreadyInvoicedQuery->where('customer_invoice_id', '!=', $currentInvoiceId);
                }

                $alreadyInvoicedE6 = (int) $alreadyInvoicedQuery->sum('quantity_e6');
                if ($alreadyInvoicedE6 + $quantityE6 > $soLine->quantity_e6) {
                    $maxAllowedE6 = $soLine->quantity_e6 - $alreadyInvoicedE6;
                    $whole = intdiv($maxAllowedE6, 1000000);
                    $fraction = str_pad((string) intdiv($maxAllowedE6 % 1000000, 10000), 2, '0', STR_PAD_LEFT);
                    $maxAllowedDecimal = "{$whole}.{$fraction}";
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity_e6" => ["Invoiced quantity on line {$lineIndex} exceeds remaining Sales Order quantity. Maximum remaining allowed is {$maxAllowedDecimal}."],
                    ]);
                }

                $sourceDescription = $soLine->description;
            }

            if ($dnlId) {
                /** @var DeliveryNoteLine|null $dnLine */
                $dnLine = $deliveryNoteLines->get($dnlId);
                if (! $dnLine) {
                    throw ValidationException::withMessages(["lines.{$index}.delivery_note_line_id" => ["Line {$lineIndex} does not belong to the selected Delivery Note."]]);
                }

                if ($dnLine->product_id !== $product->id) {
                    throw ValidationException::withMessages(["lines.{$index}.product_id" => ["Product on line {$lineIndex} must match the selected Delivery Note line."]]);
                }

                if ($dnLine->unit_of_measure_id !== $uomId) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => ["Unit of measure on line {$lineIndex} must match the selected Delivery Note line."]]);
                }

                if (! $dnLine->salesOrderLine) {
                    throw ValidationException::withMessages(["lines.{$index}.delivery_note_line_id" => ["Delivery Note line {$lineIndex} is not linked to a Sales Order line."]]);
                }

                if ((int) $dnLine->salesOrderLine->unit_price_minor !== $unitPriceMinor) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_price_minor" => ["Unit price on line {$lineIndex} must match the Delivery Note source Sales Order line."]]);
                }

                // Cumulative over-invoicing check for Delivery Note line
                $alreadyInvoicedQuery = CustomerInvoiceLine::query()
                    ->where('delivery_note_line_id', $dnlId)
                    ->whereHas('customerInvoice', fn ($q) => $q->where('status', '!=', 'cancelled'));

                if ($currentInvoiceId) {
                    $alreadyInvoicedQuery->where('customer_invoice_id', '!=', $currentInvoiceId);
                }

                $alreadyInvoicedE6 = (int) $alreadyInvoicedQuery->sum('quantity_e6');
                if ($alreadyInvoicedE6 + $quantityE6 > $dnLine->quantity_e6) {
                    $maxAllowedE6 = $dnLine->quantity_e6 - $alreadyInvoicedE6;
                    $whole = intdiv($maxAllowedE6, 1000000);
                    $fraction = str_pad((string) intdiv($maxAllowedE6 % 1000000, 10000), 2, '0', STR_PAD_LEFT);
                    $maxAllowedDecimal = "{$whole}.{$fraction}";
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity_e6" => ["Invoiced quantity on line {$lineIndex} exceeds remaining Delivery Note quantity. Maximum remaining allowed is {$maxAllowedDecimal}."],
                    ]);
                }

                $sourceDescription = $dnLine->description;
            }

            if (! $solId && ! $dnlId && $product->unit_of_measure_id !== $uomId) {
                throw ValidationException::withMessages(["lines.{$index}.unit_of_measure_id" => ["Unit of measure on line {$lineIndex} must match the selected product."]]);
            }

            $lineTotalMinor = $this->calculateLineTotalMinor($quantityE6, $unitPriceMinor, $lineIndex);

            $taxCodeId = $line['tax_code_id'] ?? null;
            $taxRateBps = 0;
            $taxAmountMinor = 0;
            $grossAmountMinor = $lineTotalMinor;

            if ($taxCodeId) {
                $calcDate = $invoiceDate ?: now()->format('Y-m-d');
                $taxResult = $this->taxCalcService->calculateTax($taxCodeId, $lineTotalMinor, $calcDate);
                $taxRateBps = $taxResult['rate_bps'];
                $taxAmountMinor = $taxResult['tax_minor'];
                $grossAmountMinor = $taxResult['gross_minor'];
            }

            $validatedLines[] = [
                'sales_order_line_id' => $solId,
                'delivery_note_line_id' => $dnlId,
                'product_id' => $product->id,
                'unit_of_measure_id' => $uomId,
                'description' => $line['description'] ?? $sourceDescription ?? (is_array($product->name) ? ($product->name['en'] ?? '') : (string) $product->name),
                'quantity_e6' => $quantityE6,
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => $lineTotalMinor,
                'tax_code_id' => $taxCodeId,
                'tax_rate_bps' => $taxRateBps,
                'tax_amount_minor' => $taxAmountMinor,
                'gross_amount_minor' => $grossAmountMinor,
            ];
        }

        return $validatedLines;
    }

    private function calculateLineTotalMinor(int $quantityE6, int $unitPriceMinor, int $lineIndex): int
    {
        if ($unitPriceMinor > 0 && $quantityE6 > intdiv(PHP_INT_MAX, $unitPriceMinor)) {
            throw ValidationException::withMessages([
                "lines.{$lineIndex}.line_total" => ["Line {$lineIndex} amount exceeds maximum allowable integer limit."],
            ]);
        }

        $product = $quantityE6 * $unitPriceMinor;

        if ($product % 1000000 !== 0) {
            throw ValidationException::withMessages([
                "lines.{$lineIndex}.line_total" => ["Line {$lineIndex} total results in fractional minor currency units which is not permitted."],
            ]);
        }

        return intdiv($product, 1000000);
    }
}
