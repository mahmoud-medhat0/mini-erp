<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Fillable(['id', 'name', 'base_currency', 'settings_json'])]
#[Translatable('name')]
class Company extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'company';

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }
}
