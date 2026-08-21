<?php

namespace App\Support\Concurrency;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionRetrier
{
    public function run(string $operation, Closure $callback, int $attempts = 3): mixed
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $exception) {
                if ($attempt >= $attempts || ! $this->isRetryable($exception)) {
                    throw $exception;
                }

                Log::warning('Retrying transient database concurrency failure', [
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'sql_state' => $exception->errorInfo[0] ?? null,
                ]);

                usleep((int) min(250000, 50000 * (2 ** ($attempt - 1))));
            }
        }

        throw new \LogicException('Transaction retry loop exhausted unexpectedly.');
    }

    private function isRetryable(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['40001', '40P01'], true)
            || in_array($driverCode, ['1205', '1213'], true);
    }
}
