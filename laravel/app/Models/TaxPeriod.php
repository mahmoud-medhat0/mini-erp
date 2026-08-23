<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaxPeriod extends Model
{
    use HasUuids;

    protected $table = 'tax_periods';

    protected $fillable = [
        'period_label',
        'start_date',
        'end_date',
        'status',
        'filed_at',
        'filed_by',
        'file_reference',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'filed_at' => 'datetime',
    ];

    public function returns(): HasMany
    {
        return $this->hasMany(TaxReturn::class, 'tax_period_id');
    }

    public function latestReturn(): HasOne
    {
        return $this->hasOne(TaxReturn::class, 'tax_period_id')->latestOfMany();
    }

    public function filedReturn(): HasOne
    {
        return $this->hasOne(TaxReturn::class, 'tax_period_id')->where('status', 'filed');
    }
}
