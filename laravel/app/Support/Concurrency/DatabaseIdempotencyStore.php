<?php

namespace App\Support\Concurrency;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DatabaseIdempotencyStore
{
    /**
     * Execute a side effect once for an operation/key/scope tuple.
     *
     * Concurrent callers with the same tuple either observe the completed result
     * or receive a deterministic in-progress conflict; they never execute the
     * callback twice.
     */
    public function run(
        string $operation,
        string $rawKey,
        Closure $callback,
        int|string|null $actorId = null,
        ?string $requestFingerprint = null,
        ?Carbon $expiresAt = null,
    ): IdempotencyResult {
        $claim = $this->claim($operation, $rawKey, $actorId, $requestFingerprint, $expiresAt);

        if ($claim['status'] === 'completed') {
            return new IdempotencyResult(false, $claim['response'], 'completed');
        }

        if ($claim['status'] !== 'acquired') {
            throw new DuplicateOperationInProgressException($operation, $claim['key_hash']);
        }

        try {
            $value = $callback();

            DB::table('idempotency_keys')
                ->where('id', $claim['id'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'response_json' => $this->encodeResponse($value),
                    'completed_at' => now(),
                ]);

            return new IdempotencyResult(true, $value, 'completed');
        } catch (Throwable $throwable) {
            DB::table('idempotency_keys')
                ->where('id', $claim['id'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error_code' => $throwable::class,
                    'completed_at' => now(),
                ]);

            Log::warning('Idempotent operation failed', [
                'operation' => $operation,
                'key_hash' => $claim['key_hash'],
                'actor_id' => $actorId,
                'exception' => $throwable::class,
            ]);

            throw $throwable;
        }
    }

    /**
     * @return array{id?: string, status: string, key_hash: string, response?: mixed}
     */
    private function claim(
        string $operation,
        string $rawKey,
        int|string|null $actorId,
        ?string $requestFingerprint,
        ?Carbon $expiresAt,
    ): array {
        $keyHash = hash('sha256', $rawKey);
        $requestHash = $requestFingerprint ? hash('sha256', $requestFingerprint) : null;
        $keyScope = $actorId === null ? 'global' : "user:{$actorId}";
        $expiresAt ??= now()->addDay();
        $id = (string) Str::uuid();

        $inserted = DB::table('idempotency_keys')->insertOrIgnore([
            'id' => $id,
            'operation' => $operation,
            'key_hash' => $keyHash,
            'key_scope' => $keyScope,
            'actor_id' => is_numeric($actorId) ? (int) $actorId : null,
            'request_hash' => $requestHash,
            'status' => 'pending',
            'created_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        if ($inserted === 1) {
            return ['id' => $id, 'status' => 'acquired', 'key_hash' => $keyHash];
        }

        $row = DB::table('idempotency_keys')
            ->where('operation', $operation)
            ->where('key_hash', $keyHash)
            ->where('key_scope', $keyScope)
            ->first();

        if (! $row) {
            return $this->claim($operation, $rawKey, $actorId, $requestFingerprint, $expiresAt);
        }

        if ($requestHash !== null && $row->request_hash !== null && $row->request_hash !== $requestHash) {
            throw new IdempotencyConflictException($operation, $keyHash);
        }

        if ($row->status === 'completed') {
            return [
                'status' => 'completed',
                'key_hash' => $keyHash,
                'response' => $this->decodeResponse($row->response_json),
            ];
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            $updated = DB::table('idempotency_keys')
                ->where('id', $row->id)
                ->where('expires_at', '<', now())
                ->whereIn('status', ['pending', 'failed'])
                ->update([
                    'status' => 'pending',
                    'request_hash' => $requestHash,
                    'error_code' => null,
                    'response_json' => null,
                    'response_type' => null,
                    'response_id' => null,
                    'created_at' => now(),
                    'completed_at' => null,
                    'expires_at' => $expiresAt,
                ]);

            if ($updated === 1) {
                return ['id' => $row->id, 'status' => 'acquired', 'key_hash' => $keyHash];
            }
        }

        return ['status' => $row->status, 'key_hash' => $keyHash];
    }

    private function encodeResponse(mixed $response): string
    {
        return json_encode($response, JSON_THROW_ON_ERROR);
    }

    private function decodeResponse(mixed $response): mixed
    {
        if (! is_string($response)) {
            return $response;
        }

        $decoded = json_decode($response, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
    }
}
