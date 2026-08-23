<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'purchase_return_line';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6' => 'integer',
            'unit_cost_minor' => 'integer',
            'line_total_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_amount_minor' => 'integer',
            'gross_amount_minor' => 'integer',
        ];
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function supplierBillLine(): BelongsTo
    {
        return $this->belongsTo(SupplierBillLine::class, 'supplier_bill_line_id');
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
