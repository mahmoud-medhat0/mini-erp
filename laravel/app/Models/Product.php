<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'product';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'unit_of_measure_id',
        'product_category_id',
        'status',
        'is_sales_enabled',
        'is_purchase_enabled',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'is_sales_enabled' => 'boolean',
            'is_purchase_enabled' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rentableItems(): HasMany
    {
        return $this->hasMany(RentableItem::class, 'product_id');
    }
}
