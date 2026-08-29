<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'journal_line';

    protected $fillable = [
        'journal_entry_id',
        'line_no',
        'account_id',
        'branch_id',
        'project_id',
        'cost_center_id',
        'memo',
        'debit_minor',
        'credit_minor',
        'currency',
        'fx_rate_e6',
        'debit_txn_minor',
        'credit_txn_minor',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'fx_rate_e6' => 'integer',
            'debit_txn_minor' => 'integer',
            'credit_txn_minor' => 'integer',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
