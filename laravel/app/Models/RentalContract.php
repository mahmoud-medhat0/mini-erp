<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalContract extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_contract';

    protected $fillable = [
        'number',
        'customer_id',
        'branch_id',
        'status',
        'contract_date',
        'start_date',
        'expected_end_date',
        'actual_end_date',
        'currency',
        'billing_cycle',
        'estimated_rent_minor',
        'deposit_minor',
        'total_estimated_minor',
        'reference',
        'notes',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'activated_by',
        'activated_at',
        'cancelled_by',
        'cancelled_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'contract_date' => 'date:Y-m-d',
            'start_date' => 'date:Y-m-d',
            'expected_end_date' => 'date:Y-m-d',
            'actual_end_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_rent_minor' => 'integer',
            'deposit_minor' => 'integer',
            'total_estimated_minor' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'submitted_by' => 'integer',
            'approved_by' => 'integer',
            'activated_by' => 'integer',
            'cancelled_by' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RentalContractLine::class, 'rental_contract_id')->orderBy('line_no');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(RentalHandover::class, 'rental_contract_id')->latest('handover_date');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(RentalReturn::class, 'rental_contract_id')->latest('return_date');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(RentalInvoice::class, 'rental_contract_id')->latest('invoice_date');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(RentalContractStatusEvent::class, 'rental_contract_id')->latest('at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
