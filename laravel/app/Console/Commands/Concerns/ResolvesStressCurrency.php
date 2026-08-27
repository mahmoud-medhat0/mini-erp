<?php

namespace App\Console\Commands\Concerns;

use App\Application\Support\BaseCurrencyResolver;
use App\Models\Currency;

trait ResolvesStressCurrency
{
    protected function resolveStressCurrency(BaseCurrencyResolver $baseCurrencyResolver): string
    {
        $currency = $baseCurrencyResolver->resolve();

        Currency::query()->firstOrCreate(
            ['code' => $currency],
            [
                'name' => ['en' => $currency, 'ar' => $currency],
                'symbol' => $currency,
                'exponent' => 2,
                'is_active' => true,
            ],
        );

        return $currency;
    }
}
