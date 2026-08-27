<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class PayrollRunLineComponent extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'payroll_run_line_component';

    protected $fillable = [
        'payroll_run_line_id',
        'payroll_component_id',
        'expense_account_id',
        'liability_account_id',
        'code',
        'name',
        'type',
        'amount_minor',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class, 'payroll_run_line_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }
}
