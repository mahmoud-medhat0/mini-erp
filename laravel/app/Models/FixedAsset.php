<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class FixedAsset extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'fixed_asset';

    protected $fillable = [
        'asset_number',
        'name',
        'description',
        'fixed_asset_category_id',
        'currency',
        'acquisition_date',
        'in_service_date',
        'cost_minor',
        'salvage_value_minor',
        'useful_life_months',
        'depreciation_method',
        'opening_accumulated_depreciation_minor',
        'status',
        'capitalization_mode',
        'capitalization_date',
        'journal_entry_id',
        'capitalized_at',
        'capitalized_by',
        'serial_number',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date:Y-m-d',
            'in_service_date' => 'date:Y-m-d',
            'capitalization_date' => 'date:Y-m-d',
            'capitalized_at' => 'datetime',
            'cost_minor' => 'integer',
            'salvage_value_minor' => 'integer',
            'useful_life_months' => 'integer',
            'opening_accumulated_depreciation_minor' => 'integer',
            'lock_version' => 'integer',
            'capitalized_by' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'fixed_asset_category_id');
    }

    public function currencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function depreciationSchedules(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationSchedule::class, 'fixed_asset_id')->orderBy('period_number');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(FixedAssetDisposal::class, 'fixed_asset_id')->latest('created_at');
    }

    public function capitalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capitalized_by');
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
