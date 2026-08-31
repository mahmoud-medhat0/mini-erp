<?php

namespace App\Application\Support;

use App\Domain\Currency\CurrencyRegistry;
use App\Models\Company;
use App\Models\Currency;

class BaseCurrencyResolver
{
    public function __construct(
        private readonly CurrencyRegistry $currencyRegistry,
    ) {}

    public function resolve(): string
    {
        $baseCurrency = Company::query()->orderBy('created_at')->value('base_currency');
        $baseCurrency = strtoupper(trim((string) $baseCurrency));

        if ($baseCurrency !== '') {
            return $baseCurrency;
        }

        $registryDefault = strtoupper(trim($this->currencyRegistry->default()));
        $registeredDefault = Currency::query()
            ->where('code', $registryDefault)
            ->value('code');

        if ($registeredDefault) {
            return strtoupper((string) $registeredDefault);
        }

        $firstCurrency = Currency::query()->orderBy('code')->value('code');

        return strtoupper((string) ($firstCurrency ?: $registryDefault));
    }
}
