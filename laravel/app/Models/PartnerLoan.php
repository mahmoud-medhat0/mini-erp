<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerLoan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'partner_loan';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'currency',
        'principal_minor',
        'remaining_balance_minor',
        'disbursement_date',
        'disbursement_method',
        'cash_account_id',
        'bank_account_id',
        'status',
        'journal_entry_id',
        'notes',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'principal_minor' => 'integer',
        'remaining_balance_minor' => 'integer',
        'disbursement_date' => 'date:Y-m-d',
        'lock_version' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
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

    public function repayments(): HasMany
    {
        return $this->hasMany(PartnerLoanRepayment::class, 'partner_loan_id')->orderBy('repayment_date');
    }
}
