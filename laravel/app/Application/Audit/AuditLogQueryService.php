<?php

namespace App\Application\Audit;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AuditLogQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function pageData(array $filters): array
    {
        return [
            'filters' => $filters,
            'actions' => $this->getAvailableActions(),
            'entityTypes' => $this->getAvailableEntityTypes(),
            'usersList' => $this->usersList(),
        ];
    }

    /**
     * Query Spatie activity_log table with safe read-only filtering and mapped aliases.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $paginator = $this->filteredQuery($filters)
            ->orderByDesc('activity_log.created_at')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (object $row): object => $this->normalizeRow($row));

        return $paginator;
    }

    /**
     * Server-side DataTables feed for the audit trail.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = $this->filteredQuery($filters)
            ->orderByDesc('activity_log.created_at');
        $properties = [];
        $props = function (object $row) use (&$properties): array {
            $key = (string) $row->id;

            return $properties[$key] ??= $this->decodeProperties($row->properties ?? null);
        };

        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $keyword = trim((string) request()->input('search.value', ''));

                if ($keyword !== '') {
                    $this->applySearch($builder, $keyword);
                }
            })
            ->orderColumn('at', 'activity_log.created_at $1')
            ->orderColumn('actor_name', 'users.name $1')
            ->orderColumn('action', 'activity_log.event $1')
            ->orderColumn('entity_type', 'activity_log.subject_type $1')
            ->orderColumn('entity_id', 'activity_log.subject_id $1')
            ->orderColumn('request_id', $this->jsonValueExpression('request_id').' $1')
            ->editColumn('id', fn (object $row) => (string) $row->id)
            ->addColumn('actor_id', fn (object $row) => $row->causer_id ?? $props($row)['actor_id'] ?? null)
            ->addColumn('action', fn (object $row) => $row->event ?: $row->description)
            ->addColumn('entity_type', fn (object $row) => $row->subject_type ?: ($props($row)['entity_type'] ?? ''))
            ->addColumn('entity_id', fn (object $row) => $row->subject_id ? (string) $row->subject_id : (string) ($props($row)['entity_id'] ?? ''))
            ->addColumn('before_json', fn (object $row) => isset($props($row)['before']) ? json_encode($props($row)['before']) : null)
            ->addColumn('after_json', fn (object $row) => isset($props($row)['after']) ? json_encode($props($row)['after']) : null)
            ->addColumn('reason', fn (object $row) => $props($row)['reason'] ?? null)
            ->addColumn('request_id', fn (object $row) => $props($row)['request_id'] ?? null)
            ->addColumn('ip', fn (object $row) => $props($row)['ip'] ?? null)
            ->addColumn('device', fn (object $row) => $props($row)['device'] ?? null)
            ->addColumn('at', fn (object $row) => $row->created_at)
            ->removeColumn('properties')
            ->toJson();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $actorIdExpression = $this->jsonValueExpression('actor_id');
        $entityTypeExpression = $this->jsonValueExpression('entity_type');
        $entityIdExpression = $this->jsonValueExpression('entity_id');
        $requestIdExpression = $this->jsonValueExpression('request_id');

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
            $query->where(function (Builder $builder) use ($actorId, $actorIdExpression): void {
                $builder->where('activity_log.causer_id', $actorId)
                    ->orWhereRaw("{$actorIdExpression} = ?", [(string) $actorId]);
            });
        }

        if (! empty($filters['action'])) {
            $action = (string) $filters['action'];
            $query->where(function (Builder $builder) use ($action): void {
                $builder->where('activity_log.event', $action)
                    ->orWhere('activity_log.description', $action);
            });
        }

        if (! empty($filters['entity_type'])) {
            $entityType = (string) $filters['entity_type'];
            $query->where(function (Builder $builder) use ($entityType, $entityTypeExpression): void {
                $builder->where('activity_log.subject_type', $entityType)
                    ->orWhereRaw("{$entityTypeExpression} = ?", [$entityType]);
            });
        }

        if (! empty($filters['entity_id'])) {
            $entityId = (string) $filters['entity_id'];
            $query->where(function (Builder $builder) use ($entityId, $entityIdExpression): void {
                if (ctype_digit($entityId)) {
                    $builder->where('activity_log.subject_id', (int) $entityId)
                        ->orWhereRaw("{$entityIdExpression} = ?", [$entityId]);

                    return;
                }

                $builder->whereRaw("{$entityIdExpression} = ?", [$entityId]);
            });
        }

        if (! empty($filters['request_id'])) {
            $query->whereRaw("{$requestIdExpression} = ?", [(string) $filters['request_id']]);
        }

        if (! empty($filters['date_from'])) {
            $query->where('activity_log.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('activity_log.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $this->applySearch($query, $filters['search']);
        }

        return $query;
    }

    private function applySearch(Builder $query, string $keyword): void
    {
        $search = '%'.mb_strtolower($keyword).'%';
        $propertiesTextExpression = $this->jsonTextExpression();

        $query->where(function (Builder $builder) use ($search, $propertiesTextExpression): void {
            $builder->whereRaw('LOWER(activity_log.description) LIKE ?', [$search])
                ->orWhereRaw('LOWER(activity_log.event) LIKE ?', [$search])
                ->orWhereRaw("LOWER({$propertiesTextExpression}) LIKE ?", [$search])
                ->orWhereRaw('LOWER(users.name) LIKE ?', [$search])
                ->orWhereRaw('LOWER(users.email) LIKE ?', [$search]);
        });
    }

    private function normalizeRow(object $row): object
    {
        $props = $this->decodeProperties($row->properties ?? null);

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
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeProperties(mixed $properties): array
    {
        if (empty($properties)) {
            return [];
        }

        $decoded = is_string($properties) ? json_decode($properties, true) : (array) $properties;

        return is_array($decoded) ? $decoded : [];
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
        $entityTypeExpression = $this->jsonValueExpression('entity_type');

        $fromProps = DB::table('activity_log')
            ->whereNotNull('properties')
            ->whereRaw("{$entityTypeExpression} IS NOT NULL")
            ->whereRaw("{$entityTypeExpression} <> ''")
            ->selectRaw("{$entityTypeExpression} as entity_type")
            ->distinct()
            ->get()
            ->pluck('entity_type')
            ->filter()
            ->values();

        return $subjects->merge($fromProps)->unique()->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function usersList(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();
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
