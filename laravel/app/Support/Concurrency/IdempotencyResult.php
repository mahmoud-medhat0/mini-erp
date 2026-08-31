<?php

namespace App\Support\Concurrency;

class IdempotencyResult
{
    public function __construct(
        public readonly bool $executed,
        public readonly mixed $value,
        public readonly string $status,
    ) {}
}
