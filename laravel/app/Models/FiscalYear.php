<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'fiscal_year';

    protected $appends = [
        'name',
    ];

    protected $fillable = [
        'year',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function getNameAttribute(): string
    {
        return 'FY '.$this->year;
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FinancialPeriod::class, 'fiscal_year_id')->orderBy('month');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * @param  Builder<FiscalYear>  $query
     * @return Builder<FiscalYear>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class, 'fiscal_year_id');
    }
}
