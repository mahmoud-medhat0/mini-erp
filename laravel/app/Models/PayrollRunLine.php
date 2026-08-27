<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRunLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payroll_run_line';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'line_no',
        'branch_id',
        'currency',
        'base_salary_minor',
        'earnings_minor',
        'deductions_minor',
        'gross_minor',
        'net_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'base_salary_minor' => 'integer',
            'earnings_minor' => 'integer',
            'deductions_minor' => 'integer',
            'gross_minor' => 'integer',
            'net_minor' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PayrollRunLineComponent::class, 'payroll_run_line_id');
    }
}
