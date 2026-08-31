<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableEntrySettlement extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'receivable_entry_settlement';

    protected $fillable = [
        'customer_id',
        'source_receivable_entry_id',
        'target_receivable_entry_id',
        'currency',
        'amount_minor',
        'status',
        'settled_at',
        'reversed_at',
        'reason',
        'reversed_reason',
        'created_by',
        'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'settled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sourceReceivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'source_receivable_entry_id');
    }

    public function targetReceivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'target_receivable_entry_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
