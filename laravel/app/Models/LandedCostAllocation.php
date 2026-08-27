<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandedCostAllocation extends Model
{
    use HasUuids;

    protected $table = 'landed_cost_allocation';

    protected $fillable = [
        'number',
        'goods_receipt_id',
        'supplier_id',
        'fiscal_year_id',
        'financial_period_id',
        'allocation_date',
        'due_date',
        'currency',
        'fx_rate_e6',
        'allocation_method',
        'cost_amount_minor',
        'tax_amount_minor',
        'total_amount_minor',
        'status',
        'reference',
        'description',
        'journal_entry_id',
        'payable_entry_id',
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
            'allocation_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'fx_rate_e6' => 'integer',
            'cost_amount_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function payableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'payable_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LandedCostAllocationLine::class, 'landed_cost_allocation_id')->orderBy('line_no', 'asc');
    }
}
