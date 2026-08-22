<?php

namespace App\Application\Sales;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerCreditNoteLine;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\ReceivableEntry;
use App\Models\SalesReturn;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCreditNoteService
{
    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    private const TAX_MODES = ['none', 'manual_rate', 'manual_amount'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, ?int $actorId = null): CustomerCreditNote
    {
        return DB::transaction(function () use ($data, $actorId): CustomerCreditNote {
            [$attributes, $validatedLines] = $this->resolveAttributesAndLines($data, null);

            /** @var CustomerCreditNote $note */
            $note = CustomerCreditNote::query()->create($attributes);

            foreach ($validatedLines as $index => $line) {
                $note->lines()->create([
                    'line_no' => $index + 1,
                    'customer_invoice_line_id' => $line['customer_invoice_line_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_subtotal_minor' => $line['line_subtotal_minor'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_minor' => $line['tax_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            $note->load(['customer', 'customerInvoice', 'salesReturn', 'lines']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_credit_note.create',
                entityType: 'customer_credit_note',
                entityId: $note->id,
                before: null,
                after: $note->toArray(),
            );

            return $note;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): CustomerCreditNote
    {
        return DB::transaction(function () use ($id, $data, $actorId): CustomerCreditNote {
            /** @var CustomerCreditNote $note */
            $note = CustomerCreditNote::query()->with(['lines'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft customer credit notes can be updated.']]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $note->lock_version) {
                throw ValidationException::withMessages(['lock_version' => ['The record has been modified by another user. Please refresh and try again.']]);
            }

            if (! isset($data['lines']) || empty($data['lines'])) {
                throw ValidationException::withMessages(['lines' => ['At least one line item is required.']]);
            }

            $effective = [
                'customer_id' => $note->customer_id,
                'currency' => $data['currency'] ?? $note->currency,
                'credit_date' => $data['credit_date'] ?? $note->credit_date->format('Y-m-d'),
                'tax_mode' => $data['tax_mode'] ?? $note->tax_mode,
                'customer_invoice_id' => array_key_exists('customer_invoice_id', $data) ? $data['customer_invoice_id'] : $note->customer_invoice_id,
                'sales_return_id' => array_key_exists('sales_return_id', $data) ? $data['sales_return_id'] : $note->sales_return_id,
                'reason' => $data['reason'] ?? $note->reason,
                'notes' => $data['notes'] ?? $note->notes,
                'lines' => $data['lines'],
            ];

            if (array_key_exists('tax_rate_bps', $data)) {
                $effective['tax_rate_bps'] = $data['tax_rate_bps'];
            } elseif ($effective['tax_mode'] === 'manual_rate') {
                $effective['tax_rate_bps'] = $note->tax_rate_bps;
            }

            if (array_key_exists('tax_minor_override', $data)) {
                $effective['tax_minor_override'] = $data['tax_minor_override'];
            } elseif ($effective['tax_mode'] === 'manual_amount') {
                $effective['tax_minor_override'] = $note->tax_minor;
            }

            [$attributes, $validatedLines] = $this->resolveAttributesAndLines($effective, $note->id);

            $before = $note->toArray();

            $note->update([
                'fiscal_year_id' => $attributes['fiscal_year_id'],
                'financial_period_id' => $attributes['financial_period_id'],
                'credit_date' => $attributes['credit_date'],
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $note->due_date,
                'currency' => $attributes['currency'],
                'customer_invoice_id' => $attributes['customer_invoice_id'],
                'sales_return_id' => $attributes['sales_return_id'],
                'subtotal_minor' => $attributes['subtotal_minor'],
                'tax_rate_bps' => $attributes['tax_rate_bps'],
                'tax_minor' => $attributes['tax_minor'],
                'total_minor' => $attributes['total_minor'],
                'tax_mode' => $attributes['tax_mode'],
                'reason' => $attributes['reason'],
                'notes' => $attributes['notes'],
                'updated_by' => $actorId,
                'lock_version' => $note->lock_version + 1,
            ]);

            $note->lines()->delete();

            foreach ($validatedLines as $index => $line) {
                $note->lines()->create([
                    'line_no' => $index + 1,
                    'customer_invoice_line_id' => $line['customer_invoice_line_id'],
                    'description' => $line['description'],
                    'quantity_e6' => $line['quantity_e6'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_subtotal_minor' => $line['line_subtotal_minor'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_minor' => $line['tax_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            $note->load(['customer', 'customerInvoice', 'salesReturn', 'lines']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_credit_note.update',
                entityType: 'customer_credit_note',
                entityId: $note->id,
                before: $before,
                after: $note->toArray(),
            );

            return $note;
        });
    }

    public function submit(string $id, ?int $actorId = null): CustomerCreditNote
    {
        return DB::transaction(function () use ($id, $actorId): CustomerCreditNote {
            /** @var CustomerCreditNote $note */
            $note = CustomerCreditNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Only draft customer credit notes can be submitted.']]);
            }

            if ($note->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Customer credit note must have at least one line item before submitting.']]);
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
                action: 'customer_credit_note.submit',
                entityType: 'customer_credit_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['customer', 'lines'])->toArray(),
            );

            return $note->fresh(['customer', 'lines']);
        });
    }

    public function approve(string $id, ?int $actorId = null): CustomerCreditNote
    {
        return DB::transaction(function () use ($id, $actorId): CustomerCreditNote {
            /** @var CustomerCreditNote $note */
            $note = CustomerCreditNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status === 'approved') {
                return $note->load(['customer', 'lines']);
            }

            if (! in_array($note->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or submitted customer credit notes can be approved.']]);
            }

            if ($note->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => ['Customer credit note must have at least one line item before approving.']]);
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
                action: 'customer_credit_note.approve',
                entityType: 'customer_credit_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['customer', 'lines'])->toArray(),
            );

            return $note->fresh(['customer', 'lines']);
        });
    }

    public function cancel(string $id, ?int $actorId = null): CustomerCreditNote
    {
        return DB::transaction(function () use ($id, $actorId): CustomerCreditNote {
            /** @var CustomerCreditNote $note */
            $note = CustomerCreditNote::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status === 'posted') {
                throw ValidationException::withMessages(['status' => ['Posted customer credit notes cannot be cancelled in this slice.']]);
            }

            if ($note->status === 'cancelled') {
                return $note->load(['customer', 'lines']);
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
                action: 'customer_credit_note.cancel',
                entityType: 'customer_credit_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['customer', 'lines'])->toArray(),
            );

            return $note->fresh(['customer', 'lines']);
        });
    }

    public function post(string $id, ?int $actorId = null): CustomerCreditNote
    {
        return DB::transaction(function () use ($id, $actorId): CustomerCreditNote {
            /** @var CustomerCreditNote $note */
            $note = CustomerCreditNote::query()->with(['lines', 'customer'])->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($note->status === 'posted') {
                return $note->load(['customer', 'lines', 'journalEntry', 'receivableEntry']);
            }

            if ($note->status !== 'approved') {
                throw ValidationException::withMessages(['status' => ['Only approved customer credit notes can be posted to AR/GL.']]);
            }

            if ($note->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['Customer credit note must have at least one line item before posting.']]);
            }

            $period = FinancialPeriod::query()->where('id', $note->financial_period_id)->lockForUpdate()->firstOrFail();
            if (! $period->isOpen()) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period is closed.']]);
            }
            if ($period->fiscal_year_id !== $note->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => ['Financial period does not belong to the credit note fiscal year.']]);
            }
            $creditDate = $note->credit_date->format('Y-m-d');
            if ($creditDate < $period->start_date || $creditDate > $period->end_date) {
                throw ValidationException::withMessages(['credit_date' => ['Credit date must fall within the financial period.']]);
            }

            $salesReturnsAccount = $this->mappingService->getAccount('sales_returns');
            $arAccount = $this->mappingService->getAccount('ar_control');
            $outputTaxAccount = (int) $note->tax_minor > 0 ? $this->mappingService->getAccount('output_tax_payable') : null;

            if ($salesReturnsAccount->currency !== $note->currency || $arAccount->currency !== $note->currency) {
                throw ValidationException::withMessages(['currency' => ['Mapped GL account currency must match credit note currency.']]);
            }
            if ($outputTaxAccount && $outputTaxAccount->currency !== $note->currency) {
                throw ValidationException::withMessages(['currency' => ['Mapped GL account currency must match credit note currency.']]);
            }

            $number = $note->number;
            if (! $number) {
                $orderYear = Carbon::parse($creditDate)->format('Y');
                $seq = $this->numberAllocator->nextValue('customer.credit_note');
                $number = 'CN-'.$orderYear.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            $before = $note->toArray();

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $creditDate,
                'financial_period_id' => $note->financial_period_id,
                'source_type' => 'customer_credit_note',
                'source_id' => $note->id,
                'description' => "Customer Credit Note {$number} - {$note->customer->name}",
                'currency' => $note->currency,
                'fx_rate_e6' => 1000000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $salesReturnsAccount->id,
                'memo' => "Sales Returns - Credit Note {$number}",
                'debit_minor' => $note->subtotal_minor,
                'credit_minor' => 0,
                'debit_txn_minor' => $note->subtotal_minor,
                'credit_txn_minor' => 0,
                'currency' => $note->currency,
                'fx_rate_e6' => 1000000,
            ]);

            $lineNo = 2;
            if ($outputTaxAccount) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $outputTaxAccount->id,
                    'memo' => "Output Tax Payable - Credit Note {$number}",
                    'debit_minor' => $note->tax_minor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $note->tax_minor,
                    'credit_txn_minor' => 0,
                    'currency' => $note->currency,
                    'fx_rate_e6' => 1000000,
                ]);
            }

            $arLine = $journalEntry->lines()->create([
                'line_no' => $lineNo,
                'account_id' => $arAccount->id,
                'memo' => "AR Control - Credit Note {$number}",
                'debit_minor' => 0,
                'credit_minor' => $note->total_minor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $note->total_minor,
                'currency' => $note->currency,
                'fx_rate_e6' => 1000000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            /** @var ReceivableEntry $receivableEntry */
            $receivableEntry = ReceivableEntry::query()->create([
                'customer_id' => $note->customer_id,
                'source_type' => 'customer_credit_note',
                'source_id' => $note->id,
                'journal_entry_id' => $postedJournal->id,
                'journal_line_id' => $arLine->id,
                'financial_period_id' => $note->financial_period_id,
                'entry_date' => $creditDate,
                'due_date' => $note->due_date,
                'description' => "Customer Credit Note {$number}",
                'currency' => $note->currency,
                'debit_minor' => 0,
                'credit_minor' => $note->total_minor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $note->total_minor,
                'fx_rate_e6' => 1000000,
                'created_by' => $actorId,
            ]);

            $note->number = $number;
            $note->status = 'posted';
            $note->journal_entry_id = $postedJournal->id;
            $note->receivable_entry_id = $receivableEntry->id;
            $note->posted_by = $actorId;
            $note->posted_at = Carbon::now();
            $note->updated_by = $actorId;
            $note->lock_version = $note->lock_version + 1;
            $note->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_credit_note.post',
                entityType: 'customer_credit_note',
                entityId: $note->id,
                before: $before,
                after: $note->fresh(['customer', 'lines', 'journalEntry', 'receivableEntry'])->toArray(),
            );

            return $note->fresh(['customer', 'lines', 'journalEntry', 'receivableEntry']);
        });
    }

    private function resolveAttributesAndLines(array $data, ?string $currentNoteId): array
    {
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

        $creditDate = $data['credit_date'] ?? null;
        if (! $creditDate) {
            throw ValidationException::withMessages(['credit_date' => ['Credit date is required.']]);
        }

        $period = $this->resolveFinancialPeriodForDate($creditDate);

        $taxMode = $data['tax_mode'] ?? 'none';
        if (! in_array($taxMode, self::TAX_MODES, true)) {
            throw ValidationException::withMessages(['tax_mode' => ['Tax mode must be one of: '.implode(', ', self::TAX_MODES).'.']]);
        }

        $taxRateBps = 0;
        if ($taxMode === 'manual_rate') {
            if (! isset($data['tax_rate_bps']) || ! is_numeric($data['tax_rate_bps']) || (int) $data['tax_rate_bps'] < 0) {
                throw ValidationException::withMessages(['tax_rate_bps' => ['Tax rate in basis points is required for manual rate mode and must be an integer >= 0.']]);
            }
            $taxRateBps = (int) $data['tax_rate_bps'];
        }

        $taxMinorOverride = null;
        if ($taxMode === 'manual_amount') {
            if (! isset($data['tax_minor_override']) || ! is_numeric($data['tax_minor_override']) || (int) $data['tax_minor_override'] < 0) {
                throw ValidationException::withMessages(['tax_minor_override' => ['Tax amount override is required for manual amount mode and must be an integer >= 0.']]);
            }
            $taxMinorOverride = (int) $data['tax_minor_override'];
        }

        $customerInvoice = null;
        if (! empty($data['customer_invoice_id'])) {
            /** @var CustomerInvoice|null $customerInvoice */
            $customerInvoice = CustomerInvoice::query()->where('id', $data['customer_invoice_id'])->first();
            if (! $customerInvoice || $customerInvoice->status !== 'posted') {
                throw ValidationException::withMessages(['customer_invoice_id' => ['Referenced Customer Invoice must be posted.']]);
            }
            if ($customerInvoice->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['customer_invoice_id' => ['Customer Invoice must belong to this customer.']]);
            }
            if ($customerInvoice->currency !== $currency) {
                throw ValidationException::withMessages(['currency' => ['Currency must match the referenced Customer Invoice currency.']]);
            }
        }

        if (! empty($data['sales_return_id'])) {
            /** @var SalesReturn|null $salesReturn */
            $salesReturn = SalesReturn::query()->where('id', $data['sales_return_id'])->first();
            if (! $salesReturn || $salesReturn->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['sales_return_id' => ['Sales Return must belong to this customer.']]);
            }
        }

        $lines = $data['lines'] ?? [];
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

            $quantityE6 = array_key_exists('quantity_e6', $line) && $line['quantity_e6'] !== null
                ? (int) $line['quantity_e6']
                : null;
            if ($quantityE6 !== null && $quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => ["Quantity on line {$lineIndex} must be greater than zero."]]);
            }

            $unitPriceMinor = (int) ($line['unit_price_minor'] ?? 0);
            if ($unitPriceMinor < 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_price_minor" => ["Unit price on line {$lineIndex} cannot be negative."]]);
            }

            $customerInvoiceLineId = $line['customer_invoice_line_id'] ?? null;
            if ($customerInvoiceLineId) {
                if (! $customerInvoice) {
                    throw ValidationException::withMessages(["lines.{$index}.customer_invoice_line_id" => ["Line {$lineIndex} references a Customer Invoice line but no Customer Invoice was selected."]]);
                }

                /** @var CustomerInvoiceLine|null $cil */
                $cil = CustomerInvoiceLine::query()
                    ->where('id', $customerInvoiceLineId)
                    ->where('customer_invoice_id', $customerInvoice->id)
                    ->first();
                if (! $cil) {
                    throw ValidationException::withMessages(["lines.{$index}.customer_invoice_line_id" => ["Customer Invoice line on line {$lineIndex} does not belong to the referenced invoice."]]);
                }

                if ($quantityE6 !== null) {
                    $alreadyCreditedQuery = CustomerCreditNoteLine::query()
                        ->where('customer_invoice_line_id', $cil->id)
                        ->whereHas('customerCreditNote', fn ($q) => $q->where('status', '!=', 'cancelled'));

                    if ($currentNoteId) {
                        $alreadyCreditedQuery->where('customer_credit_note_id', '!=', $currentNoteId);
                    }

                    $alreadyCreditedE6 = (int) $alreadyCreditedQuery->sum('quantity_e6');
                    if ($alreadyCreditedE6 + $quantityE6 > $cil->quantity_e6) {
                        $maxAllowedE6 = $cil->quantity_e6 - $alreadyCreditedE6;
                        $whole = intdiv($maxAllowedE6, 1000000);
                        $fraction = str_pad((string) abs($maxAllowedE6 % 1000000), 6, '0', STR_PAD_LEFT);
                        throw ValidationException::withMessages([
                            "lines.{$index}.quantity_e6" => ["Credited quantity on line {$lineIndex} exceeds remaining invoiced quantity. Maximum remaining allowed is {$whole}.{$fraction}."],
                        ]);
                    }
                }
            }

            $lineSubtotalMinor = $quantityE6 !== null
                ? intdiv($quantityE6 * $unitPriceMinor, 1000000)
                : $unitPriceMinor;

            $effectiveRateBps = 0;
            $lineTaxMinor = 0;
            if ($taxMode === 'manual_rate') {
                $overrideRate = $line['tax_rate_bps'] ?? null;
                $effectiveRateBps = $overrideRate !== null ? (int) $overrideRate : $taxRateBps;
                if ($effectiveRateBps < 0) {
                    throw ValidationException::withMessages(["lines.{$index}.tax_rate_bps" => ["Tax rate on line {$lineIndex} cannot be negative."]]);
                }
                $lineTaxMinor = intdiv(($lineSubtotalMinor * $effectiveRateBps) + 5000, 10000);
            }

            $lineTotalMinor = $lineSubtotalMinor + $lineTaxMinor;

            $validatedLines[] = [
                'customer_invoice_line_id' => $customerInvoiceLineId,
                'description' => trim($description),
                'quantity_e6' => $quantityE6,
                'unit_price_minor' => $unitPriceMinor,
                'line_subtotal_minor' => $lineSubtotalMinor,
                'tax_rate_bps' => $effectiveRateBps,
                'tax_minor' => $lineTaxMinor,
                'line_total_minor' => $lineTotalMinor,
            ];
        }

        $subtotalMinor = array_sum(array_column($validatedLines, 'line_subtotal_minor'));
        $taxMinor = $taxMode === 'manual_amount'
            ? (int) $taxMinorOverride
            : array_sum(array_column($validatedLines, 'tax_minor'));
        $totalMinor = $subtotalMinor + $taxMinor;

        $attributes = [
            'customer_id' => $customer->id,
            'customer_invoice_id' => $customerInvoice?->id,
            'sales_return_id' => $data['sales_return_id'] ?? null,
            'fiscal_year_id' => $period->fiscal_year_id,
            'financial_period_id' => $period->id,
            'credit_date' => $creditDate,
            'currency' => $currency,
            'subtotal_minor' => $subtotalMinor,
            'tax_rate_bps' => $taxRateBps,
            'tax_minor' => $taxMinor,
            'total_minor' => $totalMinor,
            'tax_mode' => $taxMode,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return [$attributes, $validatedLines];
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
            throw ValidationException::withMessages(['credit_date' => ["No open financial period covers date {$date}."]]);
        }

        return $period;
    }
}
