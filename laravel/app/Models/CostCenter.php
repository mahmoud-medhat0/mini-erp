<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CostCenter extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'cost_center';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'is_active',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
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
        return $this->hasMany(JournalLine::class, 'cost_center_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'cost_center_id');
    }

    public function expenseLines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'cost_center_id');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'cost_center_id');
    }
}
