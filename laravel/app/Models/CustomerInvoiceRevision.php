<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerInvoiceRevision extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'customer_invoice_revision';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revision_date' => 'date:Y-m-d',
            'fx_rate_e6' => 'integer',
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function customerCreditNote(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditNote::class, 'customer_credit_note_id');
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerInvoiceRevisionLine::class, 'customer_invoice_revision_id')->orderBy('line_no', 'asc');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
