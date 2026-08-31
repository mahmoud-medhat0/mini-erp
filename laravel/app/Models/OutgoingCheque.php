<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingCheque extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'outgoing_cheque';

    protected $fillable = [
        'number',
        'supplier_id',
        'bank_account_id',
        'cheque_number',
        'payee_name',
        'due_date',
        'issued_fiscal_year_id',
        'issued_financial_period_id',
        'issued_date',
        'cleared_fiscal_year_id',
        'cleared_financial_period_id',
        'cleared_date',
        'returned_fiscal_year_id',
        'returned_financial_period_id',
        'returned_date',
        'cancelled_fiscal_year_id',
        'cancelled_financial_period_id',
        'cancelled_date',
        'currency',
        'amount_minor',
        'fx_rate_e6',
        'status',
        'issue_journal_entry_id',
        'clear_journal_entry_id',
        'return_journal_entry_id',
        'cancel_journal_entry_id',
        'payable_entry_id',
        'return_payable_entry_id',
        'cancel_payable_entry_id',
        'reference',
        'description',
        'created_by',
        'updated_by',
        'issued_by',
        'cleared_by',
        'returned_by',
        'cancelled_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'fx_rate_e6' => 'integer',
            'lock_version' => 'integer',
            'due_date' => 'date:Y-m-d',
            'issued_date' => 'date',
            'cleared_date' => 'date',
            'returned_date' => 'date',
            'cancelled_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function issueJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'issue_journal_entry_id');
    }

    public function clearJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'clear_journal_entry_id');
    }

    public function returnJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'return_journal_entry_id');
    }

    public function cancelJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cancel_journal_entry_id');
    }

    public function payableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'payable_entry_id');
    }

    public function returnPayableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'return_payable_entry_id');
    }

    public function cancelPayableEntry(): BelongsTo
    {
        return $this->belongsTo(PayableEntry::class, 'cancel_payable_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
