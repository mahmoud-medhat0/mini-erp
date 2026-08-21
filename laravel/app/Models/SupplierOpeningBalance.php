<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOpeningBalance extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'supplier_opening_balance';

    protected $fillable = [
        'supplier_id',
        'fiscal_year_id',
        'financial_period_id',
        'entry_date',
        'due_date',
        'reference',
        'description',
        'currency',
        'amount_minor',
        'fx_rate_e6',
        'status',
        'journal_entry_id',
        'payable_entry_id',
        'created_by',
        'updated_by',
        'posted_by',
        'posted_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'amount_minor' => 'integer',
            'fx_rate_e6' => 'integer',
            'posted_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function period(): BelongsTo
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

    public function payableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'payable_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
