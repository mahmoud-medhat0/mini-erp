<?php

namespace App\Application\Inventory;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class WarehousePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     warehouses: LengthAwarePaginator,
     *     branches: EloquentCollection<int, Branch>,
     *     warehouseTypes: array<int, string>,
     *     locationTypes: array<int, string>,
     *     filters: array{search: mixed, status: mixed, branch_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'branch_id' => $filters['branch_id'] ?? null,
        ];

        return [
            'warehouses' => $this->warehouses($normalizedFilters),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'warehouseTypes' => WarehouseService::WAREHOUSE_TYPES,
            'locationTypes' => WarehouseService::LOCATION_TYPES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, branch_id: mixed}  $filters
     */
    private function warehouses(array $filters): LengthAwarePaginator
    {
        return Warehouse::query()
            ->with(['branch', 'locations'])
            ->when($filters['search'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('code', 'like', "%{$filters['search']}%")
                        ->orWhere('name->en', 'like', "%{$filters['search']}%")
                        ->orWhere('name->ar', 'like', "%{$filters['search']}%");
                });
            })
            ->when(
                $filters['status'] && in_array($filters['status'], ['active', 'inactive'], true),
                fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active')
            )
            ->when($filters['branch_id'], fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();
    }
}
