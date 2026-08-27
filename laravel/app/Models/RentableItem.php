<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class RentableItem extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'rentable_item';

    protected $fillable = [
        'code',
        'name',
        'description',
        'item_source',
        'product_id',
        'fixed_asset_id',
        'branch_id',
        'warehouse_id',
        'status',
        'condition_status',
        'currency',
        'serial_number',
        'replacement_value_minor',
        'daily_rate_minor',
        'monthly_rate_minor',
        'deposit_minor',
        'notes',
        'is_active',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'replacement_value_minor' => 'integer',
            'daily_rate_minor' => 'integer',
            'monthly_rate_minor' => 'integer',
            'deposit_minor' => 'integer',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(RentableItemStatusEvent::class, 'rentable_item_id')->latest('at');
    }

    public function rentalContractLines(): HasMany
    {
        return $this->hasMany(RentalContractLine::class, 'rentable_item_id');
    }

    public function rentalHandoverLines(): HasMany
    {
        return $this->hasMany(RentalHandoverLine::class, 'rentable_item_id');
    }

    public function rentalReturnLines(): HasMany
    {
        return $this->hasMany(RentalReturnLine::class, 'rentable_item_id');
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
