<?php

namespace App\Application\CostCenters;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class CostCenterPageData
{
    private const SORT_COLUMNS = [
        'code' => 'cost_center.code',
        'name' => 'cost_center.name',
        'description' => 'cost_center.description',
        'category' => 'cost_center.category',
        'is_active' => 'cost_center.is_active',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{search: string, category: string, status: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return [
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
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
                        $builder->orderByRaw("CAST(cost_center.name AS TEXT) {$direction}");
                    } else {
                        $builder->orderBy(self::SORT_COLUMNS[$data], $direction);
                    }
                }

                $builder->orderBy('cost_center.code')->orderBy('cost_center.id');
            })
            ->editColumn('name', fn (CostCenter $costCenter): array => $costCenter->getTranslations('name'))
            ->editColumn('is_active', fn (CostCenter $costCenter): bool => (bool) $costCenter->is_active)
            ->editColumn('lock_version', fn (CostCenter $costCenter): int => (int) $costCenter->lock_version)
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the {en, ar} translation map.
            ->rawColumns(['name', 'name.en', 'name.ar'])
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $query = CostCenter::query()
            ->select('cost_center.*')
            ->when($category !== '', fn (Builder $builder) => $builder->where('cost_center.category', $category))
            ->when($status !== '', fn (Builder $builder) => $builder->where('cost_center.is_active', $status === 'active'));

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
            $inner->whereRaw('LOWER(cost_center.code) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(CAST(cost_center.name AS TEXT)) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(cost_center.description, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(cost_center.category, \'\')) LIKE ?', [$pattern]);
        });
    }
}
