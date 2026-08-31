<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferLine extends Model
{
    use HasUuids;

    protected $table = 'stock_transfer_line';

    protected $fillable = [
        'stock_transfer_id',
        'line_no',
        'product_id',
        'unit_of_measure_id',
        'quantity_e6',
        'issued_quantity_e6',
        'received_quantity_e6',
        'issued_value_minor',
        'source_movement_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6' => 'integer',
            'issued_quantity_e6' => 'integer',
            'received_quantity_e6' => 'integer',
            'issued_value_minor' => 'integer',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function sourceMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovementLedger::class, 'source_movement_id');
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(StockTransferReceiptLine::class, 'stock_transfer_line_id');
    }
}
