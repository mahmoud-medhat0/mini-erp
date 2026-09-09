<?php

namespace App\Application\Projects;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ProjectPageData
{
    private const SORT_COLUMNS = [
        'code' => 'project.code',
        'name' => 'project.name',
        'description' => 'project.description',
        'status' => 'project.status',
        'start_date' => 'project.start_date',
        'end_date' => 'project.end_date',
        'is_billable' => 'project.is_billable',
        'is_active' => 'project.is_active',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{search: string, status: string, is_billable: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $isBillable = trim((string) ($filters['is_billable'] ?? ''));

        return [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'is_billable' => $isBillable,
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = $this->filteredQuery($filters);

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $this->applySearch($builder, trim((string) request()->input('search.value', '')));
            })
            ->order(function (Builder $builder): void {
                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset(self::SORT_COLUMNS[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    if ($data === 'name') {
                        $builder->orderByRaw("CAST(project.name AS TEXT) {$direction}");
                    } else {
                        $builder->orderBy(self::SORT_COLUMNS[$data], $direction);
                    }
                }

                $builder->orderBy('project.code')->orderBy('project.id');
            })
            ->editColumn('name', fn (Project $project): array => $project->getTranslations('name'))
            ->editColumn('is_billable', fn (Project $project): bool => (bool) $project->is_billable)
            ->editColumn('is_active', fn (Project $project): bool => (bool) $project->is_active)
            ->editColumn('lock_version', fn (Project $project): int => (int) $project->lock_version)
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the {en, ar} translation map.
            ->rawColumns(['name', 'name.en', 'name.ar'])
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $isBillable = trim((string) ($filters['is_billable'] ?? ''));

        $query = Project::query()
            ->select('project.*')
            ->when($status !== '', fn (Builder $builder) => $builder->where('project.status', $status))
            ->when($isBillable !== '', fn (Builder $builder) => $builder->where('project.is_billable', $isBillable === 'true'));

        $this->applySearch($query, $search);

        return $query;
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = '%'.mb_strtolower($search).'%';
        $query->where(function (Builder $inner) use ($pattern): void {
            $inner->whereRaw('LOWER(project.code) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(CAST(project.name AS TEXT)) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(project.description, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(project.status) LIKE ?', [$pattern]);
        });
    }
}
