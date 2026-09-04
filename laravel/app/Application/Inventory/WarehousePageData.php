<?php

namespace App\Application\Inventory;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class WarehousePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     warehouses: array,
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
            'warehouses' => [],
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
     * Server-side DataTables feed for warehouses grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');

        $query = Warehouse::query()
            ->with(['branch', 'locations'])
            ->leftJoin('branch', 'branch.id', '=', 'warehouse.branch_id')
            ->select('warehouse.*')
            ->when($status && in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('warehouse.is_active', $status === 'active'))
            ->when($branchId, fn ($q) => $q->where('warehouse.branch_id', $branchId))
            ->orderByDesc('warehouse.is_default')
            ->orderBy('warehouse.code', 'asc');

        return DataTables::eloquent($query)
            ->filterColumn('code', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('warehouse.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(warehouse.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->editColumn('name', fn ($row) => $this->translatableName($row->name))
            ->addColumn('branch_name', fn ($row) => $row->branch?->code ?? '')
            ->addColumn('locations_list', fn ($row) => (string) ($row->locations?->count() ?? 0))
            ->addColumn('actions', fn ($row) => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    private function translatableName(mixed $name): array|string
    {
        if (! is_string($name)) {
            return is_array($name) ? $name : (string) $name;
        }

        $decoded = json_decode($name, true);

        return is_array($decoded) ? $decoded : $name;
    }
}
