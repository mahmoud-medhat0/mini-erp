<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    use HasUuids;

    protected $table = 'purchase_request_line';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'purchase_request_id',
        'line_no',
        'product_id',
        'unit_of_measure_id',
        'description',
        'quantity_e6',
        'estimated_unit_price_minor',
        'line_total_minor',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'quantity_e6' => 'integer',
        'estimated_unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
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
