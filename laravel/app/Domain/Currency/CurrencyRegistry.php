<?php

namespace App\Domain\Currency;

use InvalidArgumentException;

class CurrencyRegistry
{
    public function default(): string
    {
        return (string) config('erp_currencies.default', 'EGP');
    }

    public function exponent(string $code): int
    {
        $currency = config("erp_currencies.supported.{$code}");

        if (! is_array($currency)) {
            throw new InvalidArgumentException("Unknown currency: {$code}");
        }

        return (int) $currency['exponent'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function supported(): array
    {
        return config('erp_currencies.supported', []);
    }
}
