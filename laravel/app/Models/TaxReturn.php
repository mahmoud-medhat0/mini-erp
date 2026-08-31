<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturn extends Model
{
    use HasUuids;

    protected $table = 'tax_returns';

    protected $fillable = [
        'tax_period_id',
        'number',
        'status',
        'output_tax_minor',
        'input_tax_minor',
        'net_payable_minor',
        'snapshot',
        'generated_at',
        'generated_by',
        'filed_at',
        'filed_by',
    ];

    protected $casts = [
        'output_tax_minor' => 'integer',
        'input_tax_minor' => 'integer',
        'net_payable_minor' => 'integer',
        'snapshot' => 'array',
        'generated_at' => 'datetime',
        'filed_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(TaxPeriod::class, 'tax_period_id');
    }
}
