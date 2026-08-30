<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableAllocation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payable_allocation';

    protected $fillable = [
        'supplier_id',
        'supplier_payment_id',
        'payable_entry_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->payment();
    }

    public function payableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'payable_entry_id');
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
