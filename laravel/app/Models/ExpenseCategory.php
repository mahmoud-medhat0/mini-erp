<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ExpenseCategory extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'expense_category';

    protected $fillable = [
        'code',
        'name',
        'default_expense_account_id',
        'default_tax_code_id',
        'requires_attachment',
        'is_active',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_expense_account_id');
    }

    public function defaultTaxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'default_tax_code_id');
    }

    public function expenseLines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'expense_category_id');
    }

    public function prepaidSchedules(): HasMany
    {
        return $this->hasMany(PrepaidSchedule::class, 'expense_category_id');
    }

    public function accrualSchedules(): HasMany
    {
        return $this->hasMany(AccrualSchedule::class, 'expense_category_id');
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
