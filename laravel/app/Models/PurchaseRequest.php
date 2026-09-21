<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'purchase_request';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'supplier_id',
        'requested_date',
        'needed_by_date',
        'currency',
        'status',
        'reference',
        'notes',
        'subtotal_minor',
        'total_minor',
        'converted_purchase_order_id',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    protected $casts = [
        'requested_date' => 'date:Y-m-d',
        'needed_by_date' => 'date:Y-m-d',
        'subtotal_minor' => 'integer',
        'total_minor' => 'integer',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function convertedPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class, 'purchase_request_id')->orderBy('line_no');
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
