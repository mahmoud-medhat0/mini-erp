<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAdjustmentNoteLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'supplier_adjustment_note_line';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6' => 'integer',
            'unit_cost_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function supplierAdjustmentNote(): BelongsTo
    {
        return $this->belongsTo(SupplierAdjustmentNote::class, 'supplier_adjustment_note_id');
    }

    public function supplierBillLine(): BelongsTo
    {
        return $this->belongsTo(SupplierBillLine::class, 'supplier_bill_line_id');
    }

    public function purchaseReturnLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturnLine::class, 'purchase_return_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
