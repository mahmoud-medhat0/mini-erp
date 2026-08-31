<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDepreciationSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fixed_asset_depreciation_schedule';

    protected $fillable = [
        'fixed_asset_id',
        'period_number',
        'financial_period_id',
        'period_start_date',
        'period_end_date',
        'depreciation_minor',
        'accumulated_depreciation_minor',
        'net_book_value_minor',
        'status',
        'depreciation_run_id',
        'journal_entry_id',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'period_start_date' => 'date:Y-m-d',
            'period_end_date' => 'date:Y-m-d',
            'depreciation_minor' => 'integer',
            'accumulated_depreciation_minor' => 'integer',
            'net_book_value_minor' => 'integer',
            'posted_at' => 'datetime',
            'posted_by' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function depreciationRun(): BelongsTo
    {
        return $this->belongsTo(FixedAssetDepreciationRun::class, 'depreciation_run_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
