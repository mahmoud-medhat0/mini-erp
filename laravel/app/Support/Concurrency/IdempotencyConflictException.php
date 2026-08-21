<?php

namespace App\Support\Concurrency;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct(public readonly string $operation, public readonly string $keyHash)
    {
        parent::__construct(__('concurrency.idempotency_conflict'), 409);
    }
}
