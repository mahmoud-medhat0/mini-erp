<?php

namespace App\Application\Accounting;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CurrencyPageData
{
    /**
     * @return array{currencies: EloquentCollection<int, Currency>}
     */
    public function indexData(): array
    {
        return [
            'currencies' => Currency::query()
                ->with([
                    'accounts' => fn ($query) => $query->select('id', 'code', 'name', 'type', 'nature', 'currency')->orderBy('code'),
                    'exchangeRates' => fn ($query) => $query->select('id', 'currency', 'date', 'rate_e6')->orderBy('date', 'desc'),
                ])
                ->withCount(['accounts', 'journalEntries', 'exchangeRates'])
                ->orderBy('code')
                ->get(),
        ];
    }
}
