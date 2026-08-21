<?php

namespace App\Domain\Errors;

use RuntimeException;

class DomainError extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $domainCode,
        string $message,
        public readonly int $httpStatus = 400,
        public readonly array $details = [],
    ) {
        parent::__construct($message, $httpStatus);
    }
}
