<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency', 'code');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'currency', 'code');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'currency', 'code');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'currency', 'code');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'currency', 'code');
    }

    public function openingBalances(): HasMany
    {
        return $this->hasMany(OpeningBalance::class, 'currency', 'code');
    }
}
