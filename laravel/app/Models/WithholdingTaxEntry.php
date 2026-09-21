<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithholdingTaxEntry extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'withholding_tax_entry';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'tax_code_id',
        'supplier_id',
        'reference',
        'entry_date',
        'financial_period_id',
        'currency',
        'base_amount_minor',
        'rate_bps',
        'withheld_amount_minor',
        'status',
        'journal_entry_id',
        'notes',
        'lock_version',
        'created_by',
        'updated_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'base_amount_minor' => 'integer',
        'rate_bps' => 'integer',
        'withheld_amount_minor' => 'integer',
        'lock_version' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
