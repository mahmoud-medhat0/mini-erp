<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = true;

    protected $table = 'exchange_rate';

    protected $fillable = [
        'currency',
        'date',
        'rate_e6',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'rate_e6' => 'integer',
        ];
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
