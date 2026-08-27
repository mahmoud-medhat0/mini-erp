<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'ledger_entry';

    protected $fillable = [
        'journal_entry_id',
        'journal_line_id',
        'account_id',
        'financial_period_id',
        'branch_id',
        'entry_date',
        'debit_minor',
        'credit_minor',
        'currency',
        'fx_rate_e6',
        'debit_txn_minor',
        'credit_txn_minor',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'fx_rate_e6' => 'integer',
            'debit_txn_minor' => 'integer',
            'credit_txn_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
