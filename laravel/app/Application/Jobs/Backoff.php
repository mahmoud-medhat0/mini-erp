<?php

namespace App\Application\Jobs;

class Backoff
{
    public static function milliseconds(int $attempt, int $baseMs = 1000, int $capMs = 300000): int
    {
        return min($capMs, $baseMs * (2 ** max(0, $attempt - 1)));
    }
}
