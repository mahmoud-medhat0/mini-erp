<?php

namespace App\Application\Audit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditLogQueryService
{
    /**
     * Query Spatie activity_log table with safe read-only filtering and mapped aliases.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $actorIdExpression = $this->jsonValueExpression('actor_id');
        $entityTypeExpression = $this->jsonValueExpression('entity_type');
        $entityIdExpression = $this->jsonValueExpression('entity_id');
        $requestIdExpression = $this->jsonValueExpression('request_id');
        $propertiesTextExpression = $this->jsonTextExpression();

        $query = DB::table('activity_log')
            ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
            ->select([
                'activity_log.id',
                'activity_log.causer_id',
                'activity_log.causer_type',
                'users.name as actor_name',
                'users.email as actor_email',
                'activity_log.log_name',
                'activity_log.description',
                'activity_log.event',
                'activity_log.subject_type',
                'activity_log.subject_id',
                'activity_log.properties',
                'activity_log.created_at',
            ]);

        if (! empty($filters['actor_id'])) {
            $actorId = (int) $filters['actor_id'];
            $query->where(function (Builder $q) use ($actorId, $actorIdExpression): void {
                $q->where('activity_log.causer_id', $actorId)
                    ->orWhereRaw("{$actorIdExpression} = ?", [(string) $actorId]);
            });
        }

        if (! empty($filters['action'])) {
            $action = (string) $filters['action'];
            $query->where(function (Builder $q) use ($action): void {
                $q->where('activity_log.event', $action)
                    ->orWhere('activity_log.description', $action);
            });
        }

        if (! empty($filters['entity_type'])) {
            $entityType = (string) $filters['entity_type'];
            $query->where(function (Builder $q) use ($entityType, $entityTypeExpression): void {
                $q->where('activity_log.subject_type', $entityType)
                    ->orWhereRaw("{$entityTypeExpression} = ?", [$entityType]);
            });
        }

        if (! empty($filters['entity_id'])) {
            $entityId = (string) $filters['entity_id'];
            $query->where(function (Builder $q) use ($entityId, $entityIdExpression): void {
                if (ctype_digit($entityId)) {
                    $q->where('activity_log.subject_id', (int) $entityId)
                        ->orWhereRaw("{$entityIdExpression} = ?", [$entityId]);

                    return;
                }

                $q->whereRaw("{$entityIdExpression} = ?", [$entityId]);
            });
        }

        if (! empty($filters['request_id'])) {
            $requestId = (string) $filters['request_id'];
            $query->whereRaw("{$requestIdExpression} = ?", [$requestId]);
        }

        if (! empty($filters['date_from'])) {
            $query->where('activity_log.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('activity_log.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (! empty($filters['search'])) {
            $search = '%'.mb_strtolower((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($search, $propertiesTextExpression): void {
                $q->whereRaw('LOWER(activity_log.description) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(activity_log.event) LIKE ?', [$search])
                    ->orWhereRaw("LOWER({$propertiesTextExpression}) LIKE ?", [$search])
                    ->orWhereRaw('LOWER(users.name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$search]);
            });
        }

        $paginator = $query->orderByDesc('activity_log.created_at')->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(function (object $row): object {
            $props = [];
            if (! empty($row->properties)) {
                $decoded = is_string($row->properties) ? json_decode($row->properties, true) : (array) $row->properties;
                if (is_array($decoded)) {
                    $props = $decoded;
                }
            }

            return (object) [
                'id' => (string) $row->id,
                'actor_id' => $row->causer_id ?? $props['actor_id'] ?? null,
                'actor_name' => $row->actor_name,
                'actor_email' => $row->actor_email,
                'action' => $row->event ?: $row->description,
                'entity_type' => $row->subject_type ?: ($props['entity_type'] ?? ''),
                'entity_id' => $row->subject_id ? (string) $row->subject_id : (string) ($props['entity_id'] ?? ''),
                'before_json' => isset($props['before']) ? json_encode($props['before']) : null,
                'after_json' => isset($props['after']) ? json_encode($props['after']) : null,
                'reason' => $props['reason'] ?? null,
                'request_id' => $props['request_id'] ?? null,
                'ip' => $props['ip'] ?? null,
                'device' => $props['device'] ?? null,
                'at' => $row->created_at,
            ];
        });

        return $paginator;
    }

    /**
     * Get distinct available actions from activity_log.
     *
     * @return Collection<int, string>
     */
    public function getAvailableActions(): Collection
    {
        $events = DB::table('activity_log')->distinct()->pluck('event')->filter()->values();
        $descriptions = DB::table('activity_log')->distinct()->pluck('description')->filter()->values();

        return $events->merge($descriptions)->unique()->values();
    }

    /**
     * Get distinct available entity types from activity_log.
     *
     * @return Collection<int, string>
     */
    public function getAvailableEntityTypes(): Collection
    {
        $subjects = DB::table('activity_log')->distinct()->pluck('subject_type')->filter()->values();

        $fromProps = DB::table('activity_log')
            ->whereNotNull('properties')
            ->get()
            ->map(function ($row) {
                $props = is_string($row->properties) ? json_decode($row->properties, true) : (array) $row->properties;

                return $props['entity_type'] ?? null;
            })
            ->filter()
            ->values();

        return $subjects->merge($fromProps)->unique()->values();
    }

    private function jsonValueExpression(string $key): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "activity_log.properties->>'{$key}'",
            'sqlite' => "json_extract(activity_log.properties, '$.{$key}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT(activity_log.properties, '$.{$key}'))",
        };
    }

    private function jsonTextExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'CAST(activity_log.properties AS TEXT)',
            default => 'CAST(activity_log.properties AS TEXT)',
        };
    }
}
