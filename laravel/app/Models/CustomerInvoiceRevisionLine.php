<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceRevisionLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'customer_invoice_revision_line';

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

    public function customerInvoiceRevision(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoiceRevision::class, 'customer_invoice_revision_id');
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
