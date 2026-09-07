<?php

namespace App\Application\FixedAssets;

use App\Models\Branch;
use App\Models\FixedAssetLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FixedAssetLocationPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters, ?User $user): array
    {
        return [
            'locations' => [],
            'branches' => $this->branches(),
            'filters' => [
                'search' => trim((string) ($filters['search'] ?? '')),
                'branch_id' => (string) ($filters['branch_id'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'can' => [
                'create' => $this->can($user, 'fixedAssets.create'),
                'edit' => $this->can($user, 'fixedAssets.edit'),
                'delete' => $this->can($user, 'fixedAssets.delete'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters): JsonResponse
    {
        $branchId = (string) ($filters['branch_id'] ?? '');
        $status = (string) ($filters['status'] ?? '');

        $query = FixedAssetLocation::query()
            ->with('branch')
            ->select('fixed_asset_location.*')
            ->withCount('assets')
            ->when($branchId !== '', fn ($builder) => $builder->where('fixed_asset_location.branch_id', $branchId))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($builder) => $builder->where('fixed_asset_location.is_active', $status === 'active'));

        return DataTables::eloquent($query)
            ->addColumn('name_text', fn (FixedAssetLocation $location): string => (string) $location->name)
            ->addColumn('branch_label', fn (): string => '')
            ->addColumn('actions', fn (): string => '')
            ->filterColumn('name_text', function ($builder, string $keyword): void {
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('fixed_asset_location.name->en', 'like', "%{$keyword}%")
                        ->orWhere('fixed_asset_location.name->ar', 'like', "%{$keyword}%");
                });
            })
            ->toJson();
    }

    /**
     * @return EloquentCollection<int, Branch>
     */
    private function branches(): EloquentCollection
    {
        return Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
    }

    private function can(?User $user, string $permission): bool
    {
        return $user?->can($permission) ?? false;
    }
}
