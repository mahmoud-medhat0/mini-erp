<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollComponent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'employee_payroll_component';

    protected $fillable = [
        'employee_id',
        'payroll_component_id',
        'amount_minor',
        'rate_bps',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'rate_bps' => 'integer',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
