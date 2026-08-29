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
        $cleanUserId = (int) $userId;
        abort_if($cleanUserId <= 0, 422, __('Invalid user ID for notification.'));

        $cleanType = $this->normalizeType($type);
        $cleanTargetRef = $this->normalizeTargetRef($targetRef);

        $id = (string) Str::uuid();
        $key = $this->resolveDedupeKey($cleanUserId, $cleanType, $cleanTargetRef, $dedupeKey);

        $payload = [
            'id' => $id,
            'user_id' => $cleanUserId,
            'type' => $cleanType,
            'target_ref' => $cleanTargetRef,
            'dedupe_key' => $key,
            'read' => false,
            'at' => now(),
        ];

        DB::table('notification')->insertOrIgnore($payload);

        $record = DB::table('notification')
            ->where('user_id', $cleanUserId)
            ->where('dedupe_key', $key)
            ->first();

        return (array) $record;
    }

    public function queryForUser(int|string $userId, bool $unreadOnly = false): Builder
    {
        $cleanUserId = (int) $userId;

        return DB::table('notification')
            ->where('user_id', $cleanUserId)
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

    public function unreadCount(int|string $userId): int
    {
        return $this->queryForUser($userId, true)->count();
    }

    public function markRead(int|string $userId, string $id): bool
    {
        $cleanUserId = (int) $userId;
        $cleanId = trim($id);

        if ($cleanUserId <= 0 || $cleanId === '') {
            return false;
        }

        return DB::table('notification')
            ->where('id', $cleanId)
            ->where('user_id', $cleanUserId)
            ->update(['read' => true]) === 1;
    }

    public function markAllRead(int|string $userId): int
    {
        $cleanUserId = (int) $userId;

        if ($cleanUserId <= 0) {
            return 0;
        }

        return DB::table('notification')
            ->where('user_id', $cleanUserId)
            ->where('read', false)
            ->update(['read' => true]);
    }

    private function normalizeType(string $type): string
    {
        $type = trim($type);
        abort_if($type === '', 422, __('Notification type cannot be empty.'));

        if (mb_strlen($type) > 100) {
            $type = mb_substr($type, 0, 100);
        }

        return $type;
    }

    private function normalizeTargetRef(string $targetRef): string
    {
        $targetRef = trim($targetRef);
        abort_if($targetRef === '', 422, __('Notification target reference cannot be empty.'));

        if (mb_strlen($targetRef) > 255) {
            $targetRef = mb_substr($targetRef, 0, 255);
        }

        return $targetRef;
    }

    private function resolveDedupeKey(int $userId, string $type, string $targetRef, ?string $dedupeKey): string
    {
        if ($dedupeKey !== null && trim($dedupeKey) !== '') {
            $cleanKey = trim($dedupeKey);
            if (mb_strlen($cleanKey) > 255) {
                return md5($cleanKey);
            }

            return $cleanKey;
        }

        return md5("{$userId}:{$type}:{$targetRef}");
    }
}
