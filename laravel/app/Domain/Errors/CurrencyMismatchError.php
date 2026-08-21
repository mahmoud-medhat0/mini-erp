<?php

namespace App\Domain\Errors;

class CurrencyMismatchError extends DomainError
{
    public function __construct(string $left, string $right)
    {
        parent::__construct('currency_mismatch', "Currency mismatch: {$left} vs {$right}", 422, [
            'left' => $left,
            'right' => $right,
        ]);
    }
}
