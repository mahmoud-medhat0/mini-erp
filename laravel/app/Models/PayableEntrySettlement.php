<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableEntrySettlement extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payable_entry_settlement';

    protected $fillable = [
        'supplier_id',
        'source_payable_entry_id',
        'target_payable_entry_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function sourcePayableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'source_payable_entry_id');
    }

    public function targetPayableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'target_payable_entry_id');
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
