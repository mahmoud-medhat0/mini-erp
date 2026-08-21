<?php

namespace App\Domain\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        DB::table('audit_log')->insert([
            'id' => (string) Str::uuid(),
            'actor_id' => is_numeric($actorId) ? (int) $actorId : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before === null ? null : json_encode($this->redact($before), JSON_THROW_ON_ERROR),
            'after_json' => $after === null ? null : json_encode($this->redact($after), JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'request_id' => $requestId,
            'ip' => $ip,
            'device' => $device,
            'at' => now(),
        ]);
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
