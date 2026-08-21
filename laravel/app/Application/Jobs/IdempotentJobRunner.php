<?php

namespace App\Application\Jobs;

use App\Support\Concurrency\DatabaseIdempotencyStore;
use Closure;

class IdempotentJobRunner
{
    public function __construct(private readonly DatabaseIdempotencyStore $idempotencyStore) {}

    public function run(string $jobName, string $idempotencyKey, Closure $handler): mixed
    {
        return $this->idempotencyStore
            ->run("job.{$jobName}", $idempotencyKey, $handler, null, $jobName, now()->addDays(7))
            ->value;
    }
}
