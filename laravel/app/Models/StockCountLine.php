<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    use HasUuids;

    protected $table = 'stock_count_line';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'expected_quantity_e6' => 'integer',
            'counted_quantity_e6' => 'integer',
            'variance_quantity_e6' => 'integer',
            'unit_cost_minor' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
