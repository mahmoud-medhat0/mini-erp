<?php

namespace App\Application\Reports;

use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\PurchaseReturn;
use App\Models\RentalInvoice;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use Carbon\Carbon;

class VatRegisterReportService
{
    public function generate(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $toDate = $filters['to_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        $type = $filters['type'] ?? 'all'; // 'all', 'output', 'input'
        $taxCodeId = $filters['tax_code_id'] ?? null;

        $rows = [];

        $totalOutputSubtotal = 0;
        $totalOutputTax = 0;
        $totalOutputGross = 0;

        $totalInputSubtotal = 0;
        $totalInputTax = 0;
        $totalInputGross = 0;

        // 1. Output VAT - Customer Invoices (+)
        if ($type === 'all' || $type === 'output') {
            $invoices = CustomerInvoice::query()
                ->with(['customer', 'lines.taxCode'])
                ->where('status', 'posted')
                ->whereBetween('invoice_date', [$fromDate, $toDate])
                ->get();

            foreach ($invoices as $inv) {
                foreach ($inv->lines as $line) {
                    if (! $line->tax_code_id) {
                        continue;
                    }
                    if ($taxCodeId && $line->tax_code_id !== $taxCodeId) {
                        continue;
                    }

                    $subtotal = (int) $line->line_total_minor;
                    $tax = (int) $line->tax_amount_minor;
                    $gross = (int) ($line->gross_amount_minor ?: ($subtotal + $tax));

                    $rows[] = [
                        'document_type' => 'customer_invoice',
                        'document_id' => (string) $inv->id,
                        'document_number' => $inv->number ?? 'DRAFT',
                        'document_date' => Carbon::parse($inv->invoice_date)->format('Y-m-d'),
                        'entity_type' => 'customer',
                        'entity_name' => $inv->customer?->name ?? '—',
                        'tax_category' => 'output',
                        'tax_code_id' => (string) $line->tax_code_id,
                        'tax_code' => $line->taxCode?->code ?? '—',
                        'tax_rate_bps' => (int) $line->tax_rate_bps,
                        'subtotal_minor' => $subtotal,
                        'tax_amount_minor' => $tax,
                        'gross_amount_minor' => $gross,
                    ];

                    $totalOutputSubtotal += $subtotal;
                    $totalOutputTax += $tax;
                    $totalOutputGross += $gross;
                }
            }

            // 2. Output VAT - Customer Credit Notes (-)
            $creditNotes = CustomerCreditNote::query()
                ->with(['customer', 'lines.taxCode'])
                ->where('status', 'posted')
                ->whereBetween('credit_date', [$fromDate, $toDate])
                ->get();

            foreach ($creditNotes as $cn) {
                foreach ($cn->lines as $line) {
                    if (! $line->tax_code_id) {
                        continue;
                    }
                    if ($taxCodeId && $line->tax_code_id !== $taxCodeId) {
                        continue;
                    }

                    $subtotal = -abs((int) $line->line_subtotal_minor);
                    $tax = -abs((int) $line->tax_minor);
                    $gross = -abs((int) ($line->line_total_minor ?: (abs($subtotal) + abs($tax))));

                    $rows[] = [
                        'document_type' => 'customer_credit_note',
                        'document_id' => (string) $cn->id,
                        'document_number' => $cn->number ?? 'DRAFT',
                        'document_date' => Carbon::parse($cn->credit_date)->format('Y-m-d'),
                        'entity_type' => 'customer',
                        'entity_name' => $cn->customer?->name ?? '—',
                        'tax_category' => 'output',
                        'tax_code_id' => (string) $line->tax_code_id,
                        'tax_code' => $line->taxCode?->code ?? '—',
                        'tax_rate_bps' => (int) $line->tax_rate_bps,
                        'subtotal_minor' => $subtotal,
                        'tax_amount_minor' => $tax,
                        'gross_amount_minor' => $gross,
                    ];

                    $totalOutputSubtotal += $subtotal;
                    $totalOutputTax += $tax;
                    $totalOutputGross += $gross;
                }
            }

            // 3. Output VAT - Sales Returns: intentionally NOT a register source.
            //
            // SalesReturnService::post() never posts to AR or any tax account in
            // any disposition (restock_original_cost, restock_manual_value,
            // scrap_no_restock) - it only ever posts inventory movements (and,
            // for restock_manual_value with a variance, an inventory-return-
            // variance/COGS journal). line->tax_amount_minor and
            // line->stock_value_minor on a sales return line are values copied
            // from the originating invoice line purely for display; neither one
            // ever corresponds to a real ledger_entry, in any scenario.
            //
            // The only document that actually reverses AR/output tax for a
            // return is its linked CustomerCreditNote, which conditionally
            // posts to `output_tax_payable` exactly when tax_minor > 0
            // (CustomerCreditNoteService::post()) - already captured by the
            // credit-note block above, independent of tax_mode or whether
            // sales_return_id is set. Including the return's copied figures
            // here as well double-counts a single real reversal as two
            // register rows, understating total_output_tax_minor relative to
            // the true `output_tax_payable` GL balance by the return's share
            // whenever a linked credit note also posts tax. Keeping this
            // register limited to documents that actually move the ledger
            // guarantees it reconciles against the GL in every scenario -
            // with a credit note or without one, tax_mode 'none' or not,
            // sales_return_id linked or not.

            // 4. Output VAT - Rental Invoices (+)
            $rentalInvoices = RentalInvoice::query()
                ->with(['customer', 'lines.taxCode'])
                ->where('status', 'posted')
                ->whereBetween('invoice_date', [$fromDate, $toDate])
                ->get();

            foreach ($rentalInvoices as $invoice) {
                foreach ($invoice->lines as $line) {
                    if (! $line->tax_code_id) {
                        continue;
                    }
                    if ($taxCodeId && $line->tax_code_id !== $taxCodeId) {
                        continue;
                    }

                    $subtotal = (int) $line->line_total_minor;
                    $tax = (int) $line->tax_amount_minor;
                    $gross = (int) ($line->gross_amount_minor ?: ($subtotal + $tax));

                    $rows[] = [
                        'document_type' => 'rental_invoice',
                        'document_id' => (string) $invoice->id,
                        'document_number' => $invoice->number ?? 'DRAFT',
                        'document_date' => Carbon::parse($invoice->invoice_date)->format('Y-m-d'),
                        'entity_type' => 'customer',
                        'entity_name' => $invoice->customer?->name ?? '—',
                        'tax_category' => 'output',
                        'tax_code_id' => (string) $line->tax_code_id,
                        'tax_code' => $line->taxCode?->code ?? '—',
                        'tax_rate_bps' => (int) $line->tax_rate_bps,
                        'subtotal_minor' => $subtotal,
                        'tax_amount_minor' => $tax,
                        'gross_amount_minor' => $gross,
                    ];

                    $totalOutputSubtotal += $subtotal;
                    $totalOutputTax += $tax;
                    $totalOutputGross += $gross;
                }
            }
        }

        // 5. Input VAT - Supplier Bills (+)
        if ($type === 'all' || $type === 'input') {
            $supplierBills = SupplierBill::query()
                ->with(['supplier', 'lines.taxCode'])
                ->where('status', 'posted')
                ->whereBetween('bill_date', [$fromDate, $toDate])
                ->get();

            foreach ($supplierBills as $bill) {
                foreach ($bill->lines as $line) {
                    if (! $line->tax_code_id) {
                        continue;
                    }
                    if ($taxCodeId && $line->tax_code_id !== $taxCodeId) {
                        continue;
                    }

                    $subtotal = (int) $line->line_total_minor;
                    $tax = (int) $line->tax_amount_minor;
                    $gross = (int) ($line->gross_amount_minor ?: ($subtotal + $tax));

                    $rows[] = [
                        'document_type' => 'supplier_bill',
                        'document_id' => (string) $bill->id,
                        'document_number' => $bill->number ?? 'DRAFT',
                        'document_date' => Carbon::parse($bill->bill_date)->format('Y-m-d'),
                        'entity_type' => 'supplier',
                        'entity_name' => $bill->supplier?->name ?? '—',
                        'tax_category' => 'input',
                        'tax_code_id' => (string) $line->tax_code_id,
                        'tax_code' => $line->taxCode?->code ?? '—',
                        'tax_rate_bps' => (int) $line->tax_rate_bps,
                        'subtotal_minor' => $subtotal,
                        'tax_amount_minor' => $tax,
                        'gross_amount_minor' => $gross,
                    ];

                    $totalInputSubtotal += $subtotal;
                    $totalInputTax += $tax;
                    $totalInputGross += $gross;
                }
            }

            // 5. Input VAT - Supplier Adjustment Notes (+ or - based on direction)
            $adjustmentNotes = SupplierAdjustmentNote::query()
                ->with(['supplier', 'lines.taxCode'])
                ->where('status', 'posted')
                ->whereBetween('adjustment_date', [$fromDate, $toDate])
                ->get();

            foreach ($adjustmentNotes as $note) {
                $isDecrease = $note->direction === 'decrease_payable';

                foreach ($note->lines as $line) {
                    if (! $line->tax_code_id) {
                        continue;
                    }
                    if ($taxCodeId && $line->tax_code_id !== $taxCodeId) {
                        continue;
                    }

                    $rawSubtotal = (int) $line->line_subtotal_minor;
                    $rawTax = (int) ($line->tax_amount_minor ?: $line->tax_minor);
                    $rawGross = (int) ($line->gross_amount_minor ?: ($rawSubtotal + $rawTax));

                    $subtotal = $isDecrease ? -abs($rawSubtotal) : abs($rawSubtotal);
                    $tax = $isDecrease ? -abs($rawTax) : abs($rawTax);
                    $gross = $isDecrease ? -abs($rawGross) : abs($rawGross);

                    $rows[] = [
                        'document_type' => 'supplier_adjustment_note',
                        'document_id' => (string) $note->id,
                        'document_number' => $note->number ?? 'DRAFT',
                        'document_date' => Carbon::parse($note->adjustment_date)->format('Y-m-d'),
                        'entity_type' => 'supplier',
                        'entity_name' => $note->supplier?->name ?? '—',
                        'tax_category' => 'input',
                        'tax_code_id' => (string) $line->tax_code_id,
                        'tax_code' => $line->taxCode?->code ?? '—',
                        'tax_rate_bps' => (int) $line->tax_rate_bps,
                        'subtotal_minor' => $subtotal,
                        'tax_amount_minor' => $tax,
                        'gross_amount_minor' => $gross,
                    ];

                    $totalInputSubtotal += $subtotal;
                    $totalInputTax += $tax;
                    $totalInputGross += $gross;
                }
            }

            // 6. Input VAT - Purchase Returns (-)
            $purchaseReturns = PurchaseReturn::query()
                ->with(['supplier', 'lines.taxCode'])
                ->where('status', 'posted')
                ->whereBetween('return_date', [$fromDate, $toDate])
                ->get();

            foreach ($purchaseReturns as $pr) {
                foreach ($pr->lines as $line) {
                    if (! $line->tax_code_id) {
                        continue;
                    }
                    if ($taxCodeId && $line->tax_code_id !== $taxCodeId) {
                        continue;
                    }

                    $subtotal = -abs((int) $line->original_receipt_cost_minor);
                    $tax = -abs((int) $line->tax_amount_minor);
                    $gross = -abs((int) ($line->gross_amount_minor ?: (abs($subtotal) + abs($tax))));

                    $rows[] = [
                        'document_type' => 'purchase_return',
                        'document_id' => (string) $pr->id,
                        'document_number' => $pr->number ?? 'DRAFT',
                        'document_date' => Carbon::parse($pr->return_date)->format('Y-m-d'),
                        'entity_type' => 'supplier',
                        'entity_name' => $pr->supplier?->name ?? '—',
                        'tax_category' => 'input',
                        'tax_code_id' => (string) $line->tax_code_id,
                        'tax_code' => $line->taxCode?->code ?? '—',
                        'tax_rate_bps' => (int) $line->tax_rate_bps,
                        'subtotal_minor' => $subtotal,
                        'tax_amount_minor' => $tax,
                        'gross_amount_minor' => $gross,
                    ];

                    $totalInputSubtotal += $subtotal;
                    $totalInputTax += $tax;
                    $totalInputGross += $gross;
                }
            }
        }

        // Sort rows by document_date asc, document_number asc
        usort($rows, function ($a, $b) {
            $cmp = strcmp($a['document_date'], $b['document_date']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['document_number'], $b['document_number']);
        });

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => $type,
            'tax_code_id' => $taxCodeId,
            'rows' => $rows,
            'summary' => [
                'total_output_subtotal_minor' => $totalOutputSubtotal,
                'total_output_tax_minor' => $totalOutputTax,
                'total_output_gross_minor' => $totalOutputGross,
                'total_input_subtotal_minor' => $totalInputSubtotal,
                'total_input_tax_minor' => $totalInputTax,
                'total_input_gross_minor' => $totalInputGross,
                'net_vat_payable_minor' => $totalOutputTax - $totalInputTax,
            ],
        ];
    }
}
