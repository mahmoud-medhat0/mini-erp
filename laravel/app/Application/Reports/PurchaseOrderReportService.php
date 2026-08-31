<?php

namespace App\Application\Reports;

use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;

class PurchaseOrderReportService
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
        $query = PurchaseOrder::query()
            ->with(['supplier', 'lines.product', 'lines.unitOfMeasure'])
            ->orderBy('order_date', 'desc')
            ->orderBy('number', 'desc');

        if ($dateFrom) {
            $query->where('order_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('order_date', '<=', $dateTo);
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

        $orders = $query->get();

        $rows = new Collection;
        $totalOrdersCount = $orders->count();
        $totalQuantityE6 = 0;
        $totalAmountMinor = 0;

        foreach ($orders as $po) {
            $orderQtyE6 = 0;
            foreach ($po->lines as $line) {
                if (! $productId || $line->product_id === $productId) {
                    $orderQtyE6 += (int) $line->quantity_e6;
                }
            }

            $totalQuantityE6 += $orderQtyE6;
            $totalAmountMinor += (int) $po->total_minor;

            $rows->push([
                'id' => $po->id,
                'order_number' => $po->number ?? '—',
                'supplier_name' => $po->supplier?->name ?? '—',
                'supplier_code' => $po->supplier?->code ?? '—',
                'order_date' => $po->order_date,
                'status' => $po->status,
                'currency' => $po->currency,
                'total_minor' => (int) $po->total_minor,
                'ordered_quantity_e6' => $orderQtyE6,
                'lines_count' => $po->lines->count(),
            ]);
        }

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_orders_count' => $totalOrdersCount,
                'total_quantity_e6' => $totalQuantityE6,
                'total_amount_minor' => $totalAmountMinor,
            ],
        ];
    }
}
