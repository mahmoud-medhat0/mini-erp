<?php

namespace App\Application\FixedAssets;

use App\Domain\Audit\AuditLogger;
use App\Models\FixedAssetCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class FixedAssetCategoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Server-side DataTables feed for fixed asset categories grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = FixedAssetCategory::query()
            ->withCount('fixedAssets')
            ->select('fixed_asset_category.*')
            ->when($status === 'active', fn ($q) => $q->where('fixed_asset_category.is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('fixed_asset_category.is_active', false));

        return DataTables::of($query)
            ->addColumn('name_text', fn (FixedAssetCategory $row) => is_array($row->name) ? ($row->name['en'] ?? '') : (string) $row->name)
            ->filterColumn('name_text', fn ($q, $kw) => $q->where(function ($q2) use ($kw) {
                $q2->where('fixed_asset_category.name->en', 'like', "%{$kw}%")
                   ->orWhere('fixed_asset_category.name->ar', 'like', "%{$kw}%");
            }))
            ->make(true);
    }

    /**
     * @return Collection<int, FixedAssetCategory>
     */
    public function listCategories(): Collection
    {
        return FixedAssetCategory::query()
            ->withCount('fixedAssets')
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  array{code: string, name: array{en: string, ar: string}, useful_life_months?: int, salvage_value_minor?: int, is_active?: bool}  $data
     */
    public function createCategory(array $data, ?int $actorId = null): FixedAssetCategory
    {
        $code = trim($data['code']);
        if (FixedAssetCategory::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('Category code [:code] already exists.', ['code' => $code])],
            ]);
        }

        $usefulLife = (int) ($data['useful_life_months'] ?? 60);
        if ($usefulLife <= 0) {
            throw ValidationException::withMessages([
                'useful_life_months' => [__('Useful life must be a positive number of months.')],
            ]);
        }

        $salvage = (int) ($data['salvage_value_minor'] ?? 0);
        if ($salvage < 0) {
            throw ValidationException::withMessages([
                'salvage_value_minor' => [__('Salvage value cannot be negative.')],
            ]);
        }

        /** @var FixedAssetCategory $category */
        $category = FixedAssetCategory::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $data['name'],
            'useful_life_months' => $usefulLife,
            'salvage_value_minor' => $salvage,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'fixed_asset_category.create',
            entityType: 'fixed_asset_category',
            entityId: (string) $category->id,
            after: [
                'code' => $category->code,
                'name' => $category->name,
                'useful_life_months' => $category->useful_life_months,
            ],
        );

        return $category;
    }

    /**
     * @param  array{code?: string, name?: array{en: string, ar: string}, useful_life_months?: int, salvage_value_minor?: int, is_active?: bool}  $data
     */
    public function updateCategory(string $id, array $data, ?int $actorId = null): FixedAssetCategory
    {
        /** @var FixedAssetCategory $category */
        $category = FixedAssetCategory::query()->findOrFail($id);
        $before = $category->toArray();

        if (isset($data['code']) && $data['code'] !== $category->code) {
            $code = trim($data['code']);
            if (FixedAssetCategory::query()->where('code', $code)->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => [__('Category code [:code] already exists.', ['code' => $code])],
                ]);
            }
            $category->code = $code;
        }

        if (isset($data['name'])) {
            $category->name = $data['name'];
        }

        if (isset($data['useful_life_months'])) {
            $usefulLife = (int) $data['useful_life_months'];
            if ($usefulLife <= 0) {
                throw ValidationException::withMessages([
                    'useful_life_months' => [__('Useful life must be a positive number of months.')],
                ]);
            }
            $category->useful_life_months = $usefulLife;
        }

        if (isset($data['salvage_value_minor'])) {
            $salvage = (int) $data['salvage_value_minor'];
            if ($salvage < 0) {
                throw ValidationException::withMessages([
                    'salvage_value_minor' => [__('Salvage value cannot be negative.')],
                ]);
            }
            $category->salvage_value_minor = $salvage;
        }

        if (isset($data['is_active'])) {
            $category->is_active = (bool) $data['is_active'];
        }

        $category->save();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'fixed_asset_category.update',
            entityType: 'fixed_asset_category',
            entityId: (string) $category->id,
            before: $before,
            after: $category->toArray(),
        );

        return $category;
    }

    public function deleteCategory(string $id, ?int $actorId = null): void
    {
        /** @var FixedAssetCategory $category */
        $category = FixedAssetCategory::query()->withCount('fixedAssets')->findOrFail($id);

        if ($category->fixed_assets_count > 0) {
            throw ValidationException::withMessages([
                'category' => [__('Cannot delete category with linked assets.')],
            ]);
        }

        $before = $category->toArray();
        $category->delete();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'fixed_asset_category.delete',
            entityType: 'fixed_asset_category',
            entityId: $id,
            before: $before,
        );
    }
}
