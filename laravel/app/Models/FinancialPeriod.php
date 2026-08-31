<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPeriod extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'financial_period';

    protected $appends = [
        'name',
        'period_number',
    ];

    protected $fillable = [
        'fiscal_year_id',
        'month',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'close_note',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function getNameAttribute(): string
    {
        $year = $this->relationLoaded('fiscalYear') ? $this->fiscalYear?->year : null;
        $prefix = $year ? "{$year} - " : '';

        return "{$prefix}Month {$this->month}";
    }

    public function getPeriodNumberAttribute(): int
    {
        return (int) $this->month;
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'financial_period_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'financial_period_id');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'financial_period_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'reopened'], true);
    }

    /**
     * @param  Builder<FinancialPeriod>  $query
     * @return Builder<FinancialPeriod>
     */
    public function scopeOpenForPosting(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'reopened']);
    }
}
