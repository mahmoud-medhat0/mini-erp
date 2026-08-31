<?php

namespace App\Application\Accounting;

use App\Application\Support\BaseCurrencyResolver;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ExchangeRatePageData
{
    public function __construct(private readonly BaseCurrencyResolver $baseCurrencyResolver) {}

    /**
     * @return array{
     *     rates: LengthAwarePaginator,
     *     currencies: EloquentCollection<int, Currency>,
     *     baseCurrency: string,
     *     baseCurrencyRef: Currency|null
     * }
     */
    public function indexData(): array
    {
        $baseCurrency = $this->baseCurrencyResolver->resolve();

        return [
            'rates' => ExchangeRate::query()->with('currencyRef')->orderBy('date', 'desc')->paginate(30),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'baseCurrency' => $baseCurrency,
            'baseCurrencyRef' => Currency::query()->where('code', $baseCurrency)->first(),
        ];
    }
}
