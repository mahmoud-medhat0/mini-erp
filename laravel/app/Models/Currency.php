<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Fillable(['code', 'name', 'symbol', 'exponent'])]
#[Translatable('name')]
class Currency extends Model
{
    use HasTranslations;

    protected $table = 'currency';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'exponent' => 'integer',
        ];
    }
}
