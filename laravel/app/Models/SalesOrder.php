<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sales_order';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'customer_id',
        'order_date',
        'expected_delivery_date',
        'currency',
        'fx_rate_e6',
        'status',
        'reference',
        'notes',
        'subtotal_minor',
        'total_minor',
        'submitted_by',
        'submitted_at',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    protected $casts = [
        'order_date' => 'date:Y-m-d',
        'expected_delivery_date' => 'date:Y-m-d',
        'fx_rate_e6' => 'integer',
        'subtotal_minor' => 'integer',
        'total_minor' => 'integer',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id')->orderBy('line_no', 'asc');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
