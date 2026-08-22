<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sales_return_line';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function deliveryNoteLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteLine::class, 'delivery_note_line_id');
    }

    public function customerInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoiceLine::class, 'customer_invoice_line_id');
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
