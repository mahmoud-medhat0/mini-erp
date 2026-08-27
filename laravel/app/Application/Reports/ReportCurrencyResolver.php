<?php

namespace App\Application\Reports;

use App\Application\Support\BaseCurrencyResolver;

class ReportCurrencyResolver
{
    public function __construct(
        private readonly BaseCurrencyResolver $baseCurrencyResolver,
    ) {}

    public function resolve(mixed $currency = null): string
    {
        $requestedCurrency = is_scalar($currency) ? strtoupper(trim((string) $currency)) : '';

        if ($requestedCurrency !== '') {
            return $requestedCurrency;
        }

        return $this->baseCurrencyResolver->resolve();
    }
}
