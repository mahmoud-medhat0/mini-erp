<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetMovement extends Model
{
    use HasUuids;

    protected $table = 'fixed_asset_movement';

    public $timestamps = false;

    protected $fillable = [
        'number',
        'fixed_asset_id',
        'movement_date',
        'from_branch_id',
        'to_branch_id',
        'from_location_id',
        'to_location_id',
        'from_snapshot_json',
        'to_snapshot_json',
        'reason',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date:Y-m-d',
            'from_snapshot_json' => 'array',
            'to_snapshot_json' => 'array',
            'created_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(FixedAssetLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(FixedAssetLocation::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
