<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property array|string $name
 * @property string $tax_type
 * @property string $calculation_mode
 * @property string $recoverability_mode
 * @property bool $is_system
 * @property bool $is_active
 */
class TaxCode extends Model
{
    use HasUuids;

    protected $table = 'tax_codes';

    protected $fillable = [
        'code',
        'name',
        'tax_type',
        'calculation_mode',
        'recoverability_mode',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'json',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_code_id');
    }
}
