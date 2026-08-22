<?php

namespace App\Application\Reports;

use App\Models\GoodsReceipt;
use Illuminate\Support\Collection;

class GoodsReceiptReportService
{
    public function generate(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $status = null,
        ?string $supplierId = null,
        ?string $productId = null,
        ?string $search = null
    ): array {
        $query = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'lines.product', 'lines.unitOfMeasure'])
            ->orderBy('receipt_date', 'desc')
            ->orderBy('number', 'desc');

        if ($dateFrom) {
            $query->where('receipt_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('receipt_date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($supplierId) {
            $query->whereHas('purchaseOrder', function ($pq) use ($supplierId): void {
                $pq->where('supplier_id', $supplierId);
            });
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', function ($pq) use ($search): void {
                        $pq->where('number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($sq) use ($search): void {
                                $sq->where('name', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%");
                            });
                    });
            });
        }
        if ($productId) {
            $query->whereHas('lines', function ($lq) use ($productId): void {
                $lq->where('product_id', $productId);
            });
        }

        $receipts = $query->get();

        $rows = new Collection;
        $totalReceiptsCount = $receipts->count();
        $totalReceivedQtyE6 = 0;

        foreach ($receipts as $gr) {
            $receiptQtyE6 = 0;
            foreach ($gr->lines as $line) {
                if (! $productId || $line->product_id === $productId) {
                    $receiptQtyE6 += (int) $line->quantity_e6;
                }
            }

            $totalReceivedQtyE6 += $receiptQtyE6;

            $rows->push([
                'id' => $gr->id,
                'receipt_number' => $gr->number ?? '—',
                'purchase_order_number' => $gr->purchaseOrder?->number ?? '—',
                'supplier_name' => $gr->purchaseOrder?->supplier?->name ?? '—',
                'supplier_code' => $gr->purchaseOrder?->supplier?->code ?? '—',
                'receipt_date' => $gr->receipt_date,
                'status' => $gr->status,
                'received_quantity_e6' => $receiptQtyE6,
                'lines_count' => $gr->lines->count(),
            ]);
        }

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_receipts_count' => $totalReceiptsCount,
                'total_received_quantity_e6' => $totalReceivedQtyE6,
            ],
        ];
    }
}
