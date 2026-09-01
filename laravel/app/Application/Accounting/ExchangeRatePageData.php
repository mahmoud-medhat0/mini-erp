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
     *     baseCurrencyRef: Currency|null,
     *     filters: array{search: string|null},
     *     activeCurrencyCount: int
     * }
     */
    public function indexData(?string $search = null): array
    {
        $baseCurrency = $this->baseCurrencyResolver->resolve();
        $search = trim((string) $search);
        $query = ExchangeRate::query()
            ->with('currencyRef')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('currency', 'like', "%{$search}%")
                        ->orWhereHas('currencyRef', fn ($currency) => $currency
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name->en', 'like', "%{$search}%")
                            ->orWhere('name->ar', 'like', "%{$search}%"));

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) === 1) {
                        $nested->orWhereDate('date', $search);
                    }
                });
            });

        return [
            'rates' => (clone $query)->orderBy('date', 'desc')->paginate(30)->withQueryString(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'baseCurrency' => $baseCurrency,
            'baseCurrencyRef' => Currency::query()->where('code', $baseCurrency)->first(),
            'filters' => ['search' => $search !== '' ? $search : null],
            'activeCurrencyCount' => (clone $query)->distinct()->count('currency'),
        ];
    }
}
