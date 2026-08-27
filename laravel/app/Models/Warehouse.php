<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Warehouse extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'warehouse';

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'warehouse_type',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StockLocation::class, 'warehouse_id');
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'warehouse_id');
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class, 'warehouse_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'warehouse_id');
    }

    public function rentableItems(): HasMany
    {
        return $this->hasMany(RentableItem::class, 'warehouse_id');
    }
}
