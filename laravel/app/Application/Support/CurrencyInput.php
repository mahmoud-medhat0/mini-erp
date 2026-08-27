<?php

namespace App\Application\Support;

use Illuminate\Validation\ValidationException;

class CurrencyInput
{
    public static function required(mixed $currency, string $field = 'currency'): string
    {
        $code = is_scalar($currency) ? strtoupper(trim((string) $currency)) : '';

        if ($code === '') {
            throw ValidationException::withMessages([$field => [__('Currency is required.')]]);
        }

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw ValidationException::withMessages([$field => [__('Currency must be a 3-letter ISO code.')]]);
        }

        return $code;
    }

    public static function related(mixed $currency, string $field = 'currency', string $source = 'Source document'): string
    {
        try {
            return self::required($currency, $field);
        } catch (ValidationException) {
            throw ValidationException::withMessages([$field => [__(':source currency is required.', ['source' => $source])]]);
        }
    }
}
