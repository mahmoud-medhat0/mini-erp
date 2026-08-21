<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingCheque extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'incoming_cheque';

    protected $fillable = [
        'number',
        'customer_id',
        'cheque_number',
        'drawer_bank_name',
        'received_fiscal_year_id',
        'received_financial_period_id',
        'received_date',
        'deposited_date',
        'deposit_bank_account_id',
        'cleared_fiscal_year_id',
        'cleared_financial_period_id',
        'cleared_date',
        'returned_fiscal_year_id',
        'returned_financial_period_id',
        'returned_date',
        'bounced_fiscal_year_id',
        'bounced_financial_period_id',
        'bounced_date',
        'currency',
        'amount_minor',
        'fx_rate_e6',
        'status',
        'receive_journal_entry_id',
        'clear_journal_entry_id',
        'return_journal_entry_id',
        'bounce_journal_entry_id',
        'receivable_entry_id',
        'return_receivable_entry_id',
        'bounce_receivable_entry_id',
        'reference',
        'description',
        'created_by',
        'updated_by',
        'received_by',
        'deposited_by',
        'cleared_by',
        'returned_by',
        'bounced_by',
        'cancelled_by',
        'cancelled_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'fx_rate_e6' => 'integer',
            'lock_version' => 'integer',
            'received_date' => 'date',
            'deposited_date' => 'date',
            'cleared_date' => 'date',
            'returned_date' => 'date',
            'bounced_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function depositBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'deposit_bank_account_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function receiveJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'receive_journal_entry_id');
    }

    public function clearJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'clear_journal_entry_id');
    }

    public function returnJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'return_journal_entry_id');
    }

    public function bounceJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'bounce_journal_entry_id');
    }

    public function receivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'receivable_entry_id');
    }

    public function returnReceivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'return_receivable_entry_id');
    }

    public function bounceReceivableEntry(): BelongsTo
    {
        return $this->belongsTo(ReceivableEntry::class, 'bounce_receivable_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
