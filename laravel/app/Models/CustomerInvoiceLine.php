<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceLine extends Model
{
    use HasUuids;

    protected $table = 'customer_invoice_line';

    protected $fillable = [
        'customer_invoice_id',
        'sales_order_line_id',
        'delivery_note_line_id',
        'line_no',
        'product_id',
        'unit_of_measure_id',
        'description',
        'quantity_e6',
        'unit_price_minor',
        'line_total_minor',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    public function deliveryNoteLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteLine::class, 'delivery_note_line_id');
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
