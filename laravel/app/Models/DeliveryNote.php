<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNote extends Model
{
    use HasUuids;

    protected $table = 'delivery_note';

    protected $fillable = [
        'number',
        'sales_order_id',
        'warehouse_id',
        'delivery_date',
        'status',
        'reference',
        'notes',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class, 'delivery_note_id')->orderBy('line_no', 'asc');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
