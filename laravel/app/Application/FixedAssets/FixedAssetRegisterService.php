<?php

namespace App\Application\FixedAssets;

use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetRegisterService
{
    public function __construct(
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{search?: string, category_id?: string, status?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<FixedAsset>
     */
    public function listAssets(array $filters = []): LengthAwarePaginator
    {
        $query = FixedAsset::query()
            ->with(['category', 'currencyModel', 'branch', 'location'])
            ->orderBy('asset_number', 'desc');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('asset_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('fixed_asset_category_id', $filters['category_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('fixed_asset_location_id', $filters['location_id']);
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        return $query->paginate($perPage);
    }

    /**
     * @param  array{
     *     name: array{en: string, ar: string},
     *     fixed_asset_category_id: string,
     *     acquisition_date: string,
     *     in_service_date: string,
     *     cost_minor: int,
     *     useful_life_months?: int,
     *     salvage_value_minor?: int,
     *     opening_accumulated_depreciation_minor?: int,
     *     currency?: string,
     *     description?: string|null,
     *     serial_number?: string|null,
     *     status?: string,
     *     asset_number?: string|null
     * }  $data
     */
    public function createAsset(array $data, ?int $actorId = null): FixedAsset
    {
        /** @var FixedAssetCategory $category */
        $category = FixedAssetCategory::query()->findOrFail($data['fixed_asset_category_id']);

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'fixed_asset_category_id' => [__('Selected asset category is inactive.')],
            ]);
        }

        $currencyCode = CurrencyInput::required($data['currency'] ?? null);
        /** @var Currency|null $currency */
        $currency = Currency::query()->where('code', $currencyCode)->first();
        if (! $currency) {
            throw ValidationException::withMessages([
                'currency' => [__('Currency [:code] is missing.', ['code' => $currencyCode])],
            ]);
        }

        $cost = (int) $data['cost_minor'];
        if ($cost <= 0) {
            throw ValidationException::withMessages([
                'cost_minor' => [__('Asset cost must be greater than zero.')],
            ]);
        }

        $salvage = (int) ($data['salvage_value_minor'] ?? $category->salvage_value_minor);
        if ($salvage < 0) {
            throw ValidationException::withMessages([
                'salvage_value_minor' => [__('Salvage value cannot be negative.')],
            ]);
        }

        if ($salvage > $cost) {
            throw ValidationException::withMessages([
                'salvage_value_minor' => [__('Salvage value cannot exceed historical cost.')],
            ]);
        }

        $usefulLife = (int) ($data['useful_life_months'] ?? $category->useful_life_months);
        if ($usefulLife <= 0) {
            throw ValidationException::withMessages([
                'useful_life_months' => [__('Useful life must be a positive number of months.')],
            ]);
        }

        $openingAccum = (int) ($data['opening_accumulated_depreciation_minor'] ?? 0);
        if ($openingAccum < 0) {
            throw ValidationException::withMessages([
                'opening_accumulated_depreciation_minor' => [__('Opening accumulated depreciation cannot be negative.')],
            ]);
        }

        $maxDepreciable = $cost - $salvage;
        if ($openingAccum > $maxDepreciable) {
            throw ValidationException::withMessages([
                'opening_accumulated_depreciation_minor' => [__('Opening accumulated depreciation cannot exceed depreciable base (Cost - Salvage).')],
            ]);
        }

        $status = $data['status'] ?? 'draft';
        if ($status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Fixed assets must be created as draft and activated through capitalization.')],
            ]);
        }

        return DB::transaction(function () use ($data, $category, $currencyCode, $cost, $salvage, $usefulLife, $openingAccum, $status, $actorId): FixedAsset {
            $assetNumber = ! empty($data['asset_number'])
                ? trim($data['asset_number'])
                : $this->generateAssetNumber();

            if (FixedAsset::query()->where('asset_number', $assetNumber)->exists()) {
                throw ValidationException::withMessages([
                    'asset_number' => [__('Asset number [:asset_number] is already in use.', ['asset_number' => $assetNumber])],
                ]);
            }

            /** @var FixedAsset $asset */
            $asset = FixedAsset::query()->create([
                'id' => (string) Str::uuid(),
                'asset_number' => $assetNumber,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'fixed_asset_category_id' => $category->id,
                'currency' => $currencyCode,
                'acquisition_date' => $data['acquisition_date'],
                'in_service_date' => $data['in_service_date'],
                'cost_minor' => $cost,
                'salvage_value_minor' => $salvage,
                'useful_life_months' => $usefulLife,
                'depreciation_method' => 'straight_line',
                'opening_accumulated_depreciation_minor' => $openingAccum,
                'status' => $status,
                'serial_number' => $data['serial_number'] ?? null,
                'lock_version' => 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset.create',
                entityType: 'fixed_asset',
                entityId: (string) $asset->id,
                after: [
                    'asset_number' => $asset->asset_number,
                    'category_id' => $category->id,
                    'cost_minor' => $asset->cost_minor,
                    'status' => $asset->status,
                ],
            );

            return $asset->load(['category', 'currencyModel']);
        });
    }

    /**
     * @param  array{
     *     name?: array{en: string, ar: string},
     *     description?: string|null,
     *     acquisition_date?: string,
     *     in_service_date?: string,
     *     cost_minor?: int,
     *     salvage_value_minor?: int,
     *     useful_life_months?: int,
     *     opening_accumulated_depreciation_minor?: int,
     *     serial_number?: string|null,
     *     status?: string
     * }  $data
     */
    public function updateAsset(string $id, array $data, ?int $actorId = null): FixedAsset
    {
        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()->findOrFail($id);
        $before = $asset->toArray();

        if ($asset->status !== 'draft') {
            throw ValidationException::withMessages([
                'asset' => [__('Only draft assets can be edited.')],
            ]);
        }

        if (isset($data['name'])) {
            $asset->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $asset->description = $data['description'];
        }

        if (array_key_exists('serial_number', $data)) {
            $asset->serial_number = $data['serial_number'];
        }

        if (isset($data['acquisition_date'])) {
            $asset->acquisition_date = $data['acquisition_date'];
        }

        if (isset($data['in_service_date'])) {
            $asset->in_service_date = $data['in_service_date'];
        }

        $cost = isset($data['cost_minor']) ? (int) $data['cost_minor'] : $asset->cost_minor;
        if ($cost <= 0) {
            throw ValidationException::withMessages([
                'cost_minor' => [__('Asset cost must be greater than zero.')],
            ]);
        }
        $asset->cost_minor = $cost;

        $salvage = isset($data['salvage_value_minor']) ? (int) $data['salvage_value_minor'] : $asset->salvage_value_minor;
        if ($salvage < 0) {
            throw ValidationException::withMessages([
                'salvage_value_minor' => [__('Salvage value cannot be negative.')],
            ]);
        }
        if ($salvage > $cost) {
            throw ValidationException::withMessages([
                'salvage_value_minor' => [__('Salvage value cannot exceed historical cost.')],
            ]);
        }
        $asset->salvage_value_minor = $salvage;

        $usefulLife = isset($data['useful_life_months']) ? (int) $data['useful_life_months'] : $asset->useful_life_months;
        if ($usefulLife <= 0) {
            throw ValidationException::withMessages([
                'useful_life_months' => [__('Useful life must be a positive number of months.')],
            ]);
        }
        $asset->useful_life_months = $usefulLife;

        $openingAccum = isset($data['opening_accumulated_depreciation_minor']) ? (int) $data['opening_accumulated_depreciation_minor'] : $asset->opening_accumulated_depreciation_minor;
        if ($openingAccum < 0) {
            throw ValidationException::withMessages([
                'opening_accumulated_depreciation_minor' => [__('Opening accumulated depreciation cannot be negative.')],
            ]);
        }
        if ($openingAccum > ($cost - $salvage)) {
            throw ValidationException::withMessages([
                'opening_accumulated_depreciation_minor' => [__('Opening accumulated depreciation cannot exceed depreciable base (Cost - Salvage).')],
            ]);
        }
        $asset->opening_accumulated_depreciation_minor = $openingAccum;

        if (isset($data['status'])) {
            if ($data['status'] !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('Fixed assets must be activated through capitalization.')],
                ]);
            }
            $asset->status = $data['status'];
        }

        $asset->lock_version++;
        $asset->updated_by = $actorId;
        $asset->save();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'fixed_asset.update',
            entityType: 'fixed_asset',
            entityId: (string) $asset->id,
            before: $before,
            after: $asset->toArray(),
        );

        return $asset->load(['category', 'currencyModel']);
    }

    public function deleteAsset(string $id, ?int $actorId = null): void
    {
        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()->findOrFail($id);

        if ($asset->status !== 'draft') {
            throw ValidationException::withMessages([
                'asset' => [__('Only draft assets can be deleted.')],
            ]);
        }

        if ($asset->movements()->exists()) {
            throw ValidationException::withMessages([
                'asset' => [__('Assets with movement history cannot be deleted.')],
            ]);
        }

        $before = $asset->toArray();
        $asset->delete();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'fixed_asset.delete',
            entityType: 'fixed_asset',
            entityId: $id,
            before: $before,
        );
    }

    private function generateAssetNumber(): string
    {
        $seq = $this->numberSequenceAllocator->nextValue('fixed_asset');
        $year = now()->format('Y');
        $padded = str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        return "FA-{$year}-{$padded}";
    }
}
