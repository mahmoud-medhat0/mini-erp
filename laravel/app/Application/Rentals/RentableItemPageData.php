<?php

namespace App\Application\Rentals;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\RentableItem;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class RentableItemPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     items: LengthAwarePaginator,
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

        $items = RentableItem::query()
            ->with(['product', 'fixedAsset', 'branch', 'warehouse', 'currencyRef'])
            ->when($status !== '' && in_array($status, RentableItemService::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($source !== '' && in_array($source, RentableItemService::ITEM_SOURCES, true), fn ($query) => $query->where('item_source', $source))
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->when($warehouseId !== '', fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return [
            'items' => $items,
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
}
