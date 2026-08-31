<?php

namespace App\Domain\Audit;

use App\Models\User;

class AuditLogger
{
    private const REDACT = ['password', 'password_hash', 'passwordHash', 'token', 'secret', 'authorization'];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        int|string|null $actorId,
        string $action,
        string $entityType,
        string $entityId,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?string $requestId = null,
        ?string $ip = null,
        ?string $device = null,
    ): void {
        $causer = null;

        if ($actorId !== null && is_numeric($actorId)) {
            $causer = User::query()->find((int) $actorId);
        }

        $properties = [
            'actor_id' => $actorId !== null ? (is_numeric($actorId) ? (int) $actorId : (string) $actorId) : null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before === null ? null : $this->redact($before),
            'after' => $after === null ? null : $this->redact($after),
            'reason' => $reason,
            'request_id' => $requestId,
            'ip' => $ip,
            'device' => $device,
        ];

        $activity = activity('default')
            ->event($action)
            ->withProperties($properties);

        if ($causer !== null) {
            $activity->causedBy($causer);
        } else {
            $activity->causedByAnonymous();
        }

        $activity->log($action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            $redacted[$key] = in_array($key, self::REDACT, true) ? '[redacted]' : $value;
        }

        return $redacted;
    }
}
