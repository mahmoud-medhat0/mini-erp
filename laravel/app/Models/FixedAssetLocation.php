<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class FixedAssetLocation extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'fixed_asset_location';

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'is_active',
        'lock_version',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'fixed_asset_location_id');
    }

    public function inboundMovements(): HasMany
    {
        return $this->hasMany(FixedAssetMovement::class, 'to_location_id');
    }

    public function outboundMovements(): HasMany
    {
        return $this->hasMany(FixedAssetMovement::class, 'from_location_id');
    }
}
