<?php

namespace App\Application\Inventory;

use App\Models\Currency;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class InventoryPageOptions
{
    public function activeWarehouses(): Collection
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->with('branch')
            ->orderBy('code')
            ->get();
    }

    public function stockProducts(): Collection
    {
        return Product::query()
            ->with('unitOfMeasure')
            ->where('status', 'active')
            ->where('type', 'stock')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'unit_of_measure_id']);
    }

    public function currencies(): Collection
    {
        return Currency::query()
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);
    }
}
