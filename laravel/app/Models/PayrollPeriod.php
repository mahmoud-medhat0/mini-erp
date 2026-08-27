<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payroll_period';

    protected $fillable = [
        'year',
        'month',
        'start_date',
        'end_date',
        'payment_date',
        'financial_period_id',
        'status',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'lock_version' => 'integer',
        ];
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'payroll_period_id');
    }
}
