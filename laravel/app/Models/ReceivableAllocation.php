<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableAllocation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'receivable_allocation';

    protected $fillable = [
        'customer_id',
        'customer_receipt_id',
        'receivable_entry_id',
        'currency',
        'amount_minor',
        'status',
        'allocated_at',
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
            'allocated_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id');
    }

    public function receivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'receivable_entry_id');
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
