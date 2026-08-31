<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class StockLocation extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'stock_location';

    protected $fillable = [
        'warehouse_id',
        'code',
        'name',
        'location_type',
        'is_active',
        'created_by',
        'updated_by',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
