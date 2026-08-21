<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Fillable(['id', 'name', 'base_currency', 'settings_json'])]
#[Translatable('name')]
class Company extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'company';

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    public function baseCurrencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'code');
    }
}
