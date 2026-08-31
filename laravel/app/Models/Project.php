<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Project extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'project';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'is_billable',
        'is_active',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'project_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'project_id');
    }

    public function expenseLines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'project_id');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'project_id');
    }
}
