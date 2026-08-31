<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalReturn extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_return';

    protected $fillable = [
        'number',
        'rental_contract_id',
        'customer_id',
        'branch_id',
        'status',
        'return_date',
        'notes',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'submitted_by' => 'integer',
            'completed_by' => 'integer',
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
        return $this->hasMany(RentalReturnLine::class, 'rental_return_id');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(RentalInvoiceLine::class, 'rental_return_id');
    }
}
