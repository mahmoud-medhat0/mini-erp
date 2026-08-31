<?php

namespace App\Application\Reports;

use App\Models\CustomerInvoice;
use App\Models\ReceivableEntry;
use Illuminate\Support\Collection;

class CustomerInvoiceReportService
{
    public function generate(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $status = null,
        ?string $customerId = null,
        ?string $productId = null,
        ?string $currency = null,
        ?string $search = null
    ): array {
        $query = CustomerInvoice::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure', 'journalEntry'])
            ->orderBy('invoice_date', 'desc')
            ->orderBy('number', 'desc');

        if ($dateFrom) {
            $query->where('invoice_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('invoice_date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        if ($currency) {
            $query->where('currency', $currency);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }
        if ($productId) {
            $query->whereHas('lines', function ($lq) use ($productId): void {
                $lq->where('product_id', $productId);
            });
        }

        $invoices = $query->get();

        // Prefetch receivable entries mapped by source_id
        $invoiceIds = $invoices->pluck('id')->filter()->all();
        $receivableEntries = ReceivableEntry::query()
            ->where('source_type', 'customer_invoice')
            ->whereIn('source_id', $invoiceIds)
            ->get()
            ->keyBy('source_id');

        $rows = new Collection;
        $totalInvoicesCount = $invoices->count();
        $totalAmountMinor = 0;

        foreach ($invoices as $inv) {
            $totalAmountMinor += (int) $inv->total_minor;
            $receivable = $receivableEntries->get($inv->id);

            $rows->push([
                'id' => $inv->id,
                'invoice_number' => $inv->number ?? '—',
                'customer_name' => $inv->customer?->name ?? '—',
                'customer_code' => $inv->customer?->code ?? '—',
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'status' => $inv->status,
                'currency' => $inv->currency,
                'total_minor' => (int) $inv->total_minor,
                'journal_entry_id' => $inv->journal_entry_id,
                'journal_entry_number' => $inv->journalEntry?->number ?? null,
                'receivable_entry_id' => $receivable?->id ?? null,
                'lines_count' => $inv->lines->count(),
            ]);
        }

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_invoices_count' => $totalInvoicesCount,
                'total_amount_minor' => $totalAmountMinor,
            ],
        ];
    }
}
