<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sales_order_line';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sales_order_id',
        'line_no',
        'product_id',
        'unit_of_measure_id',
        'description',
        'quantity_e6',
        'unit_price_minor',
        'line_total_minor',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'quantity_e6' => 'integer',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
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
