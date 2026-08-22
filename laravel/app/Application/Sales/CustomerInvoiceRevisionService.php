<?php

namespace App\Application\Sales;

use App\Domain\Audit\AuditLogger;
use App\Models\CustomerCreditNote;
use App\Models\CustomerCreditNoteLine;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceRevision;
use App\Models\CustomerInvoiceRevisionLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerInvoiceRevisionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function generate(
        string $customerInvoiceId,
        ?string $customerCreditNoteId,
        ?string $salesReturnId,
        int $actorId,
    ): CustomerInvoiceRevision {
        return DB::transaction(function () use ($customerInvoiceId, $customerCreditNoteId, $salesReturnId, $actorId): CustomerInvoiceRevision {
            if ($customerCreditNoteId && ! CustomerCreditNote::query()->where('id', $customerCreditNoteId)->exists()) {
                throw ValidationException::withMessages(['customer_credit_note_id' => ['Customer Credit Note does not exist.']]);
            }

            if ($salesReturnId && ! SalesReturn::query()->where('id', $salesReturnId)->exists()) {
                throw ValidationException::withMessages(['sales_return_id' => ['Sales Return does not exist.']]);
            }

            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::query()->with(['lines'])->where('id', $customerInvoiceId)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'posted') {
                throw ValidationException::withMessages(['status' => ['Revisions can only be generated for posted customer invoices.']]);
            }

            if ($invoice->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['Customer invoice has no lines to revise.']]);
            }

            $maxNo = (int) CustomerInvoiceRevision::query()->where('customer_invoice_id', $invoice->id)->max('revision_no');
            $revisionNo = $maxNo + 1;
            $displayString = $invoice->number.'-R'.str_pad((string) $revisionNo, 2, '0', STR_PAD_LEFT);
            $revisionDate = today()->toDateString();
            $currency = $invoice->currency;

            $invoiceLineIds = $invoice->lines->pluck('id')->all();

            $originalSubtotalMinor = 0;
            $creditedSubtotalMinor = 0;
            $netSubtotalMinor = 0;
            $originalTaxMinor = 0;
            $creditedTaxMinor = 0;
            $netTaxMinor = 0;
            $originalTotalMinor = 0;
            $creditedTotalMinor = 0;
            $netTotalMinor = 0;

            $computedLines = [];

            foreach ($invoice->lines as $index => $line) {
                $salesReturnLines = SalesReturnLine::query()
                    ->where('customer_invoice_line_id', $line->id)
                    ->whereHas('salesReturn', fn ($q) => $q->where('status', 'posted'))
                    ->get(['id', 'quantity_e6']);

                $creditNoteLines = CustomerCreditNoteLine::query()
                    ->where('customer_invoice_line_id', $line->id)
                    ->whereHas('customerCreditNote', fn ($q) => $q->where('status', 'posted'))
                    ->get(['id', 'quantity_e6', 'line_subtotal_minor', 'tax_minor']);

                $returnedFromSalesReturnsE6 = (int) $salesReturnLines->sum('quantity_e6');
                $returnedFromCreditNotesE6 = (int) $creditNoteLines->sum('quantity_e6');
                $cumulativeReturnedE6 = $returnedFromSalesReturnsE6 + $returnedFromCreditNotesE6;

                $originalQuantityE6 = (int) $line->quantity_e6;
                $netQuantityE6 = max(0, $originalQuantityE6 - $cumulativeReturnedE6);

                $originalSubtotalForLine = (int) $line->line_total_minor;
                $creditedSubtotalForLine = min($originalSubtotalForLine, (int) $creditNoteLines->sum('line_subtotal_minor'));
                $creditedTaxForLine = (int) $creditNoteLines->sum('tax_minor');
                $creditedTotalForLine = $creditedSubtotalForLine + $creditedTaxForLine;

                $netSubtotalForLine = max(0, $originalSubtotalForLine - $creditedSubtotalForLine);
                $netTaxForLine = max(0, -$creditedTaxForLine);
                $netTotalForLine = max(0, $originalSubtotalForLine - $creditedTotalForLine);

                $sourceSummary = [
                    'sales_return_line_ids' => $salesReturnLines->pluck('id')->all(),
                    'customer_credit_note_line_ids' => $creditNoteLines->pluck('id')->all(),
                ];

                $computedLines[] = [
                    'customer_invoice_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'unit_of_measure_id' => $line->unit_of_measure_id,
                    'description' => $line->description,
                    'original_quantity_e6' => $originalQuantityE6,
                    'returned_quantity_e6' => $cumulativeReturnedE6,
                    'net_quantity_e6' => $netQuantityE6,
                    'unit_price_minor' => (int) $line->unit_price_minor,
                    'original_subtotal_minor' => $originalSubtotalForLine,
                    'credited_subtotal_minor' => $creditedSubtotalForLine,
                    'net_subtotal_minor' => $netSubtotalForLine,
                    'original_tax_minor' => 0,
                    'credited_tax_minor' => $creditedTaxForLine,
                    'net_tax_minor' => $netTaxForLine,
                    'original_total_minor' => $originalSubtotalForLine,
                    'credited_total_minor' => $creditedTotalForLine,
                    'net_total_minor' => $netTotalForLine,
                    'source_summary_json' => json_encode($sourceSummary),
                ];

                $originalSubtotalMinor += $originalSubtotalForLine;
                $creditedSubtotalMinor += $creditedSubtotalForLine;
                $netSubtotalMinor += $netSubtotalForLine;
                $originalTaxMinor += 0;
                $creditedTaxMinor += $creditedTaxForLine;
                $netTaxMinor += $netTaxForLine;
                $originalTotalMinor += $originalSubtotalForLine;
                $creditedTotalMinor += $creditedTotalForLine;
                $netTotalMinor += $netTotalForLine;
            }

            $creditNoteNumbers = CustomerCreditNote::query()
                ->where('status', 'posted')
                ->where(function ($q) use ($invoice, $invoiceLineIds) {
                    $q->where('customer_invoice_id', $invoice->id)
                        ->orWhereHas('lines', fn ($l) => $l->whereIn('customer_invoice_line_id', $invoiceLineIds));
                })
                ->pluck('number')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $salesReturnNumbers = SalesReturn::query()
                ->where('status', 'posted')
                ->where(function ($q) use ($invoice, $invoiceLineIds) {
                    $q->where('customer_invoice_id', $invoice->id)
                        ->orWhereHas('lines', fn ($l) => $l->whereIn('customer_invoice_line_id', $invoiceLineIds));
                })
                ->pluck('number')
                ->filter()
                ->unique()
                ->values()
                ->all();

            /** @var CustomerInvoiceRevision $revision */
            $revision = CustomerInvoiceRevision::query()->create([
                'customer_invoice_id' => $invoice->id,
                'customer_credit_note_id' => $customerCreditNoteId,
                'sales_return_id' => $salesReturnId,
                'revision_no' => $revisionNo,
                'display_string' => $displayString,
                'revision_date' => $revisionDate,
                'currency' => $currency,
                'original_subtotal_minor' => $originalSubtotalMinor,
                'credited_subtotal_minor' => $creditedSubtotalMinor,
                'net_subtotal_minor' => $netSubtotalMinor,
                'original_tax_minor' => $originalTaxMinor,
                'credited_tax_minor' => $creditedTaxMinor,
                'net_tax_minor' => $netTaxMinor,
                'original_total_minor' => $originalTotalMinor,
                'credited_total_minor' => $creditedTotalMinor,
                'net_total_minor' => $netTotalMinor,
                'snapshot_json' => json_encode([
                    'invoice_number' => $invoice->number,
                    'credit_note_numbers' => $creditNoteNumbers,
                    'sales_return_numbers' => $salesReturnNumbers,
                    'generated_at' => now()->toISOString(),
                ]),
                'created_by' => $actorId,
            ]);

            foreach ($computedLines as $index => $lineData) {
                CustomerInvoiceRevisionLine::query()->create(array_merge(
                    ['customer_invoice_revision_id' => $revision->id, 'line_no' => $index + 1],
                    $lineData,
                ));
            }

            $revision->load(['customerInvoice', 'customerCreditNote', 'salesReturn', 'lines']);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'customer_invoice_revision.generate',
                entityType: 'customer_invoice_revision',
                entityId: $revision->id,
                before: null,
                after: $revision->toArray(),
            );

            return $revision->fresh(['lines']);
        });
    }
}
