<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferReceiptLine extends Model
{
    use HasUuids;

    protected $table = 'stock_transfer_receipt_line';

    protected $fillable = [
        'stock_transfer_receipt_id',
        'stock_transfer_line_id',
        'quantity_e6',
        'value_minor',
        'destination_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_e6' => 'integer',
            'value_minor' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockTransferReceipt::class, 'stock_transfer_receipt_id');
    }

    public function transferLine(): BelongsTo
    {
        return $this->belongsTo(StockTransferLine::class, 'stock_transfer_line_id');
    }

    public function destinationMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovementLedger::class, 'destination_movement_id');
    }
}
