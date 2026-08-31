<?php

namespace App\Application\Reports;

use App\Models\StockMovementLedger;
use Illuminate\Support\Collection;

class StockMovementReportService
{
    public function generate(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $movementType = null,
        ?string $productId = null,
        ?string $warehouseId = null,
        ?string $currency = null,
        ?string $search = null
    ): array {
        $query = StockMovementLedger::query()
            ->with(['warehouse.branch', 'product', 'unitOfMeasure', 'journalEntry'])
            ->orderBy('movement_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($dateFrom) {
            $query->where('movement_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('movement_date', '<=', $dateTo);
        }
        if ($movementType) {
            $query->where('movement_type', $movementType);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($currency) {
            $query->where('currency', $currency);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('source_type', 'like', "%{$search}%")
                    ->orWhere('source_id', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search): void {
                        $pq->where('name->en', 'like', "%{$search}%")
                            ->orWhere('name->ar', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $movements = $query->get();

        $rows = new Collection;
        $totalMovementsCount = $movements->count();
        $totalQuantityDeltaE6 = 0;
        $totalValueDeltaMinor = 0;

        foreach ($movements as $m) {
            $totalQuantityDeltaE6 += (int) $m->quantity_delta_e6;
            $totalValueDeltaMinor += (int) $m->value_delta_minor;

            $rows->push([
                'id' => $m->id,
                'movement_date' => $m->movement_date,
                'movement_type' => $m->movement_type,
                'warehouse_id' => $m->warehouse_id,
                'warehouse_code' => $m->warehouse?->code ?? null,
                'warehouse_name' => $m->warehouse?->name ?? null,
                'branch_id' => $m->warehouse?->branch_id,
                'branch_code' => $m->warehouse?->branch?->code ?? null,
                'branch_name' => $m->warehouse?->branch?->name ?? null,
                'source_type' => $m->source_type,
                'source_id' => $m->source_id,
                'source_line_id' => $m->source_line_id,
                'product_id' => $m->product_id,
                'product_name' => $m->product?->name ?? '—',
                'product_code' => $m->product?->code ?? '—',
                'uom_code' => $m->unitOfMeasure?->code ?? '—',
                'currency' => $m->currency,
                'quantity_delta_e6' => (int) $m->quantity_delta_e6,
                'value_delta_minor' => (int) $m->value_delta_minor,
                'unit_cost_e6' => (int) $m->unit_cost_e6,
                'balance_quantity_e6' => (int) $m->balance_quantity_e6,
                'balance_valuation_amount_minor' => (int) $m->balance_valuation_amount_minor,
                'journal_entry_id' => $m->journal_entry_id,
                'journal_entry_number' => $m->journalEntry?->number ?? null,
            ]);
        }

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_movements_count' => $totalMovementsCount,
                'total_quantity_delta_e6' => $totalQuantityDeltaE6,
                'total_value_delta_minor' => $totalValueDeltaMinor,
            ],
        ];
    }
}
