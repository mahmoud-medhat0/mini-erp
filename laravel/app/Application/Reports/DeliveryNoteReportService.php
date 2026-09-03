<?php

namespace App\Application\Reports;

use App\Models\DeliveryNote;
use Illuminate\Support\Collection;

class DeliveryNoteReportService
{
    public function generate(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $status = null,
        ?string $customerId = null,
        ?string $productId = null,
        ?string $warehouseId = null,
        ?string $search = null
    ): array {
        $query = DeliveryNote::query()
            ->with(['salesOrder.customer', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])
            ->orderBy('delivery_date', 'desc')
            ->orderBy('number', 'desc');

        if ($dateFrom) {
            $query->where('delivery_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('delivery_date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($customerId) {
            $query->whereHas('salesOrder', function ($sq) use ($customerId): void {
                $sq->where('customer_id', $customerId);
            });
        }
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', function ($sq) use ($search): void {
                        $sq->where('number', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($cq) use ($search): void {
                                $cq->where('code', 'like', "%{$search}%")
                                    ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', ['%'.mb_strtolower($search).'%']);
                            });
                    });
            });
        }
        if ($productId) {
            $query->whereHas('lines', function ($lq) use ($productId): void {
                $lq->where('product_id', $productId);
            });
        }

        $notes = $query->get();

        $rows = new Collection;
        $totalNotesCount = $notes->count();
        $totalDeliveredQtyE6 = 0;

        foreach ($notes as $dn) {
            $noteQtyE6 = 0;
            foreach ($dn->lines as $line) {
                if (! $productId || $line->product_id === $productId) {
                    $noteQtyE6 += (int) $line->quantity_e6;
                }
            }

            $totalDeliveredQtyE6 += $noteQtyE6;

            $rows->push([
                'id' => $dn->id,
                'delivery_number' => $dn->number ?? '—',
                'sales_order_number' => $dn->salesOrder?->number ?? '—',
                'customer_name' => $dn->salesOrder?->customer?->name ?? '—',
                'customer_code' => $dn->salesOrder?->customer?->code ?? '—',
                'warehouse_id' => $dn->warehouse_id,
                'warehouse_code' => $dn->warehouse?->code ?? '—',
                'warehouse_name' => $dn->warehouse?->name ?? null,
                'delivery_date' => $dn->delivery_date,
                'status' => $dn->status,
                'delivered_quantity_e6' => $noteQtyE6,
                'lines_count' => $dn->lines->count(),
            ]);
        }

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_notes_count' => $totalNotesCount,
                'total_delivered_quantity_e6' => $totalDeliveredQtyE6,
            ],
        ];
    }
}
