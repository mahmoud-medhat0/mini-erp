<?php

namespace App\Application\FixedAssets;

use App\Application\Attachments\AttachmentService;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\FixedAssetLocation;
use App\Models\User;
use Illuminate\Support\Collection;

class FixedAssetPageData
{
    public function __construct(
        private readonly FixedAssetRegisterService $assetService,
        private readonly FixedAssetCategoryService $categoryService,
        private readonly AttachmentService $attachmentService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters, ?User $user): array
    {
        return [
            'assets' => $this->assetService->listAssets($filters),
            'categories' => $this->categoryService->listCategories(),
            'branches' => $this->activeBranches(),
            'locations' => $this->activeLocations(),
            'filters' => $filters,
            'can' => [
                'create' => $this->can($user, 'fixedAssets.create'),
                'edit' => $this->can($user, 'fixedAssets.edit'),
                'delete' => $this->can($user, 'fixedAssets.delete'),
                'post' => $this->can($user, 'fixedAssets.post'),
                'reverse' => $this->can($user, 'fixedAssets.reverse'),
                'transfer' => $this->can($user, 'fixedAssets.transfer'),
                'export' => $this->can($user, 'fixedAssets.export'),
                'view_financials' => $this->can($user, 'view_financials'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createData(): array
    {
        return [
            'categories' => $this->activeCategories(),
            'currencies' => $this->currencies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(string $id, ?User $user): array
    {
        $asset = $this->asset($id);

        return [
            'asset' => $asset,
            'attachments' => $user ? $this->attachmentService->listForEntity('fixed_asset', $asset->id, $user) : [],
            'branches' => $this->activeBranches(),
            'locations' => $this->activeLocations(),
            'can' => [
                'edit' => $this->can($user, 'fixedAssets.edit') && $asset->status === 'draft',
                'delete' => $this->can($user, 'fixedAssets.delete') && $asset->status === 'draft',
                'post' => $this->can($user, 'fixedAssets.post') && $this->can($user, 'view_financials') && $asset->status === 'draft',
                'reverse' => $this->can($user, 'fixedAssets.reverse') && $this->can($user, 'view_financials') && $asset->status === 'active' && $asset->capitalization_mode === 'manual_capitalization',
                'transfer' => $this->can($user, 'fixedAssets.transfer') && $asset->status !== 'disposed',
                'generate_schedule' => $this->can($user, 'fixedAssets.edit') && $this->can($user, 'view_financials') && $asset->status === 'active',
                'view_financials' => $this->can($user, 'view_financials'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editData(FixedAsset $asset): array
    {
        return [
            'asset' => $asset,
            'categories' => $this->activeCategories(),
            'currencies' => $this->currencies(),
        ];
    }

    public function assetForEditing(string $id): FixedAsset
    {
        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()->with('category')->findOrFail($id);

        return $asset;
    }

    public function asset(string $id): FixedAsset
    {
        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()
            ->with(['category', 'currencyModel', 'journalEntry', 'capitalizer', 'creator', 'updater', 'depreciationSchedules.financialPeriod'])
            ->with(['branch', 'location.branch', 'movements.fromBranch', 'movements.toBranch', 'movements.fromLocation', 'movements.toLocation', 'movements.creator'])
            ->findOrFail($id);

        return $asset;
    }

    /**
     * @return Collection<int, mixed>
     */
    private function activeCategories(): Collection
    {
        return $this->categoryService->listCategories()->where('is_active', true)->values();
    }

    /**
     * @return Collection<int, Currency>
     */
    private function currencies(): Collection
    {
        return Currency::query()->get(['code', 'name', 'symbol']);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function activeBranches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, FixedAssetLocation>
     */
    private function activeLocations(): Collection
    {
        return FixedAssetLocation::query()->where('is_active', true)->with('branch')->orderBy('code')->get();
    }

    private function can(?User $user, string $permission): bool
    {
        return $user?->can($permission) ?? false;
    }
}
