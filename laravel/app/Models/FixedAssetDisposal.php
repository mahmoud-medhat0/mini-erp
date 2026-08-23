<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDisposal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fixed_asset_disposal';

    protected $fillable = [
        'id',
        'number',
        'fixed_asset_id',
        'disposal_date',
        'financial_period_id',
        'disposal_type',
        'proceeds_minor',
        'net_book_value_minor',
        'gain_minor',
        'loss_minor',
        'status',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'posted_at',
        'posted_by',
        'lock_version',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'proceeds_minor' => 'integer',
        'net_book_value_minor' => 'integer',
        'gain_minor' => 'integer',
        'loss_minor' => 'integer',
        'posted_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
