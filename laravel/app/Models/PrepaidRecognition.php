<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepaidRecognition extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'prepaid_recognition';

    protected $fillable = [
        'prepaid_schedule_id',
        'financial_period_id',
        'recognition_date',
        'amount_minor',
        'status',
        'journal_entry_id',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'recognition_date' => 'date:Y-m-d',
            'amount_minor' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PrepaidSchedule::class, 'prepaid_schedule_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
