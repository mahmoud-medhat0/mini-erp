<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ReceivableEntry extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'receivable_entry';

    protected $fillable = [
        'customer_id',
        'source_type',
        'source_id',
        'journal_entry_id',
        'journal_line_id',
        'financial_period_id',
        'entry_date',
        'due_date',
        'description',
        'currency',
        'debit_minor',
        'credit_minor',
        'debit_txn_minor',
        'credit_txn_minor',
        'fx_rate_e6',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'debit_txn_minor' => 'integer',
            'credit_txn_minor' => 'integer',
            'fx_rate_e6' => 'integer',
        ];
    }

    /**
     * Persist date-only columns as bare Y-m-d strings.
     *
     * The `date:Y-m-d` cast controls how a value is read back, not how it is
     * written. Callers pass a mix of Carbon instances and strings, and on SQLite
     * a Carbon instance serialises to "Y-m-d 00:00:00". That value sorts after a
     * plain "Y-m-d" bound parameter, so `entry_date <= :as_of_date` silently drops
     * every row dated on the boundary day and reports understate balances.
     */
    protected function asDateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    public function setEntryDateAttribute(mixed $value): void
    {
        $this->attributes['entry_date'] = $this->asDateOnly($value);
    }

    public function setDueDateAttribute(mixed $value): void
    {
        $this->attributes['due_date'] = $this->asDateOnly($value);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceivableAllocation::class, 'receivable_entry_id');
    }

    public function sourceSettlements(): HasMany
    {
        return $this->hasMany(ReceivableEntrySettlement::class, 'source_receivable_entry_id');
    }

    public function targetSettlements(): HasMany
    {
        return $this->hasMany(ReceivableEntrySettlement::class, 'target_receivable_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
