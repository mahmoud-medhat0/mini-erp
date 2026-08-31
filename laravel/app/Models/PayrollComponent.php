<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class PayrollComponent extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'payroll_component';

    protected $fillable = [
        'code',
        'name',
        'type',
        'calculation_type',
        'default_amount_minor',
        'rate_bps',
        'expense_account_id',
        'liability_account_id',
        'sort_order',
        'is_system',
        'is_active',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'default_amount_minor' => 'integer',
            'rate_bps' => 'integer',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class, 'payroll_component_id');
    }
}
