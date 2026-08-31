<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalHandoverLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_handover_line';

    protected $fillable = [
        'rental_handover_id',
        'rental_contract_line_id',
        'rentable_item_id',
        'condition_out',
        'accessories_out',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accessories_out' => 'array',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(RentalHandover::class, 'rental_handover_id');
    }

    public function contractLine(): BelongsTo
    {
        return $this->belongsTo(RentalContractLine::class, 'rental_contract_line_id');
    }

    public function rentableItem(): BelongsTo
    {
        return $this->belongsTo(RentableItem::class, 'rentable_item_id');
    }
}
