<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransfer extends Model
{
    use HasUuids;

    protected $table = 'treasury_transfer';

    protected $fillable = [
        'number',
        'transfer_date',
        'source_type',
        'source_cash_account_id',
        'source_bank_account_id',
        'destination_type',
        'destination_cash_account_id',
        'destination_bank_account_id',
        'source_branch_id',
        'destination_branch_id',
        'currency',
        'amount_minor',
        'fx_rate_e6',
        'status',
        'reference',
        'description',
        'fiscal_year_id',
        'financial_period_id',
        'journal_entry_id',
        'created_by',
        'updated_by',
        'posted_by',
        'posted_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date:Y-m-d',
            'amount_minor' => 'integer',
            'fx_rate_e6' => 'integer',
            'posted_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function sourceCashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'source_cash_account_id');
    }

    public function sourceBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'source_bank_account_id');
    }

    public function destinationCashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'destination_cash_account_id');
    }

    public function destinationBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_bank_account_id');
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
