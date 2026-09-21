<?php

namespace App\Application\Reports;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Phase 32.3 - Reorder level report (PHASE_25_GAP_CLOSURE_DECISION_PACK.md
 * §11.3, decision 2): a manual "below minimum stock" report rather than a
 * real-time notification, per the decision pack's bounded first slice.
 */
class ReorderLevelReportService
{
    public function generate(): array
    {
        $balances = DB::table('stock_balance')
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(quantity_e6), 0) as total_quantity_e6')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $products = Product::query()
            ->whereNotNull('reorder_level')
            ->where('status', 'active')
            ->with('unitOfMeasure')
            ->orderBy('code')
            ->get();

        $items = [];

        foreach ($products as $product) {
            $onHandQuantityE6 = (int) ($balances->get($product->id)->total_quantity_e6 ?? 0);
            $onHandQuantity = $onHandQuantityE6 / 1_000_000;
            $reorderLevel = (int) $product->reorder_level;

            if ($onHandQuantity >= $reorderLevel) {
                continue;
            }

            $items[] = [
                'id' => $product->id,
                'code' => $product->code,
                'barcode' => $product->barcode,
                'name' => $product->getTranslations('name'),
                'unit_of_measure' => $product->unitOfMeasure?->getTranslations('name'),
                'on_hand_quantity' => $onHandQuantity,
                'reorder_level' => $reorderLevel,
                'shortfall' => $reorderLevel - $onHandQuantity,
            ];
        }

        return [
            'items' => $items,
            'generated_at' => now()->toDateTimeString(),
        ];
    }
}
