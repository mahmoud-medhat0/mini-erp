<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthGarbageCollector
{
    /**
     * @return array{sessions: int, password_reset_tokens: int, idempotency_keys: int}
     */
    public function collect(int $batchSize = 500): array
    {
        $batchSize = max(1, min($batchSize, 5000));

        return [
            'sessions' => $this->collectSessions($batchSize),
            'password_reset_tokens' => $this->collectPasswordResetTokens($batchSize),
            'idempotency_keys' => $this->collectIdempotencyKeys($batchSize),
        ];
    }

    private function collectSessions(int $batchSize): int
    {
        $cutoff = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        $ids = DB::table('sessions')
            ->where('last_activity', '<', $cutoff)
            ->orderBy('last_activity')
            ->limit($batchSize)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return DB::table('sessions')
            ->whereIn('id', $ids)
            ->where('last_activity', '<', $cutoff)
            ->delete();
    }

    private function collectPasswordResetTokens(int $batchSize): int
    {
        $cutoff = now()->subMinutes((int) config('auth.passwords.users.expire', 60));

        $emails = DB::table('password_reset_tokens')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit($batchSize)
            ->pluck('email')
            ->all();

        if ($emails === []) {
            return 0;
        }

        return DB::table('password_reset_tokens')
            ->whereIn('email', $emails)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    private function collectIdempotencyKeys(int $batchSize): int
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return 0;
        }

        $ids = DB::table('idempotency_keys')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($batchSize)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return DB::table('idempotency_keys')
            ->whereIn('id', $ids)
            ->where('expires_at', '<', now())
            ->delete();
    }
}
