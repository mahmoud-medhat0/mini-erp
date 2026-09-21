<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEmployeeLoanInstallment extends Model
{
    use HasUuids;

    protected $table = 'payroll_employee_loan_installment';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payroll_employee_loan_id',
        'payroll_run_id',
        'payroll_run_line_id',
        'amount_minor',
        'applied_date',
        'note',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'applied_date' => 'date:Y-m-d',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(PayrollEmployeeLoan::class, 'payroll_employee_loan_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
