<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class RentalContractLine extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'rental_contract_line';

    protected $fillable = [
        'rental_contract_id',
        'line_no',
        'rentable_item_id',
        'description',
        'start_date',
        'end_date',
        'rate_type',
        'rate_minor',
        'estimated_units',
        'estimated_amount_minor',
        'deposit_minor',
        'notes',
    ];

    public array $translatable = ['description'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'rate_minor' => 'integer',
            'estimated_units' => 'integer',
            'estimated_amount_minor' => 'integer',
            'deposit_minor' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'rental_contract_id');
    }

    public function rentableItem(): BelongsTo
    {
        return $this->belongsTo(RentableItem::class, 'rentable_item_id');
    }

    public function handoverLines(): HasMany
    {
        return $this->hasMany(RentalHandoverLine::class, 'rental_contract_line_id');
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(RentalReturnLine::class, 'rental_contract_line_id');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(RentalInvoiceLine::class, 'rental_contract_line_id');
    }
}
