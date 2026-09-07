<?php

namespace App\Application\Rentals;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\RentableItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class RentableItemPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     items: array,
     *     branches: EloquentCollection<int, Branch>,
     *     warehouses: EloquentCollection<int, Warehouse>,
     *     products: EloquentCollection<int, Product>,
     *     fixedAssets: EloquentCollection<int, FixedAsset>,
     *     currencies: EloquentCollection<int, Currency>,
     *     itemSources: array<int, string>,
     *     statuses: array<int, string>,
     *     conditionStatuses: array<int, string>,
     *     filters: array{search: string, status: string, item_source: string, branch_id: string, warehouse_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $source = (string) ($filters['item_source'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        return [
            'items' => [],
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'branch_id', 'warehouse_type']),
            'products' => Product::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'fixedAssets' => FixedAsset::query()->where('status', '!=', 'disposed')->orderBy('asset_number')->get(['id', 'asset_number', 'name', 'status', 'branch_id']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'itemSources' => RentableItemService::ITEM_SOURCES,
            'statuses' => RentableItemService::STATUSES,
            'conditionStatuses' => RentableItemService::CONDITION_STATUSES,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'item_source' => $source,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $source = (string) ($filters['item_source'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = RentableItem::query()
            ->with(['product', 'fixedAsset', 'branch', 'warehouse', 'currencyRef'])
            ->leftJoin('branch as rentable_branch', 'rentable_branch.id', '=', 'rentable_item.branch_id')
            ->leftJoin('warehouse as rentable_warehouse', 'rentable_warehouse.id', '=', 'rentable_item.warehouse_id')
            ->select('rentable_item.*')
            ->when($status !== '' && in_array($status, RentableItemService::STATUSES, true), fn (Builder $query) => $query->where('rentable_item.status', $status))
            ->when($source !== '' && in_array($source, RentableItemService::ITEM_SOURCES, true), fn (Builder $query) => $query->where('rentable_item.item_source', $source))
            ->when($branchId !== '', fn (Builder $query) => $query->where('rentable_item.branch_id', $branchId))
            ->when($warehouseId !== '', fn (Builder $query) => $query->where('rentable_item.warehouse_id', $warehouseId));

        return DataTables::eloquent($query)
            ->filterColumn('rentable_item.code', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rentable_item.code', 'like', "%{$keyword}%")
                        ->orWhere('rentable_item.serial_number', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(rentable_item.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('rentable_item.name', function (Builder $query, string $keyword): void {
                $query->whereRaw('LOWER(CAST(rentable_item.name AS TEXT)) LIKE ?', ['%'.mb_strtolower($keyword).'%']);
            })
            ->filterColumn('location', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('rentable_branch.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(rentable_branch.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhere('rentable_warehouse.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(rentable_warehouse.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('location', 'COALESCE(rentable_branch.code, rentable_warehouse.code) $1')
            ->addColumn('location', fn () => '')
            ->addColumn('rates', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
