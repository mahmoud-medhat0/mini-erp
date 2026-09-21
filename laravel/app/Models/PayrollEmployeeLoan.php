<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEmployeeLoan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payroll_employee_loan';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'employee_id',
        'loan_type',
        'currency',
        'principal_minor',
        'remaining_balance_minor',
        'installment_amount_minor',
        'disbursement_date',
        'disbursement_method',
        'cash_account_id',
        'bank_account_id',
        'status',
        'journal_entry_id',
        'notes',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'principal_minor' => 'integer',
        'remaining_balance_minor' => 'integer',
        'installment_amount_minor' => 'integer',
        'disbursement_date' => 'date:Y-m-d',
        'lock_version' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PayrollEmployeeLoanInstallment::class, 'payroll_employee_loan_id')->orderBy('applied_date');
    }
}
