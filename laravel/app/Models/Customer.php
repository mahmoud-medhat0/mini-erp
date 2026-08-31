<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Customer extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'customer';

    protected $fillable = [
        'code',
        'name',
        'status',
        'email',
        'phone',
        'address',
        'tax_number',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }

    public function receivableAllocations(): HasMany
    {
        return $this->hasMany(ReceivableAllocation::class, 'customer_id');
    }

    public function incomingCheques(): HasMany
    {
        return $this->hasMany(IncomingCheque::class, 'customer_id');
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class, 'customer_id');
    }

    public function rentalInvoices(): HasMany
    {
        return $this->hasMany(RentalInvoice::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
