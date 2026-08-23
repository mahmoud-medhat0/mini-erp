<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAssetDepreciationRun extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fixed_asset_depreciation_run';

    protected $fillable = [
        'number',
        'financial_period_id',
        'run_date',
        'total_depreciation_minor',
        'asset_count',
        'status',
        'journal_entry_id',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date:Y-m-d',
            'total_depreciation_minor' => 'integer',
            'asset_count' => 'integer',
            'posted_at' => 'datetime',
            'posted_by' => 'integer',
        ];
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationSchedule::class, 'depreciation_run_id');
    }
}
