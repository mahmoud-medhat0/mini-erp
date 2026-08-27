<?php

namespace App\Application\FixedAssets;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class FixedAssetLocationPageData
{
    public function __construct(private readonly FixedAssetLocationService $locationService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters, ?User $user): array
    {
        return [
            'locations' => $this->locationService->listLocations($filters),
            'branches' => $this->branches(),
            'filters' => $filters,
            'can' => [
                'create' => $this->can($user, 'fixedAssets.create'),
                'edit' => $this->can($user, 'fixedAssets.edit'),
                'delete' => $this->can($user, 'fixedAssets.delete'),
            ],
        ];
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
