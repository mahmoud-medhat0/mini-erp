<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bank_reconciliation_line';

    protected $fillable = [
        'bank_reconciliation_id',
        'line_no',
        'statement_date',
        'reference',
        'description',
        'debit_minor',
        'credit_minor',
        'matched_ledger_entry_id',
        'matched_at',
        'matched_by',
        'status',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'lock_version' => 'integer',
            'statement_date' => 'date',
            'matched_at' => 'datetime',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function matchedLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'matched_ledger_entry_id');
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
