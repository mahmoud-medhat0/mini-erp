<?php

namespace App\Application\Reports;

use App\Models\PayableEntry;
use App\Models\SupplierBill;
use Illuminate\Support\Collection;

class SupplierBillReportService
{
    public function generate(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $status = null,
        ?string $supplierId = null,
        ?string $productId = null,
        ?string $currency = null,
        ?string $search = null
    ): array {
        $query = SupplierBill::query()
            ->with(['supplier', 'lines.product', 'lines.unitOfMeasure', 'journalEntry'])
            ->orderBy('bill_date', 'desc')
            ->orderBy('number', 'desc');

        if ($dateFrom) {
            $query->where('bill_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('bill_date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($currency) {
            $query->where('currency', $currency);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }
        if ($productId) {
            $query->whereHas('lines', function ($lq) use ($productId): void {
                $lq->where('product_id', $productId);
            });
        }

        $bills = $query->get();

        // Prefetch payable entries mapped by source_id
        $billIds = $bills->pluck('id')->filter()->all();
        $payableEntries = PayableEntry::query()
            ->where('source_type', 'supplier_bill')
            ->whereIn('source_id', $billIds)
            ->get()
            ->keyBy('source_id');

        $rows = new Collection;
        $totalBillsCount = $bills->count();
        $totalAmountMinor = 0;

        foreach ($bills as $bill) {
            $totalAmountMinor += (int) $bill->total_minor;
            $payable = $payableEntries->get($bill->id);

            $rows->push([
                'id' => $bill->id,
                'bill_number' => $bill->number ?? '—',
                'supplier_name' => $bill->supplier?->name ?? '—',
                'supplier_code' => $bill->supplier?->code ?? '—',
                'bill_date' => $bill->bill_date,
                'due_date' => $bill->due_date,
                'status' => $bill->status,
                'currency' => $bill->currency,
                'total_minor' => (int) $bill->total_minor,
                'journal_entry_id' => $bill->journal_entry_id,
                'journal_entry_number' => $bill->journalEntry?->number ?? null,
                'payable_entry_id' => $payable?->id ?? null,
                'lines_count' => $bill->lines->count(),
            ]);
        }

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_bills_count' => $totalBillsCount,
                'total_amount_minor' => $totalAmountMinor,
            ],
        ];
    }
}
