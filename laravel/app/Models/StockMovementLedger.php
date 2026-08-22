<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementLedger extends Model
{
    use HasUuids;

    protected $table = 'stock_movement_ledger';

    protected $fillable = [
        'movement_date',
        'source_type',
        'source_id',
        'source_line_id',
        'movement_type',
        'product_id',
        'unit_of_measure_id',
        'currency',
        'quantity_delta_e6',
        'value_delta_minor',
        'unit_cost_e6',
        'balance_quantity_e6',
        'balance_valuation_amount_minor',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date:Y-m-d',
            'quantity_delta_e6' => 'integer',
            'value_delta_minor' => 'integer',
            'unit_cost_e6' => 'integer',
            'balance_quantity_e6' => 'integer',
            'balance_valuation_amount_minor' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
