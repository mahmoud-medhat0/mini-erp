<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalContractStatusEvent extends Model
{
    use HasUuids;

    protected $table = 'rental_contract_status_event';

    protected $fillable = [
        'rental_contract_id',
        'from_status',
        'to_status',
        'event_type',
        'reason',
        'actor_id',
        'at',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'rental_contract_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
