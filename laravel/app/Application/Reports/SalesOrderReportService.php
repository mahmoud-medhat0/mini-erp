<?php

namespace App\Application\Reports;

use App\Models\SalesOrder;
use Illuminate\Support\Collection;

class SalesOrderReportService
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
        $query = SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
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

        $orders = $query->get();

        $rows = new Collection;
        $totalOrdersCount = $orders->count();
        $totalQuantityE6 = 0;
        $totalAmountMinor = 0;

        foreach ($orders as $so) {
            $orderQtyE6 = 0;
            foreach ($so->lines as $line) {
                if (! $productId || $line->product_id === $productId) {
                    $orderQtyE6 += (int) $line->quantity_e6;
                }
            }

            $totalQuantityE6 += $orderQtyE6;
            $totalAmountMinor += (int) $so->total_minor;

            $rows->push([
                'id' => $so->id,
                'order_number' => $so->number ?? '—',
                'customer_name' => $so->customer?->name ?? '—',
                'customer_code' => $so->customer?->code ?? '—',
                'order_date' => $so->order_date,
                'status' => $so->status,
                'currency' => $so->currency,
                'total_minor' => (int) $so->total_minor,
                'ordered_quantity_e6' => $orderQtyE6,
                'lines_count' => $so->lines->count(),
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
