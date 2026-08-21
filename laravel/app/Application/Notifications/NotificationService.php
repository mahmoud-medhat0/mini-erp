<?php

namespace App\Application\Notifications;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * @return array<string, mixed>
     */
    public function create(
        int|string $userId,
        string $type,
        string $targetRef,
        ?string $dedupeKey = null,
    ): array {
        $id = (string) Str::uuid();
        $payload = [
            'id' => $id,
            'user_id' => (int) $userId,
            'type' => $type,
            'target_ref' => $targetRef,
            'dedupe_key' => $dedupeKey,
            'read' => false,
            'at' => now(),
        ];

        if ($dedupeKey !== null) {
            DB::table('notification')->insertOrIgnore($payload);

            return (array) DB::table('notification')
                ->where('user_id', (int) $userId)
                ->where('dedupe_key', $dedupeKey)
                ->first();
        }

        DB::table('notification')->insert($payload);

        return $payload;
    }

    public function queryForUser(int|string $userId, bool $unreadOnly = false): Builder
    {
        return DB::table('notification')
            ->where('user_id', (int) $userId)
            ->when($unreadOnly, fn ($query) => $query->where('read', false));
    }

    /**
     * @return Collection<int, object>
     */
    public function listForUser(int|string $userId, bool $unreadOnly = false): Collection
    {
        return $this->queryForUser($userId, $unreadOnly)
            ->orderByDesc('at')
            ->get();
    }

    public function markRead(int|string $userId, string $id): bool
    {
        return DB::table('notification')
            ->where('id', $id)
            ->where('user_id', (int) $userId)
            ->update(['read' => true]) === 1;
    }

    public function markAllRead(int|string $userId): int
    {
        return DB::table('notification')
            ->where('user_id', (int) $userId)
            ->where('read', false)
            ->update(['read' => true]);
    }
}
