<?php

namespace App\Support\Concurrency;

use RuntimeException;

class DuplicateOperationInProgressException extends RuntimeException
{
    public function __construct(public readonly string $operation, public readonly string $keyHash)
    {
        parent::__construct(__('concurrency.duplicate_in_progress'), 409);
    }
}
