<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalInvoiceLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rental_invoice_line';

    protected $fillable = [
        'rental_invoice_id',
        'line_no',
        'line_type',
        'rental_contract_line_id',
        'rental_return_id',
        'rental_return_line_id',
        'description',
        'quantity_e6',
        'unit_amount_minor',
        'line_total_minor',
        'tax_code_id',
        'tax_rate_bps',
        'tax_amount_minor',
        'gross_amount_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_e6' => 'integer',
            'unit_amount_minor' => 'integer',
            'line_total_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_amount_minor' => 'integer',
            'gross_amount_minor' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(RentalInvoice::class, 'rental_invoice_id');
    }

    public function contractLine(): BelongsTo
    {
        return $this->belongsTo(RentalContractLine::class, 'rental_contract_line_id');
    }

    public function rentalReturn(): BelongsTo
    {
        return $this->belongsTo(RentalReturn::class, 'rental_return_id');
    }

    public function rentalReturnLine(): BelongsTo
    {
        return $this->belongsTo(RentalReturnLine::class, 'rental_return_line_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }
}
