<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'expense_line';

    protected $fillable = [
        'expense_id',
        'line_no',
        'expense_category_id',
        'expense_account_id',
        'project_id',
        'cost_center_id',
        'description',
        'quantity_e6',
        'unit_amount_minor',
        'line_total_minor',
        'tax_code_id',
        'tax_rate_bps',
        'tax_amount_minor',
        'gross_amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'quantity_e6' => 'integer',
            'unit_amount_minor' => 'integer',
            'line_total_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_amount_minor' => 'integer',
            'gross_amount_minor' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }
}
