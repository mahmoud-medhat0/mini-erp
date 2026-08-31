<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostAllocationLine extends Model
{
    use HasUuids;

    protected $table = 'landed_cost_allocation_line';

    protected $fillable = [
        'landed_cost_allocation_id',
        'goods_receipt_line_id',
        'line_no',
        'product_id',
        'unit_of_measure_id',
        'quantity_e6_snapshot',
        'receipt_value_minor_snapshot',
        'allocated_cost_minor',
        'capitalized_amount_minor',
        'expensed_amount_minor',
        'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6_snapshot' => 'integer',
            'receipt_value_minor_snapshot' => 'integer',
            'allocated_cost_minor' => 'integer',
            'capitalized_amount_minor' => 'integer',
            'expensed_amount_minor' => 'integer',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(LandedCostAllocation::class, 'landed_cost_allocation_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovementLedger::class, 'stock_movement_id');
    }
}
