<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalInvoice extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_invoice';

    protected $fillable = [
        'number',
        'rental_contract_id',
        'customer_id',
        'branch_id',
        'fiscal_year_id',
        'financial_period_id',
        'invoice_type',
        'status',
        'invoice_date',
        'due_date',
        'billing_period_start',
        'billing_period_end',
        'currency',
        'fx_rate_e6',
        'subtotal_minor',
        'tax_amount_minor',
        'total_minor',
        'reference',
        'notes',
        'journal_entry_id',
        'receivable_entry_id',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'billing_period_start' => 'date:Y-m-d',
            'billing_period_end' => 'date:Y-m-d',
            'fx_rate_e6' => 'integer',
            'subtotal_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_minor' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'submitted_by' => 'integer',
            'approved_by' => 'integer',
            'posted_by' => 'integer',
            'cancelled_by' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'rental_contract_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function receivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'receivable_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RentalInvoiceLine::class, 'rental_invoice_id')->orderBy('line_no');
    }
}
