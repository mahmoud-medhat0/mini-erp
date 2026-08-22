<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasUuids;

    protected $table = 'stock_balance';

    protected $fillable = [
        'product_id',
        'unit_of_measure_id',
        'currency',
        'quantity_e6',
        'valuation_amount_minor',
        'avg_unit_cost_e6',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'quantity_e6' => 'integer',
            'valuation_amount_minor' => 'integer',
            'avg_unit_cost_e6' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
