<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Budget extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'budget';

    protected $fillable = [
        'fiscal_year_id',
        'code',
        'version_code',
        'name',
        'description',
        'status',
        'default_currency',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'activated_by',
        'activated_at',
        'archived_by',
        'archived_at',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'archived_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency', 'code');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
