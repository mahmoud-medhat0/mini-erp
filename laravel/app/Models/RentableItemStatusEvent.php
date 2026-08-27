<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentableItemStatusEvent extends Model
{
    use HasUuids;

    protected $table = 'rentable_item_status_event';

    protected $fillable = [
        'rentable_item_id',
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

    public function rentableItem(): BelongsTo
    {
        return $this->belongsTo(RentableItem::class, 'rentable_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
