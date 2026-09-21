<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLoanRepayment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'partner_loan_repayment';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'partner_loan_id',
        'repayment_date',
        'amount_minor',
        'repayment_method',
        'cash_account_id',
        'bank_account_id',
        'journal_entry_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'repayment_date' => 'date:Y-m-d',
        'amount_minor' => 'integer',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(PartnerLoan::class, 'partner_loan_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
