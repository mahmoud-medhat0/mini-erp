<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Employee extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'employee';

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'status',
        'hire_date',
        'termination_date',
        'currency',
        'base_salary_minor',
        'payment_method',
        'notes',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date:Y-m-d',
            'termination_date' => 'date:Y-m-d',
            'base_salary_minor' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function componentAssignments(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class, 'employee_id');
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class, 'employee_id');
    }
}
