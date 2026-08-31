<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccrualSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'accrual_schedule';

    protected $fillable = [
        'number',
        'schedule_date',
        'start_date',
        'months',
        'branch_id',
        'expense_category_id',
        'expense_account_id',
        'accrued_liability_account_id',
        'fiscal_year_id',
        'financial_period_id',
        'currency',
        'fx_rate_e6',
        'total_minor',
        'accrued_minor',
        'status',
        'reference',
        'description',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date:Y-m-d',
            'start_date' => 'date:Y-m-d',
            'months' => 'integer',
            'fx_rate_e6' => 'integer',
            'total_minor' => 'integer',
            'accrued_minor' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccrualEntry::class, 'accrual_schedule_id')->orderBy('accrual_date');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function accruedLiabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accrued_liability_account_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
