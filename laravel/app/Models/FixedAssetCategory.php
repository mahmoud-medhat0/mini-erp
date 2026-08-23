<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class FixedAssetCategory extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'fixed_asset_category';

    protected $fillable = [
        'code',
        'name',
        'useful_life_months',
        'salvage_value_minor',
        'is_active',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'useful_life_months' => 'integer',
            'salvage_value_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'fixed_asset_category_id');
    }
}
