<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tax_code_id
 * @property int $rate_bps
 * @property string $effective_from
 * @property string|null $effective_to
 * @property bool $is_active
 * @property-read TaxCode|null $taxCode
 */
class TaxRate extends Model
{
    use HasUuids;

    protected $table = 'tax_rates';

    protected $fillable = [
        'tax_code_id',
        'rate_bps',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }
}
