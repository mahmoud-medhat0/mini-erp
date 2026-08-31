<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalReturnLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_return_line';

    protected $fillable = [
        'rental_return_id',
        'rental_contract_line_id',
        'rentable_item_id',
        'condition_in',
        'outcome',
        'estimated_damage_charge_minor',
        'accessories_in',
        'inspection_notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_damage_charge_minor' => 'integer',
            'accessories_in' => 'array',
        ];
    }

    public function rentalReturn(): BelongsTo
    {
        return $this->belongsTo(RentalReturn::class, 'rental_return_id');
    }

    public function contractLine(): BelongsTo
    {
        return $this->belongsTo(RentalContractLine::class, 'rental_contract_line_id');
    }

    public function rentableItem(): BelongsTo
    {
        return $this->belongsTo(RentableItem::class, 'rentable_item_id');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(RentalInvoiceLine::class, 'rental_return_line_id');
    }
}
