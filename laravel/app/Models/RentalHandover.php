<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalHandover extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_handover';

    protected $fillable = [
        'number',
        'rental_contract_id',
        'customer_id',
        'branch_id',
        'status',
        'handover_date',
        'notes',
        'created_by',
        'updated_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'confirmed_by' => 'integer',
            'cancelled_by' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'rental_contract_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RentalHandoverLine::class, 'rental_handover_id');
    }
}
