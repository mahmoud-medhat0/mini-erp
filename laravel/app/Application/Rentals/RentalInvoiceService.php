<?php

namespace App\Application\Rentals;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxPeriodGuard;
use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\ReceivableEntry;
use App\Models\RentalContract;
use App\Models\RentalContractLine;
use App\Models\RentalInvoice;
use App\Models\RentalInvoiceLine;
use App\Models\RentalReturnLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalInvoiceService
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public const INVOICE_TYPES = ['periodic_rent', 'deposit', 'final_charges', 'mixed'];

    public const LINE_TYPES = ['rent', 'deposit', 'damage_charge', 'late_fee', 'other_charge'];

    private const CONTRACT_BILLABLE_STATUSES = ['approved', 'active', 'completed'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly PeriodGuard $periodGuard,
        private readonly TaxCalculationService $taxCalcService,
        private readonly TaxPeriodGuard $taxPeriodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): RentalInvoice
    {
        return DB::transaction(function () use ($data, $actorId): RentalInvoice {
            $contract = $this->lockedContract($data['rental_contract_id'] ?? null);
            $payload = $this->validatedPayload($contract, $data);

            /** @var RentalInvoice $invoice */
            $invoice = RentalInvoice::query()->create([
                ...$payload['header'],
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($invoice, $payload['lines']);

            $this->auditLogger->record($actorId, 'rental_invoice.create', 'rental_invoice', $invoice->id, after: $invoice->fresh($this->relations())->toArray());

            return $invoice->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): RentalInvoice
    {
        return DB::transaction(function () use ($id, $data, $actorId): RentalInvoice {
            /** @var RentalInvoice $invoice */
            $invoice = RentalInvoice::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft rental invoices can be updated.')]]);
            }

            if ((int) ($data['lock_version'] ?? 0) !== (int) $invoice->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The rental invoice was modified by another user. Please refresh and try again.')]]);
            }

            $contract = $this->lockedContract($data['rental_contract_id'] ?? $invoice->rental_contract_id);
            $before = $invoice->fresh($this->relations())->toArray();
            $payload = $this->validatedPayload($contract, [
                'invoice_type' => $data['invoice_type'] ?? $invoice->invoice_type,
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date?->format('Y-m-d'),
                'billing_period_start' => array_key_exists('billing_period_start', $data) ? $data['billing_period_start'] : $invoice->billing_period_start?->format('Y-m-d'),
                'billing_period_end' => array_key_exists('billing_period_end', $data) ? $data['billing_period_end'] : $invoice->billing_period_end?->format('Y-m-d'),
                'currency' => $data['currency'] ?? $invoice->currency,
                'fx_rate_e6' => $data['fx_rate_e6'] ?? $invoice->fx_rate_e6,
                'reference' => array_key_exists('reference', $data) ? $data['reference'] : $invoice->reference,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'lines' => $data['lines'] ?? $invoice->lines->map(fn (RentalInvoiceLine $line): array => [
                    'line_type' => $line->line_type,
                    'rental_contract_line_id' => $line->rental_contract_line_id,
                    'rental_return_line_id' => $line->rental_return_line_id,
                    'description' => $line->description,
                    'quantity_e6' => $line->quantity_e6,
                    'unit_amount_minor' => $line->unit_amount_minor,
                    'tax_code_id' => $line->tax_code_id,
                    'notes' => $line->notes,
                ])->all(),
            ], $invoice->id);

            $invoice->update([
                ...$payload['header'],
                'updated_by' => $actorId,
                'lock_version' => ((int) $invoice->lock_version) + 1,
            ]);

            $invoice->lines()->delete();
            $this->replaceLines($invoice, $payload['lines']);

            $this->auditLogger->record($actorId, 'rental_invoice.update', 'rental_invoice', $invoice->id, before: $before, after: $invoice->fresh($this->relations())->toArray());

            return $invoice->fresh($this->relations());
        });
    }

    public function submit(string $id, ?int $actorId = null): RentalInvoice
    {
        return DB::transaction(function () use ($id, $actorId): RentalInvoice {
            $invoice = $this->lockedInvoice($id);

            if (in_array($invoice->status, ['submitted', 'approved', 'posted'], true)) {
                return $invoice->fresh($this->relations());
            }

            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft rental invoices can be submitted.')]]);
            }

            if ($invoice->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => [__('Rental invoice must have at least one line before submitting.')]]);
            }

            $before = $invoice->fresh($this->relations())->toArray();
            $invoice->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => ((int) $invoice->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'rental_invoice.submit', 'rental_invoice', $invoice->id, before: $before, after: $invoice->fresh($this->relations())->toArray());

            return $invoice->fresh($this->relations());
        });
    }

    public function approve(string $id, ?int $actorId = null): RentalInvoice
    {
        return DB::transaction(function () use ($id, $actorId): RentalInvoice {
            $invoice = $this->lockedInvoice($id);

            if (in_array($invoice->status, ['approved', 'posted'], true)) {
                return $invoice->fresh($this->relations());
            }

            if (! in_array($invoice->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only draft or submitted rental invoices can be approved.')]]);
            }

            if ($invoice->total_minor <= 0) {
                throw ValidationException::withMessages(['total_minor' => [__('Rental invoice total must be greater than zero before approval.')]]);
            }

            $before = $invoice->fresh($this->relations())->toArray();
            $invoice->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => ((int) $invoice->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'rental_invoice.approve', 'rental_invoice', $invoice->id, before: $before, after: $invoice->fresh($this->relations())->toArray());

            return $invoice->fresh($this->relations());
        });
    }

    public function post(string $id, ?int $actorId = null): RentalInvoice
    {
        return DB::transaction(function () use ($id, $actorId): RentalInvoice {
            /** @var RentalInvoice $invoice */
            $invoice = RentalInvoice::query()
                ->with(['customer', 'contract', 'lines'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $invoice->financial_period_id, (string) $invoice->invoice_date);
            $this->taxPeriodGuard->ensureDateNotFiled((string) $invoice->invoice_date);

            if ($invoice->status === 'posted') {
                return $invoice->fresh($this->relations());
            }

            if ($invoice->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved rental invoices can be posted.')]]);
            }

            if ($invoice->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Rental invoice must have at least one line before posting.')]]);
            }

            $number = $invoice->number ?: $this->number('rental.invoice', 'RINV', $invoice->invoice_date);
            $subtotalMinor = (int) ($invoice->subtotal_minor ?: $invoice->lines->sum('line_total_minor'));
            $taxAmountMinor = (int) ($invoice->tax_amount_minor ?: $invoice->lines->sum('tax_amount_minor'));
            $totalMinor = $subtotalMinor + $taxAmountMinor;

            if ($totalMinor <= 0) {
                throw ValidationException::withMessages(['total_minor' => [__('Rental invoice total must be greater than zero before posting.')]]);
            }

            $accounts = $this->postingAccounts($invoice);
            $before = $invoice->fresh($this->relations())->toArray();

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $invoice->invoice_date,
                'financial_period_id' => $invoice->financial_period_id,
                'branch_id' => $invoice->branch_id,
                'source_type' => 'rental_invoice',
                'source_id' => $invoice->id,
                'description' => "Rental Invoice {$number} - {$invoice->customer?->code}",
                'reference' => $invoice->reference,
                'currency' => $invoice->currency,
                'fx_rate_e6' => $invoice->fx_rate_e6,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'lock_version' => 1,
            ]);

            $lineNo = 1;
            $arLine = $journalEntry->lines()->create([
                'line_no' => $lineNo++,
                'account_id' => $accounts['ar_control']->id,
                'branch_id' => $invoice->branch_id,
                'memo' => "AR Control - Rental Invoice {$number}",
                'debit_minor' => $totalMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $totalMinor,
                'credit_txn_minor' => 0,
                'currency' => $invoice->currency,
                'fx_rate_e6' => $invoice->fx_rate_e6,
            ]);

            foreach ($this->lineTotalsByMappingKey($invoice) as $mappingKey => $amountMinor) {
                if ($amountMinor <= 0) {
                    continue;
                }

                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $accounts[$mappingKey]->id,
                    'branch_id' => $invoice->branch_id,
                    'memo' => $this->memoForMappingKey($mappingKey, $number),
                    'debit_minor' => 0,
                    'credit_minor' => $amountMinor,
                    'debit_txn_minor' => 0,
                    'credit_txn_minor' => $amountMinor,
                    'currency' => $invoice->currency,
                    'fx_rate_e6' => $invoice->fx_rate_e6,
                ]);
            }

            if ($taxAmountMinor > 0) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $accounts['output_tax_payable']->id,
                    'branch_id' => $invoice->branch_id,
                    'memo' => "Output Tax Payable - Rental Invoice {$number}",
                    'debit_minor' => 0,
                    'credit_minor' => $taxAmountMinor,
                    'debit_txn_minor' => 0,
                    'credit_txn_minor' => $taxAmountMinor,
                    'currency' => $invoice->currency,
                    'fx_rate_e6' => $invoice->fx_rate_e6,
                ]);
            }

            $postedJournal = $this->postingEngine->post($journalEntry, (int) $actorId, allowControlAccounts: true);

            /** @var ReceivableEntry $receivableEntry */
            $receivableEntry = ReceivableEntry::query()->create([
                'customer_id' => $invoice->customer_id,
                'source_type' => 'rental_invoice',
                'source_id' => $invoice->id,
                'journal_entry_id' => $postedJournal->id,
                'journal_line_id' => $arLine->id,
                'financial_period_id' => $invoice->financial_period_id,
                'entry_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date ?? $invoice->invoice_date,
                'description' => "Rental Invoice {$number}",
                'currency' => $invoice->currency,
                'debit_minor' => $totalMinor,
                'credit_minor' => 0,
                'debit_txn_minor' => $totalMinor,
                'credit_txn_minor' => 0,
                'fx_rate_e6' => $invoice->fx_rate_e6,
                'created_by' => $actorId,
            ]);

            $invoice->update([
                'number' => $number,
                'status' => 'posted',
                'subtotal_minor' => $subtotalMinor,
                'tax_amount_minor' => $taxAmountMinor,
                'total_minor' => $totalMinor,
                'journal_entry_id' => $postedJournal->id,
                'receivable_entry_id' => $receivableEntry->id,
                'posted_by' => $actorId,
                'posted_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => ((int) $invoice->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'rental_invoice.post', 'rental_invoice', $invoice->id, before: $before, after: $invoice->fresh($this->relations())->toArray());

            return $invoice->fresh($this->relations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): RentalInvoice
    {
        return DB::transaction(function () use ($id, $actorId): RentalInvoice {
            $invoice = $this->lockedInvoice($id);

            if ($invoice->status === 'cancelled') {
                return $invoice->fresh($this->relations());
            }

            if ($invoice->status === 'posted') {
                throw ValidationException::withMessages(['status' => [__('Posted rental invoices require a credit/reversal workflow instead of cancellation.')]]);
            }

            $before = $invoice->fresh($this->relations())->toArray();
            $invoice->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'updated_by' => $actorId,
                'lock_version' => ((int) $invoice->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'rental_invoice.cancel', 'rental_invoice', $invoice->id, before: $before, after: $invoice->fresh($this->relations())->toArray());

            return $invoice->fresh($this->relations());
        });
    }

    private function validatedPayload(RentalContract $contract, array $data, ?string $currentInvoiceId = null): array
    {
        if (! in_array($contract->status, self::CONTRACT_BILLABLE_STATUSES, true)) {
            throw ValidationException::withMessages(['rental_contract_id' => [__('Only approved, active, or completed rental contracts can be invoiced.')]]);
        }

        $invoiceType = (string) ($data['invoice_type'] ?? 'periodic_rent');
        if (! in_array($invoiceType, self::INVOICE_TYPES, true)) {
            throw ValidationException::withMessages(['invoice_type' => [__('Invalid rental invoice type.')]]);
        }

        $currency = strtoupper((string) ($data['currency'] ?? $contract->currency));
        if ($currency !== $contract->currency) {
            throw ValidationException::withMessages(['currency' => [__('Rental invoice currency must match the rental contract currency.')]]);
        }

        $fxRateE6 = (int) ($data['fx_rate_e6'] ?? 1000000);
        if ($fxRateE6 !== 1000000) {
            throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
        }

        $invoiceDate = $this->requiredDate($data['invoice_date'] ?? null, 'invoice_date');
        $period = $this->resolveFinancialPeriodForDate($invoiceDate);
        $billingStart = $this->nullableDate($data['billing_period_start'] ?? null, 'billing_period_start');
        $billingEnd = $this->nullableDate($data['billing_period_end'] ?? null, 'billing_period_end');
        if ($billingStart !== null && $billingEnd !== null && $billingEnd < $billingStart) {
            throw ValidationException::withMessages(['billing_period_end' => [__('Billing period end must be on or after billing period start.')]]);
        }

        $lines = $this->validatedLines($contract, $data['lines'] ?? [], $invoiceDate, $billingStart, $billingEnd, $currentInvoiceId);
        $subtotalMinor = array_sum(array_column($lines, 'line_total_minor'));
        $taxAmountMinor = array_sum(array_column($lines, 'tax_amount_minor'));
        $totalMinor = $subtotalMinor + $taxAmountMinor;

        if ($totalMinor <= 0) {
            throw ValidationException::withMessages(['lines' => [__('Rental invoice total must be greater than zero.')]]);
        }

        return [
            'header' => [
                'rental_contract_id' => $contract->id,
                'customer_id' => $contract->customer_id,
                'branch_id' => $contract->branch_id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'invoice_type' => $invoiceType,
                'invoice_date' => $invoiceDate,
                'due_date' => $this->nullableDate($data['due_date'] ?? null, 'due_date'),
                'billing_period_start' => $billingStart,
                'billing_period_end' => $billingEnd,
                'currency' => $currency,
                'fx_rate_e6' => $fxRateE6,
                'subtotal_minor' => $subtotalMinor,
                'tax_amount_minor' => $taxAmountMinor,
                'total_minor' => $totalMinor,
                'reference' => $this->nullableString($data['reference'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ],
            'lines' => $lines,
        ];
    }

    private function validatedLines(
        RentalContract $contract,
        mixed $rawLines,
        string $invoiceDate,
        ?string $billingStart,
        ?string $billingEnd,
        ?string $currentInvoiceId,
    ): array {
        if (! is_array($rawLines) || count($rawLines) === 0) {
            throw ValidationException::withMessages(['lines' => [__('Rental invoice must have at least one line.')]]);
        }

        $contractLines = $contract->lines()->with('rentableItem')->get()->keyBy('id');
        $lines = [];
        $seen = [];

        foreach (array_values($rawLines) as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(["lines.{$index}" => [__('Invalid rental invoice line.')]]);
            }

            $lineType = (string) ($line['line_type'] ?? '');
            if (! in_array($lineType, self::LINE_TYPES, true)) {
                throw ValidationException::withMessages(["lines.{$index}.line_type" => [__('Invalid rental invoice line type.')]]);
            }

            $contractLineId = $this->nullableUuid($line['rental_contract_line_id'] ?? null, "lines.{$index}.rental_contract_line_id");
            $returnLineId = $this->nullableUuid($line['rental_return_line_id'] ?? null, "lines.{$index}.rental_return_line_id");

            $contractLine = null;
            if ($contractLineId !== null) {
                /** @var RentalContractLine|null $contractLine */
                $contractLine = $contractLines->get($contractLineId);
                if (! $contractLine) {
                    throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Selected contract line does not belong to the rental contract.')]]);
                }
            }

            if (in_array($lineType, ['rent', 'deposit'], true) && ! $contractLine) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Rent and deposit lines must reference a rental contract line.')]]);
            }

            if ($lineType === 'rent' && ($billingStart === null || $billingEnd === null)) {
                throw ValidationException::withMessages(['billing_period_start' => [__('Rent lines require a billing period start and end.')]]);
            }

            $returnLine = null;
            $returnId = null;
            if ($returnLineId !== null) {
                /** @var RentalReturnLine|null $returnLine */
                $returnLine = RentalReturnLine::query()
                    ->with(['rentalReturn', 'contractLine'])
                    ->whereKey($returnLineId)
                    ->lockForUpdate()
                    ->first();

                if (! $returnLine || $returnLine->rentalReturn?->rental_contract_id !== $contract->id) {
                    throw ValidationException::withMessages(["lines.{$index}.rental_return_line_id" => [__('Selected return line does not belong to the rental contract.')]]);
                }

                if ($returnLine->rentalReturn?->status !== 'completed') {
                    throw ValidationException::withMessages(["lines.{$index}.rental_return_line_id" => [__('Return line charges require a completed rental return.')]]);
                }

                $returnId = $returnLine->rental_return_id;
                if ($contractLine && $returnLine->rental_contract_line_id !== $contractLine->id) {
                    throw ValidationException::withMessages(["lines.{$index}.rental_return_line_id" => [__('Return line must match the selected contract line.')]]);
                }

                if (! $contractLine) {
                    /** @var RentalContractLine|null $contractLine */
                    $contractLine = $contractLines->get($returnLine->rental_contract_line_id);
                    $contractLineId = $contractLine?->id;
                }
            }

            if ($lineType === 'damage_charge' && ! $returnLine) {
                throw ValidationException::withMessages(["lines.{$index}.rental_return_line_id" => [__('Damage charge lines must reference a completed rental return line.')]]);
            }

            $duplicateKey = implode(':', [$lineType, $contractLineId ?? '-', $returnLineId ?? '-']);
            if (isset($seen[$duplicateKey])) {
                throw ValidationException::withMessages(["lines.{$index}.line_type" => [__('Duplicate rental invoice line source in the same document.')]]);
            }
            $seen[$duplicateKey] = true;

            $quantityE6 = (int) ($line['quantity_e6'] ?? 1000000);
            $unitAmountMinor = (int) ($line['unit_amount_minor'] ?? 0);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Quantity must be greater than zero.')]]);
            }
            if ($unitAmountMinor < 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_amount_minor" => [__('Unit amount cannot be negative.')]]);
            }

            $lineTotalMinor = $this->calculateLineTotalMinor($quantityE6, $unitAmountMinor, $index + 1);
            if ($lineTotalMinor <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.line_total_minor" => [__('Line amount must be greater than zero.')]]);
            }

            $this->assertNoDuplicateOrOverBilling($lineType, $contractLine, $returnLine, $lineTotalMinor, $billingStart, $billingEnd, $currentInvoiceId, $index);

            $taxCodeId = $this->nullableUuid($line['tax_code_id'] ?? null, "lines.{$index}.tax_code_id");
            $taxRateBps = 0;
            $taxAmountMinor = 0;
            $grossAmountMinor = $lineTotalMinor;
            if ($taxCodeId !== null) {
                $tax = $this->taxCalcService->calculateTax($taxCodeId, $lineTotalMinor, $invoiceDate);
                $taxRateBps = (int) $tax['rate_bps'];
                $taxAmountMinor = (int) $tax['tax_minor'];
                $grossAmountMinor = (int) $tax['gross_minor'];
            }

            $lines[] = [
                'line_type' => $lineType,
                'rental_contract_line_id' => $contractLineId,
                'rental_return_id' => $returnId,
                'rental_return_line_id' => $returnLineId,
                'description' => $this->nullableString($line['description'] ?? null) ?: $this->defaultDescription($lineType, $contractLine, $returnLine),
                'quantity_e6' => $quantityE6,
                'unit_amount_minor' => $unitAmountMinor,
                'line_total_minor' => $lineTotalMinor,
                'tax_code_id' => $taxCodeId,
                'tax_rate_bps' => $taxRateBps,
                'tax_amount_minor' => $taxAmountMinor,
                'gross_amount_minor' => $grossAmountMinor,
                'notes' => $this->nullableString($line['notes'] ?? null),
            ];
        }

        return $lines;
    }

    private function assertNoDuplicateOrOverBilling(
        string $lineType,
        ?RentalContractLine $contractLine,
        ?RentalReturnLine $returnLine,
        int $lineTotalMinor,
        ?string $billingStart,
        ?string $billingEnd,
        ?string $currentInvoiceId,
        int $index,
    ): void {
        if ($lineType === 'rent' && $contractLine && $billingStart && $billingEnd) {
            $alreadyExists = RentalInvoiceLine::query()
                ->where('line_type', 'rent')
                ->where('rental_contract_line_id', $contractLine->id)
                ->whereHas('invoice', function ($query) use ($billingStart, $billingEnd, $currentInvoiceId): void {
                    $query->where('status', '!=', 'cancelled')
                        ->where('billing_period_start', $billingStart)
                        ->where('billing_period_end', $billingEnd);

                    if ($currentInvoiceId !== null) {
                        $query->where('id', '!=', $currentInvoiceId);
                    }
                })
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('This rental line has already been invoiced for the selected billing period.')]]);
            }
        }

        if ($lineType === 'deposit' && $contractLine) {
            $alreadyBilledMinor = (int) RentalInvoiceLine::query()
                ->where('line_type', 'deposit')
                ->where('rental_contract_line_id', $contractLine->id)
                ->whereHas('invoice', function ($query) use ($currentInvoiceId): void {
                    $query->where('status', '!=', 'cancelled');

                    if ($currentInvoiceId !== null) {
                        $query->where('id', '!=', $currentInvoiceId);
                    }
                })
                ->sum('line_total_minor');

            if ($alreadyBilledMinor + $lineTotalMinor > (int) $contractLine->deposit_minor) {
                throw ValidationException::withMessages(["lines.{$index}.unit_amount_minor" => [__('Deposit invoice amount exceeds the remaining contract-line deposit.')]]);
            }
        }

        if ($lineType === 'damage_charge' && $returnLine) {
            $alreadyBilledMinor = (int) RentalInvoiceLine::query()
                ->where('line_type', 'damage_charge')
                ->where('rental_return_line_id', $returnLine->id)
                ->whereHas('invoice', function ($query) use ($currentInvoiceId): void {
                    $query->where('status', '!=', 'cancelled');

                    if ($currentInvoiceId !== null) {
                        $query->where('id', '!=', $currentInvoiceId);
                    }
                })
                ->sum('line_total_minor');

            if ($alreadyBilledMinor + $lineTotalMinor > (int) $returnLine->estimated_damage_charge_minor) {
                throw ValidationException::withMessages(["lines.{$index}.unit_amount_minor" => [__('Damage charge exceeds the remaining inspected damage amount.')]]);
            }
        }
    }

    private function replaceLines(RentalInvoice $invoice, array $lines): void
    {
        foreach ($lines as $index => $line) {
            RentalInvoiceLine::query()->create([
                'rental_invoice_id' => $invoice->id,
                'line_no' => $index + 1,
                ...$line,
            ]);
        }
    }

    private function postingAccounts(RentalInvoice $invoice): array
    {
        $branchId = $invoice->branch_id;
        $accounts = [
            'ar_control' => $this->mappingService->getAccount('ar_control', $branchId),
        ];

        foreach (array_keys($this->lineTotalsByMappingKey($invoice)) as $mappingKey) {
            $accounts[$mappingKey] = $this->mappingService->getAccount($mappingKey, $branchId);
        }

        if ((int) $invoice->tax_amount_minor > 0) {
            $accounts['output_tax_payable'] = $this->mappingService->getAccount('output_tax_payable', $branchId);
        }

        foreach ($accounts as $key => $account) {
            if ($account->currency !== $invoice->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Mapped account for [:key] uses :account_currency; rental invoice currency is :invoice_currency.', [
                        'key' => $key,
                        'account_currency' => $account->currency,
                        'invoice_currency' => $invoice->currency,
                    ])],
                ]);
            }
        }

        return $accounts;
    }

    /**
     * @return array<string, int>
     */
    private function lineTotalsByMappingKey(RentalInvoice $invoice): array
    {
        $totals = [];

        foreach ($invoice->lines as $line) {
            $key = $this->mappingKeyForLineType((string) $line->line_type);
            $totals[$key] = ($totals[$key] ?? 0) + (int) $line->line_total_minor;
        }

        return $totals;
    }

    private function mappingKeyForLineType(string $lineType): string
    {
        return match ($lineType) {
            'rent' => 'rental_revenue',
            'deposit' => 'rental_deposit_liability',
            'damage_charge' => 'rental_damage_revenue',
            'late_fee' => 'rental_late_fee_revenue',
            'other_charge' => 'rental_other_revenue',
        };
    }

    private function memoForMappingKey(string $mappingKey, string $number): string
    {
        return match ($mappingKey) {
            'rental_revenue' => "Rental Revenue - Rental Invoice {$number}",
            'rental_deposit_liability' => "Rental Deposit Liability - Rental Invoice {$number}",
            'rental_damage_revenue' => "Rental Damage Revenue - Rental Invoice {$number}",
            'rental_late_fee_revenue' => "Rental Late Fee Revenue - Rental Invoice {$number}",
            'rental_other_revenue' => "Rental Other Revenue - Rental Invoice {$number}",
            default => "Rental Invoice {$number}",
        };
    }

    private function lockedContract(mixed $id): RentalContract
    {
        $contractId = $this->requiredUuid($id, 'rental_contract_id');

        /** @var RentalContract $contract */
        $contract = RentalContract::query()
            ->with(['customer', 'branch', 'lines.rentableItem'])
            ->whereKey($contractId)
            ->lockForUpdate()
            ->firstOrFail();

        return $contract;
    }

    private function lockedInvoice(string $id): RentalInvoice
    {
        /** @var RentalInvoice $invoice */
        $invoice = RentalInvoice::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

        return $invoice;
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
            throw ValidationException::withMessages(['invoice_date' => [__('No open financial period covers date :date.', ['date' => $date])]]);
        }

        return $period;
    }

    private function calculateLineTotalMinor(int $quantityE6, int $unitAmountMinor, int $lineNo): int
    {
        if ($unitAmountMinor > 0 && $quantityE6 > intdiv(PHP_INT_MAX, $unitAmountMinor)) {
            throw ValidationException::withMessages(["lines.{$lineNo}.line_total_minor" => [__('Line amount exceeds maximum allowable integer limit.')]]);
        }

        $product = $quantityE6 * $unitAmountMinor;
        if ($product % 1000000 !== 0) {
            throw ValidationException::withMessages(["lines.{$lineNo}.line_total_minor" => [__('Line amount results in fractional minor currency units.')]]);
        }

        return intdiv($product, 1000000);
    }

    private function defaultDescription(string $lineType, ?RentalContractLine $contractLine, ?RentalReturnLine $returnLine): string
    {
        $itemCode = $contractLine?->rentableItem?->code ?? $returnLine?->rentableItem?->code;

        return match ($lineType) {
            'rent' => 'Rental charge'.($itemCode ? " - {$itemCode}" : ''),
            'deposit' => 'Refundable rental deposit'.($itemCode ? " - {$itemCode}" : ''),
            'damage_charge' => 'Rental damage charge'.($itemCode ? " - {$itemCode}" : ''),
            'late_fee' => 'Rental late fee'.($itemCode ? " - {$itemCode}" : ''),
            'other_charge' => 'Rental other charge'.($itemCode ? " - {$itemCode}" : ''),
            default => 'Rental invoice line',
        };
    }

    private function number(string $key, string $prefix, mixed $date): string
    {
        $year = Carbon::parse($date)->format('Y');
        $sequence = $this->numberAllocator->nextValue($key);

        return $prefix.'-'.$year.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function requiredUuid(mixed $value, string $field): string
    {
        $id = (string) ($value ?? '');
        if (! Str::isUuid($id)) {
            throw ValidationException::withMessages([$field => [__('A valid identifier is required.')]]);
        }

        return $id;
    }

    private function nullableUuid(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->requiredUuid($value, $field);
    }

    private function requiredDate(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([$field => [__('Date is required.')]]);
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => [__('Invalid date.')]]);
        }
    }

    private function nullableDate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => [__('Invalid date.')]]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    private function relations(): array
    {
        return [
            'contract.customer',
            'contract.branch',
            'customer',
            'branch',
            'financialPeriod.fiscalYear',
            'journalEntry',
            'receivableEntry',
            'lines.contractLine.rentableItem',
            'lines.rentalReturn',
            'lines.rentalReturnLine.rentableItem',
            'lines.taxCode',
        ];
    }
}
