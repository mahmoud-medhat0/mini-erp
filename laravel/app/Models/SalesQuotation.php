<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesQuotation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sales_quotation';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'customer_id',
        'quotation_date',
        'valid_until',
        'currency',
        'fx_rate_e6',
        'status',
        'reference',
        'notes',
        'subtotal_minor',
        'total_minor',
        'converted_sales_order_id',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    protected $casts = [
        'quotation_date' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'fx_rate_e6' => 'integer',
        'subtotal_minor' => 'integer',
        'total_minor' => 'integer',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function convertedSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_sales_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesQuotationLine::class, 'sales_quotation_id')->orderBy('line_no');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
