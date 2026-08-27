<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'expense';

    protected $fillable = [
        'number',
        'expense_date',
        'due_date',
        'branch_id',
        'supplier_id',
        'payee_name',
        'settlement_method',
        'cash_account_id',
        'bank_account_id',
        'fiscal_year_id',
        'financial_period_id',
        'currency',
        'fx_rate_e6',
        'subtotal_minor',
        'tax_amount_minor',
        'total_minor',
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
            'expense_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fx_rate_e6' => 'integer',
            'subtotal_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_minor' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'expense_id')->orderBy('line_no');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
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
}
