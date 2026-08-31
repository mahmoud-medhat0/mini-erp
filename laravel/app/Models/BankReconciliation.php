<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bank_reconciliation';

    protected $fillable = [
        'bank_account_id',
        'financial_period_id',
        'statement_reference',
        'date_from',
        'date_to',
        'currency',
        'statement_opening_balance_minor',
        'statement_closing_balance_minor',
        'system_opening_balance_minor',
        'system_movement_minor',
        'system_closing_balance_minor',
        'statement_movement_minor',
        'matched_system_movement_minor',
        'difference_minor',
        'status',
        'reconciled_at',
        'created_by',
        'updated_by',
        'reconciled_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'statement_opening_balance_minor' => 'integer',
            'statement_closing_balance_minor' => 'integer',
            'system_opening_balance_minor' => 'integer',
            'system_movement_minor' => 'integer',
            'system_closing_balance_minor' => 'integer',
            'statement_movement_minor' => 'integer',
            'matched_system_movement_minor' => 'integer',
            'difference_minor' => 'integer',
            'lock_version' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class, 'bank_reconciliation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
