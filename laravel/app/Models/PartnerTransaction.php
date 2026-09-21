<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'partner_transaction';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'partner_id',
        'transaction_type',
        'transaction_date',
        'financial_period_id',
        'currency',
        'amount_minor',
        'status',
        'journal_entry_id',
        'reference',
        'notes',
        'lock_version',
        'created_by',
        'updated_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'transaction_date' => 'date:Y-m-d',
        'amount_minor' => 'integer',
        'lock_version' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
