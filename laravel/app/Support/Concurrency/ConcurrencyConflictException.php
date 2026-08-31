<?php

namespace App\Support\Concurrency;

use RuntimeException;

class ConcurrencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('concurrency.conflict'), 409);
    }
}
