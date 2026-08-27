<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferReceipt extends Model
{
    use HasUuids;

    protected $table = 'stock_transfer_receipt';

    protected $fillable = [
        'stock_transfer_id',
        'receipt_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date:Y-m-d',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferReceiptLine::class, 'stock_transfer_receipt_id');
    }
}
